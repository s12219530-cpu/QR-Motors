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
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT
            purchase_requests.id,
            purchase_requests.car_id,
            purchase_requests.status,
            purchase_requests.appointment_date,
            purchase_requests.appointment_time,
            purchase_requests.admin_note,
            purchase_requests.requested_at,
            purchase_requests.updated_at,
            cars.name,
            cars.brand,
            cars.year,
            cars.price,
            cars.image
         FROM purchase_requests
         INNER JOIN cars
            ON cars.id = purchase_requests.car_id
         WHERE purchase_requests.user_id = ?
         ORDER BY purchase_requests.requested_at DESC"
    );

    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

    exit;
}

if ($method === 'POST') {
    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    $carId = isset($data['car_id'])
        ? (int) $data['car_id']
        : 0;

    if ($carId <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid car ID.'
        ]);

        exit;
    }

    $checkCar = $pdo->prepare(
        "SELECT id, name
         FROM cars
         WHERE id = ?"
    );

    $checkCar->execute([$carId]);

    $car = $checkCar->fetch(PDO::FETCH_ASSOC);

    if (!$car) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Car not found.'
        ]);

        exit;
    }

    $checkRequest = $pdo->prepare(
        "SELECT id
         FROM purchase_requests
         WHERE user_id = ?
         AND car_id = ?
         AND status NOT IN ('Rejected', 'Completed')
         LIMIT 1"
    );

    $checkRequest->execute([
        $userId,
        $carId
    ]);

    if ($checkRequest->fetch()) {
        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'You already have an active request for this car.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO purchase_requests (
            user_id,
            car_id,
            status
         )
         VALUES (?, ?, 'Pending')"
    );

    $stmt->execute([
        $userId,
        $carId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Your purchase request was sent successfully.',
        'request_id' => (int) $pdo->lastInsertId()
    ]);

    exit;
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Request method is not allowed.'
]);