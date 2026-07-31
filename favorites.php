<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
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
         INNER JOIN cars ON cars.id = favorites.car_id
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
$carId = isset($data['car_id']) ? (int) $data['car_id'] : 0;

if ($carId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'رقم السيارة غير صحيح'
    ]);
    exit;
}

if ($method === 'POST') {
    $checkCar = $pdo->prepare("SELECT id FROM cars WHERE id = ?");
    $checkCar->execute([$carId]);

    if (!$checkCar->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'السيارة غير موجودة'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO favorites (user_id, car_id)
         VALUES (?, ?)"
    );
    $stmt->execute([$userId, $carId]);

    echo json_encode([
        'success' => true,
        'message' => 'تمت إضافة السيارة إلى المفضلة'
    ]);
    exit;
}

if ($method === 'DELETE') {
    $stmt = $pdo->prepare(
        "DELETE FROM favorites
         WHERE user_id = ? AND car_id = ?"
    );
    $stmt->execute([$userId, $carId]);

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف السيارة من المفضلة'
    ]);
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'طريقة الطلب غير مسموحة'
]);