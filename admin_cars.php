<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must log in first.']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access is required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query(
        "SELECT id, name, brand, year, category, fuel, gear, mileage, price, image,
                stock_quantity, is_active
         FROM cars
         ORDER BY id ASC"
    );

    echo json_encode([
        'success' => true,
        'cars' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

function validateCarData(array $data): array
{
    $name = trim($data['name'] ?? '');
    $brand = trim($data['brand'] ?? '');
    $year = (int)($data['year'] ?? 0);
    $category = trim($data['category'] ?? '');
    $fuel = trim($data['fuel'] ?? '');
    $gear = trim($data['gear'] ?? '');
    $mileage = (int)($data['mileage'] ?? 0);
    $price = (float)($data['price'] ?? 0);
    $image = trim($data['image'] ?? '');
    $stock = (int)($data['stock_quantity'] ?? 0);
    $active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

    if ($name === '' || $brand === '' || $category === '' || $fuel === '' || $gear === '' || $image === '') {
        throw new InvalidArgumentException('Please complete all required car fields.');
    }
    if ($year < 1950 || $year > 2026) {
        throw new InvalidArgumentException('Year must be between 1950 and 2026.');
    }
    if ($mileage < 0 || $mileage > 1000000) {
        throw new InvalidArgumentException('Mileage must be between 0 and 1,000,000 km.');
    }
    if ($price < 0 || $price > 100000000) {
        throw new InvalidArgumentException('Price is invalid.');
    }
    if ($stock < 0 || $stock > 100000) {
        throw new InvalidArgumentException('Stock quantity is invalid.');
    }
    if (!in_array($active, [0, 1], true)) {
        throw new InvalidArgumentException('Invalid visibility value.');
    }

    return [$name, $brand, $year, $category, $fuel, $gear, $mileage, $price, $image, $stock, $active];
}

try {
    if ($method === 'POST') {
        [$name, $brand, $year, $category, $fuel, $gear, $mileage, $price, $image, $stock, $active] = validateCarData($data);

        $stmt = $pdo->prepare(
            "INSERT INTO cars
             (name, brand, year, category, fuel, gear, mileage, price, image, stock_quantity, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $brand, $year, $category, $fuel, $gear, $mileage, $price, $image, $stock, $active]);

        echo json_encode([
            'success' => true,
            'message' => 'Car added successfully.',
            'car_id' => (int)$pdo->lastInsertId()
        ]);
        exit;
    }

    if ($method === 'PATCH') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid car ID.']);
            exit;
        }

        $check = $pdo->prepare('SELECT id FROM cars WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Car not found.']);
            exit;
        }

        // Full edit from the admin form.
        if (array_key_exists('name', $data)) {
            [$name, $brand, $year, $category, $fuel, $gear, $mileage, $price, $image, $stock, $active] = validateCarData($data);

            $stmt = $pdo->prepare(
                "UPDATE cars SET
                    name = ?, brand = ?, year = ?, category = ?, fuel = ?, gear = ?,
                    mileage = ?, price = ?, image = ?, stock_quantity = ?, is_active = ?
                 WHERE id = ?"
            );
            $stmt->execute([$name, $brand, $year, $category, $fuel, $gear, $mileage, $price, $image, $stock, $active, $id]);

            echo json_encode(['success' => true, 'message' => 'Car updated successfully.']);
            exit;
        }

        // Small stock / visibility updates.
        $updates = [];
        $values = [];

        if (array_key_exists('stock_quantity', $data)) {
            $stock = (int)$data['stock_quantity'];
            if ($stock < 0 || $stock > 100000) {
                throw new InvalidArgumentException('Stock quantity is invalid.');
            }
            $updates[] = 'stock_quantity = ?';
            $values[] = $stock;
        }

        if (array_key_exists('is_active', $data)) {
            $active = (int)$data['is_active'];
            if (!in_array($active, [0, 1], true)) {
                throw new InvalidArgumentException('Invalid visibility value.');
            }
            $updates[] = 'is_active = ?';
            $values[] = $active;
        }

        if (!$updates) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nothing to update.']);
            exit;
        }

        $values[] = $id;
        $stmt = $pdo->prepare('UPDATE cars SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($values);

        echo json_encode(['success' => true, 'message' => 'Inventory updated successfully.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Request method is not allowed.']);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while managing cars.']);
}
