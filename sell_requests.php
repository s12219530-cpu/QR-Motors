<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must log in first.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT sell_requests.*, users.username FROM sell_requests INNER JOIN users ON users.id = sell_requests.user_id ORDER BY sell_requests.created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT sell_requests.*, users.username FROM sell_requests INNER JOIN users ON users.id = sell_requests.user_id WHERE sell_requests.user_id = ? ORDER BY sell_requests.created_at DESC");
        $stmt->execute([$userId]);
    }
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($method === 'POST') {
    if ($role !== 'user') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only users can submit sell-car requests.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $carName = trim($data['car_name'] ?? '');
    $brand = trim($data['brand'] ?? '');
    $model = trim($data['model'] ?? '');
    $year = (int) ($data['year'] ?? 0);
    $price = (float) ($data['price'] ?? 0);
    $city = trim($data['city'] ?? '');
    $fuel = trim($data['fuel'] ?? '');
    $transmission = trim($data['transmission'] ?? '');
    $mileage = ($data['mileage'] ?? '') !== '' ? (int) $data['mileage'] : null;
    $phone = trim($data['phone'] ?? '');
    $description = trim($data['description'] ?? '');

    if ($carName === '' || $brand === '' || $model === '' || $city === '' || $fuel === '' || $transmission === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please complete all required car details.']);
        exit;
    }
    if ($year < 1950 || $year > 2026) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Year must be between 1950 and 2026.']);
        exit;
    }
    if ($price < 1000 || $price > 10000000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Price must be between 1,000 and 10,000,000 NIS.']);
        exit;
    }
    if (!preg_match('/^05[69][0-9]{7}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Phone number must start with 059 or 056 and contain 10 digits.']);
        exit;
    }
    if ($mileage !== null && ($mileage < 0 || $mileage > 1000000)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mileage must be between 0 and 1,000,000 km.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO sell_requests (user_id, car_name, brand, model, year, price, city, fuel, transmission, mileage, phone, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$userId, $carName, $brand, $model, $year, $price, $city, $fuel, $transmission, $mileage, $phone, $description !== '' ? $description : null]);

    echo json_encode(['success' => true, 'message' => 'Your car was submitted successfully.', 'request_id' => (int) $pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Request method is not allowed.']);
