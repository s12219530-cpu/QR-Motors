<?php

header('Content-Type: application/json; charset=utf-8');

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (strlen($username) < 3) {
    echo json_encode([
        'success' => false,
        'message' => 'Username must contain at least 3 characters.'
    ]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must contain at least 6 characters.'
    ]);
    exit;
}

if (strtolower($username) === 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'This username is reserved.'
    ]);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, role)
         VALUES (:username, :password_hash, 'user')"
    );

    $stmt->execute([
        'username' => $username,
        'password_hash' => $passwordHash
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully.'
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode([
            'success' => false,
            'message' => 'This username already exists.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error.'
        ]);
    }
}