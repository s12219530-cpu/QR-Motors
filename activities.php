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

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Request method is not allowed.'
    ]);

    exit;
}

if ($role === 'admin') {
    $stmt = $pdo->query(
        "SELECT
            activities.id,
            activities.user_id,
            users.username,
            activities.type,
            activities.description,
            activities.metadata,
            activities.created_at
         FROM activities
         INNER JOIN users
            ON users.id = activities.user_id
         ORDER BY activities.created_at DESC"
    );
} else {
    $stmt = $pdo->prepare(
        "SELECT
            activities.id,
            activities.user_id,
            users.username,
            activities.type,
            activities.description,
            activities.metadata,
            activities.created_at
         FROM activities
         INNER JOIN users
            ON users.id = activities.user_id
         WHERE activities.user_id = ?
         ORDER BY activities.created_at DESC"
    );

    $stmt->execute([$userId]);
}

echo json_encode([
    'success' => true,
    'activities' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);