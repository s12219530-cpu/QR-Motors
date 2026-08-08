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
            cars.image,
            cars.stock_quantity,
            cars.is_active
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
    ) ?? [];

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

    try {
        $pdo->beginTransaction();

        $checkRequest = $pdo->prepare(
            "SELECT
                purchase_requests.user_id,
                purchase_requests.car_id,
                purchase_requests.status AS current_status,
                cars.name AS car_name,
                cars.stock_quantity
             FROM purchase_requests
             INNER JOIN cars
                ON cars.id = purchase_requests.car_id
             WHERE purchase_requests.id = ?
             FOR UPDATE"
        );

        $checkRequest->execute([$requestId]);
        $request = $checkRequest->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Purchase request not found.'
            ]);
            exit;
        }

        $oldStatus = $request['current_status'];
        $inventoryChanged = false;
        $inventoryMessage = '';

        /*
         * Stock is reduced only when the sale is actually completed.
         * Re-clicking Complete does not reduce stock again.
         */
        if ($status === 'Completed' && $oldStatus !== 'Completed') {
            if ((int) $request['stock_quantity'] <= 0) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'This car is out of stock. The purchase cannot be completed.'
                ]);
                exit;
            }

            $stockUpdate = $pdo->prepare(
                "UPDATE cars
                 SET stock_quantity = stock_quantity - 1
                 WHERE id = ?
                   AND stock_quantity > 0"
            );

            $stockUpdate->execute([(int) $request['car_id']]);

            if ($stockUpdate->rowCount() !== 1) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Could not reduce stock because the car is no longer available.'
                ]);
                exit;
            }

            $inventoryChanged = true;
            $inventoryMessage = ' Stock decreased by 1.';
        }

        /*
         * If a Completed request is changed back to another status,
         * the previously deducted unit is returned to inventory.
         */
        if ($oldStatus === 'Completed' && $status !== 'Completed') {
            $stockRestore = $pdo->prepare(
                "UPDATE cars
                 SET stock_quantity = stock_quantity + 1
                 WHERE id = ?"
            );

            $stockRestore->execute([(int) $request['car_id']]);

            $inventoryChanged = true;
            $inventoryMessage = ' Stock restored by 1.';
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

        $activity = $pdo->prepare(
            "INSERT INTO activities
                (user_id, type, description, metadata)
             VALUES (?, ?, ?, ?)"
        );

        $activity->execute([
            (int) $request['user_id'],
            'Admin Update',
            "Purchase request for {$request['car_name']} changed to $status" . $inventoryMessage,
            json_encode([
                'request_id' => $requestId,
                'car_id' => (int) $request['car_id'],
                'previous_status' => $oldStatus,
                'status' => $status,
                'inventory_changed' => $inventoryChanged
            ], JSON_UNESCAPED_UNICODE)
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Purchase request updated successfully.' . $inventoryMessage
        ]);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error while updating the purchase request.'
        ]);
        exit;
    }
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Request method is not allowed.'
]);
