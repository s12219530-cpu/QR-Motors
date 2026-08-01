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
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if ($role !== 'admin') {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Admin access is required.'
        ]);

        exit;
    }

    $stmt = $pdo->query(
        "SELECT
            messages.id,
            messages.user_id,
            users.username,
            messages.email,
            messages.subject,
            messages.message,
            messages.created_at
         FROM messages
         INNER JOIN users
            ON users.id = messages.user_id
         ORDER BY messages.created_at DESC"
    );

    echo json_encode([
        'success' => true,
        'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

    exit;
}

if ($method === 'POST') {
    if ($role !== 'user') {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Only users can send contact messages.'
        ]);

        exit;
    }

    $data = json_decode(
        file_get_contents('php://input'),
        true
    ) ?? [];

    $email = trim($data['email'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address.'
        ]);

        exit;
    }

    if (
        strlen($subject) < 3 ||
        strlen($subject) > 150
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Subject must be between 3 and 150 characters.'
        ]);

        exit;
    }

    if (
        strlen($message) < 5 ||
        strlen($message) > 5000
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Message must be between 5 and 5000 characters.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO messages (
            user_id,
            email,
            subject,
            message
         )
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([
        $userId,
        $email,
        $subject,
        $message
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Your message was sent successfully.',
        'message_id' => (int) $pdo->lastInsertId()
    ]);

    exit;
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Request method is not allowed.'
]);