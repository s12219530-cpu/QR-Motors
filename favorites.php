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
            cars.id,
            cars.name,
            cars.brand,
            cars.year,
            cars.category,
            cars.fuel,
            cars.gear,
            cars.mileage,
            cars.price,
            cars.image
         FROM favorites
         INNER JOIN cars
            ON cars.id = favorites.car_id
         WHERE favorites.user_id = ?
         ORDER BY favorites.created_at DESC"
    );

    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'favorites' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

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

if ($method === 'POST') {
    $checkCar = $pdo->prepare(
        "SELECT id, name FROM cars WHERE id = ?"
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

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO favorites (user_id, car_id)
         VALUES (?, ?)"
    );

    $stmt->execute([$userId, $carId]);

    if ($stmt->rowCount() > 0) {
        $activity = $pdo->prepare("INSERT INTO activities (user_id, type, description, metadata) VALUES (?, ?, ?, ?)");
        $activity->execute([$userId, 'Favorite', "Added {$car['name']} to favorites", json_encode(['car_id' => $carId], JSON_UNESCAPED_UNICODE)]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Car added to your favorites.'
    ]);

    exit;
}

if ($method === 'DELETE') {
    $carQuery = $pdo->prepare("SELECT name FROM cars WHERE id = ?");
    $carQuery->execute([$carId]);
    $car = $carQuery->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "DELETE FROM favorites
         WHERE user_id = ?
         AND car_id = ?"
    );

    $stmt->execute([$userId, $carId]);

    if ($stmt->rowCount() > 0 && $car) {
        $activity = $pdo->prepare("INSERT INTO activities (user_id, type, description, metadata) VALUES (?, ?, ?, ?)");
        $activity->execute([$userId, 'Favorite', "Removed {$car['name']} from favorites", json_encode(['car_id' => $carId], JSON_UNESCAPED_UNICODE)]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Car removed from your favorites.'
    ]);

    exit;
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Request method is not allowed.'
]);
