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
    if ($role === 'admin') {
        $stmt = $pdo->query(
            "SELECT
                sell_requests.*,
                users.username
             FROM sell_requests
             INNER JOIN users
                ON users.id = sell_requests.user_id
             ORDER BY sell_requests.created_at DESC"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT
                sell_requests.*,
                users.username
             FROM sell_requests
             INNER JOIN users
                ON users.id = sell_requests.user_id
             WHERE sell_requests.user_id = ?
             ORDER BY sell_requests.created_at DESC"
        );
        $stmt->execute([$userId]);
    }

    echo json_encode([
        'success' => true,
        'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

if ($method === 'POST') {
    if ($role !== 'user') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Only users can submit sell-car requests.'
        ]);
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
    $mileage = ($data['mileage'] ?? '') !== ''
        ? (int) $data['mileage']
        : null;
    $phone = trim($data['phone'] ?? '');
    $description = trim($data['description'] ?? '');

    if (
        $carName === '' ||
        $brand === '' ||
        $model === '' ||
        $city === '' ||
        $fuel === '' ||
        $transmission === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Please complete all required car details.'
        ]);
        exit;
    }

    if ($year < 1950 || $year > 2026) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Year must be between 1950 and 2026.'
        ]);
        exit;
    }

    if ($price < 1000 || $price > 10000000) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Price must be between 1,000 and 10,000,000 NIS.'
        ]);
        exit;
    }

    if (!preg_match('/^05[69][0-9]{7}$/', $phone)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Phone number must start with 059 or 056 and contain 10 digits.'
        ]);
        exit;
    }

    if ($mileage !== null && ($mileage < 0 || $mileage > 1000000)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Mileage must be between 0 and 1,000,000 km.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO sell_requests
            (
                user_id,
                car_name,
                brand,
                model,
                year,
                price,
                city,
                fuel,
                transmission,
                mileage,
                phone,
                description,
                status
            )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
    );

    $stmt->execute([
        $userId,
        $carName,
        $brand,
        $model,
        $year,
        $price,
        $city,
        $fuel,
        $transmission,
        $mileage,
        $phone,
        $description !== '' ? $description : null
    ]);

    $requestId = (int) $pdo->lastInsertId();

    $activity = $pdo->prepare(
        "INSERT INTO activities
            (user_id, type, description, metadata)
         VALUES (?, ?, ?, ?)"
    );

    $activity->execute([
        $userId,
        'Sell Request',
        "Submitted $carName for admin review",
        json_encode([
            'request_id' => $requestId,
            'car_name' => $carName
        ], JSON_UNESCAPED_UNICODE)
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Your car was submitted successfully.',
        'request_id' => $requestId
    ]);
    exit;
}

if ($method === 'PATCH') {
    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Admin access is required.'
        ]);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $requestId = (int) ($data['request_id'] ?? 0);
    $status = trim($data['status'] ?? '');

    $allowedStatuses = ['Pending', 'Approved', 'Rejected'];

    if ($requestId <= 0 || !in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request ID or status.'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $requestQuery = $pdo->prepare(
            "SELECT
                id,
                user_id,
                car_name,
                brand,
                model,
                year,
                price,
                city,
                fuel,
                transmission,
                mileage,
                phone,
                description,
                status
             FROM sell_requests
             WHERE id = ?
             FOR UPDATE"
        );

        $requestQuery->execute([$requestId]);
        $request = $requestQuery->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Sell-car request not found.'
            ]);
            exit;
        }

        $oldStatus = $request['status'];
        $inventoryMessage = '';

        /*
         * Approving a user's sell request adds exactly one unit
         * to showroom inventory. Re-clicking Approve does not add twice.
         */
        if ($status === 'Approved' && $oldStatus !== 'Approved') {
            $carQuery = $pdo->prepare(
                "SELECT id, stock_quantity
                 FROM cars
                 WHERE name = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            $carQuery->execute([$request['car_name']]);
            $car = $carQuery->fetch(PDO::FETCH_ASSOC);

            if ($car) {
                $increaseStock = $pdo->prepare(
                    "UPDATE cars
                     SET
                        stock_quantity = stock_quantity + 1,
                        is_active = 1
                     WHERE id = ?"
                );

                $increaseStock->execute([(int) $car['id']]);
                $inventoryMessage = ' Inventory increased by 1.';
            } else {
                /*
                 * If the model is not already in the cars table,
                 * create it as a used car with one unit in stock.
                 */
                $insertCar = $pdo->prepare(
                    "INSERT INTO cars
                        (
                            name,
                            brand,
                            year,
                            category,
                            fuel,
                            gear,
                            mileage,
                            price,
                            image,
                            stock_quantity,
                            is_active
                        )
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
                );

                $insertCar->execute([
                    $request['car_name'],
                    $request['brand'],
                    (int) $request['year'],
                    'Used Car',
                    $request['fuel'],
                    $request['transmission'],
                    $request['mileage'] !== null
                        ? (int) $request['mileage']
                        : 0,
                    (float) $request['price'],
                    'car-placeholder'
                ]);

                $inventoryMessage = ' Car added to inventory with stock 1.';
            }
        }

        /*
         * If an already-approved sell request is changed back to another
         * status, undo the previously added unit so inventory stays correct.
         */
        if ($oldStatus === 'Approved' && $status !== 'Approved') {
            $carQuery = $pdo->prepare(
                "SELECT id, stock_quantity
                 FROM cars
                 WHERE name = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            $carQuery->execute([$request['car_name']]);
            $car = $carQuery->fetch(PDO::FETCH_ASSOC);

            if ($car && (int) $car['stock_quantity'] > 0) {
                $decreaseStock = $pdo->prepare(
                    "UPDATE cars
                     SET stock_quantity = stock_quantity - 1
                     WHERE id = ?
                       AND stock_quantity > 0"
                );

                $decreaseStock->execute([(int) $car['id']]);
                $inventoryMessage = ' Previously added inventory unit was removed.';
            }
        }

        $stmt = $pdo->prepare(
            "UPDATE sell_requests
             SET status = ?
             WHERE id = ?"
        );

        $stmt->execute([$status, $requestId]);

        $activity = $pdo->prepare(
            "INSERT INTO activities
                (user_id, type, description, metadata)
             VALUES (?, ?, ?, ?)"
        );

        $activity->execute([
            (int) $request['user_id'],
            'Admin Update',
            "Sell-car request for {$request['car_name']} was $status" . $inventoryMessage,
            json_encode([
                'request_id' => $requestId,
                'previous_status' => $oldStatus,
                'status' => $status,
                'inventory_updated' => $inventoryMessage !== ''
            ], JSON_UNESCAPED_UNICODE)
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Sell-car request marked as $status." . $inventoryMessage
        ]);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error while updating the sell-car request.'
        ]);
        exit;
    }
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Request method is not allowed.'
]);
