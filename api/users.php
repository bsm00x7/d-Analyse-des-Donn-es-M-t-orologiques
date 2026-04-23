<?php
require(__DIR__ . '/../config/database.php');
session_start();

header('Content-Type: application/json');

// For testing purposes, allow all requests (remove in production)
// In production, uncomment this check:
/*
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
*/

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch all users
        $stmt = $conn->prepare("SELECT id, name, email, role, isActive FROM users ORDER BY id DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'status' => $row['isActive'] ? 'active' : 'suspended',
                'avatar' => strtoupper(substr($row['name'], 0, 1)),
                'last' => 'Recently',
                'created' => date('Y')
            ];
        }
        echo json_encode($users);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? 'user';
        $status = $data['status'] ?? 'active';
        $isActive = ($status === 'active') ? 1 : 0;

        if (empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and email are required']);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            exit();
        }


        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists']);
            exit();
        }
        $check->close();

        //
        $defaultPassword = 'password123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, isActive) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $email, $hashedPassword, $role, $isActive);

        if ($stmt->execute()) {
            $id = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'id' => $id,
                'message' => 'User created successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create user: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'PUT':

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            exit();
        }

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? 'user';
        $status = $data['status'] ?? 'active';
        $isActive = ($status === 'active') ? 1 : 0;
        if (empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and email are required']);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            exit();
        }


        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists']);
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, isActive = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $email, $role, $isActive, $id);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update user: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':

        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            exit();
        }

        // Check if user exists
        $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user: ' . $conn->error]);
        }
        $stmt->close();
        break;
}

$conn->close();
