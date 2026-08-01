<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'You must log in first.'
    ]);

    exit;
}

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Admin access is required.'
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Request method is not allowed.'
    ]);

    exit;
}

$stmt = $pdo->query(
    "SELECT
        id,
        username,
        role,
        created_at
     FROM users
     ORDER BY created_at DESC"
);

echo json_encode([
    'success' => true,
    'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);