<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter username and password.'
    ]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, username, password_hash, role
     FROM users
     WHERE username = :username
     LIMIT 1"
);

$stmt->execute([
    'username' => $username
]);

$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

$activity = $pdo->prepare(
    "INSERT INTO activities (user_id, type, description)
     VALUES (:user_id, 'Login', 'Logged in to QR Motors')"
);

$activity->execute([
    'user_id' => $user['id']
]);

echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role']
    ]
]);