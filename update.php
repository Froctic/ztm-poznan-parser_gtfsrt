<?php

require_once __DIR__ . "/decoder.php";

function updateVehicles()
{
    $cacheFile = __DIR__ . "/vehicles.json";
    $cacheTime = 10; // Время кэша в секундах. Данные из Познани не нужны чаще раза в 10 сек.

    // ЕСЛИ файл существует И он свежий (изменен меньше 10 секунд назад)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        // Просто читаем готовый JSON с сервера и возвращаем его, не качая заново из ZTM!
        $jsonData = file_get_contents($cacheFile);
        return json_decode($jsonData, true);
    }

    // --- ЕСЛИ КЭШ УСТАРЕЛ, КАЧАЕМ НОВЫЕ ДАННЫЕ ИЗ ПОЗНАНИ ---
    $url = "https://ztm.poznan.pl";
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

    $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if ($json === false) {
        throw new Exception("JSON: " . json_last_error_msg());
    }

    // Сохраняем свежую копию в кэш
    file_put_contents($cacheFile, $json, LOCK_EX);

    return $result;
}
