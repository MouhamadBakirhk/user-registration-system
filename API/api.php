<?php
header('Content-Type: application/json');
include 'connection.php';  // ملف الاتصال وفيه $conn

$action = $_GET['action'] ?? '';

if ($action === 'createtable') {
    // إنشاء جدول users لو مش موجود
    $sql = "
    IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'users')
    CREATE TABLE users (
        id INT IDENTITY(1,1) PRIMARY KEY,
        username NVARCHAR(50) NOT NULL,
        email NVARCHAR(100) NOT NULL,
        password NVARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT GETDATE()
    )";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "Table creation failed", "error" => sqlsrv_errors()]);
    } else {
        echo json_encode(["success" => true, "message" => "Table created successfully or already exists"]);
    }
    exit;
}

elseif ($action === 'select') {
    if (isset($_GET['id'])) {
        $sql = "SELECT id, username, email, created_at FROM users WHERE id = ?";
        $params = [$_GET['id']];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die(json_encode(["success" => false, "message" => "Query failed", "error" => sqlsrv_errors()]));
        }
        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($result && $result['created_at'] instanceof DateTime) {
            $result['created_at'] = $result['created_at']->format('Y-m-d H:i:s');
        }
        echo json_encode($result ?: (object)[]);
    } else {
        $sql = "SELECT id, username, email, created_at FROM users ORDER BY id DESC";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            die(json_encode(["success" => false, "message" => "Query failed", "error" => sqlsrv_errors()]));
        }
        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['created_at'] instanceof DateTime) {
                $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
            }
            $data[] = $row;
        }
        echo json_encode(["data" => $data]);
    }
}

elseif ($action === 'insert') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $email && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, GETDATE())";
        $params = [$username, $email, $hash];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die(json_encode(["success" => false, "message" => "Insert failed", "error" => sqlsrv_errors()]));
        }
        // الحصول على ID الجديد
        $idSql = "SELECT SCOPE_IDENTITY() AS id";
        $idStmt = sqlsrv_query($conn, $idSql);
        $idRow = sqlsrv_fetch_array($idStmt, SQLSRV_FETCH_ASSOC);
        $id = $idRow['id'] ?? null;

        echo json_encode(["success" => true, "id" => $id]);
    } else {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
    }
}

elseif ($action === 'update') {
    $id = $_POST['userId'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['password'] ?? '';

    if ($id && $username && $email && $currentPassword) {
        // جلب الباسورد المخزن
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt === false) {
            die(json_encode(["success" => false, "message" => "Query failed", "error" => sqlsrv_errors()]));
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if (!$row) {
            echo json_encode(["success" => false, "message" => "User not found"]);
            exit;
        }

        $hashedPassword = $row['password'];

        // تحقق من كلمة المرور القديمة
        if (!password_verify($currentPassword, $hashedPassword)) {
            echo json_encode(["success" => false, "message" => "Current password is incorrect"]);
            exit;
        }

        // حدث البيانات
        if (!empty($newPassword)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $sqlUpdate = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
            $params = [$username, $email, $hash, $id];
        } else {
            $sqlUpdate = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            $params = [$username, $email, $id];
        }
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $params);
        if ($stmtUpdate === false) {
            die(json_encode(["success" => false, "message" => "Update failed", "error" => sqlsrv_errors()]));
        }
        echo json_encode(["success" => true, "message" => "User updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
    }
}

elseif ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    if ($id) {
        $sql = "DELETE FROM users WHERE id = ?";
        $params = [$id];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die(json_encode(["success" => false, "message" => "Delete failed", "error" => sqlsrv_errors()]));
        }
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Missing ID"]);
    }
}

else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}
