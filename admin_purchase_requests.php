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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query(
        "SELECT
            purchase_requests.id,
            purchase_requests.user_id,
            purchase_requests.car_id,
            purchase_requests.status,
            purchase_requests.appointment_date,
            purchase_requests.appointment_time,
            purchase_requests.admin_note,
            purchase_requests.requested_at,
            purchase_requests.updated_at,
            users.username,
            cars.name AS car_name,
            cars.brand,
            cars.year,
            cars.price,
            cars.image
         FROM purchase_requests
         INNER JOIN users
            ON users.id = purchase_requests.user_id
         INNER JOIN cars
            ON cars.id = purchase_requests.car_id
         ORDER BY purchase_requests.requested_at DESC"
    );

    echo json_encode([
        'success' => true,
        'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

    exit;
}

if ($method === 'PATCH') {
    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    $requestId = isset($data['request_id'])
        ? (int) $data['request_id']
        : 0;

    $status = isset($data['status'])
        ? trim($data['status'])
        : '';

    $appointmentDate =
        !empty($data['appointment_date'])
            ? $data['appointment_date']
            : null;

    $appointmentTime =
        !empty($data['appointment_time'])
            ? $data['appointment_time']
            : null;

    $adminNote =
        !empty($data['admin_note'])
            ? trim($data['admin_note'])
            : null;

    $allowedStatuses = [
        'Pending',
        'Approved',
        'Rejected',
        'Appointment Scheduled',
        'Completed'
    ];

    if ($requestId <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid request ID.'
        ]);

        exit;
    }

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid request status.'
        ]);

        exit;
    }

    if (
        $status === 'Appointment Scheduled' &&
        (!$appointmentDate || !$appointmentTime)
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Appointment date and time are required.'
        ]);

        exit;
    }

    $checkRequest = $pdo->prepare(
        "SELECT id
         FROM purchase_requests
         WHERE id = ?"
    );

    $checkRequest->execute([$requestId]);

    if (!$checkRequest->fetch()) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Purchase request not found.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE purchase_requests
         SET
            status = ?,
            appointment_date = ?,
            appointment_time = ?,
            admin_note = ?,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );

    $stmt->execute([
        $status,
        $appointmentDate,
        $appointmentTime,
        $adminNote,
        $requestId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Purchase request updated successfully.'
    ]);

    exit;
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Request method is not allowed.'
]);