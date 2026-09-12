<?php

header("Access-Control-Allow-Origin: https://ztmpzprojects.gamejolt.io");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/update.php";

header("Content-Type: application/json; charset=utf-8");

try {

    $result = updateVehicles();

    echo json_encode([
        "success" => true,
        "updated" => $result["updated"],
        "vehicle_count" => $result["vehicle_count"],
        "vehicles" => $result["vehicles"]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}