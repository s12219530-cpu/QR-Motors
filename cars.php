<?php

header('Content-Type: application/json; charset=utf-8');

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Request method is not allowed.'
    ]);

    exit;
}


$stmt = $pdo->query(
    "SELECT
        id,
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
        is_active,

        CASE
            WHEN stock_quantity > 0
            THEN 'Available'
            ELSE 'Out of Stock'
        END AS availability

     FROM cars

     WHERE is_active = 1

     ORDER BY id ASC"
);


echo json_encode([
    'success' => true,
    'cars' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);