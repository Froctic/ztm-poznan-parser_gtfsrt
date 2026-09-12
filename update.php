<?php

require_once __DIR__ . "/decoder.php";

function updateVehicles()
{
    $url = "https://www.ztm.poznan.pl/pl/dla-deweloperow/getGtfsRtFile?file=vehicle_positions.pb";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);

    $data = curl_exec($ch);

    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($data === false) {
        throw new Exception("cURL: " . $error);
    }

    if ($http != 200) {
        throw new Exception("ZTM HTTP: " . $http);
    }

    $result = decodeVehicleFeed($data);

    $json = json_encode(
        $result,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );

    if ($json === false) {
        throw new Exception("JSON: " . json_last_error_msg());
    }

    if (file_put_contents(
        __DIR__ . "/vehicles.json",
        $json,
        LOCK_EX
    ) === false) {
        throw new Exception("Не удалось записать vehicles.json");
    }

    return $result;
}