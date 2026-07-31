<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require 'db.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare(
        "INSERT INTO activities (user_id, type, description)
         VALUES (:user_id, 'Logout', 'Logged out of QR Motors')"
    );

    $stmt->execute([
        'user_id' => $_SESSION['user_id']
    ]);
}

session_unset();
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Logout successful.'
]);