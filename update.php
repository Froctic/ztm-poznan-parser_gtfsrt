<?php

require_once __DIR__ . "/decoder.php";

function updateVehicles()
{
    $cacheFile = __DIR__ . "/vehicles.json";
    $cacheTime = 10; // Время кэша в секундах. Данные из Познани не обновляются чаще раза в 10 сек.

    // 1. ПРОВЕРКА КЭША: Если файл существует и он свежий, отдаем его мгновенно без запроса к ZTM
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $jsonData = file_get_contents($cacheFile);
        $result = json_decode($jsonData, true);
        
        if ($result !== null) {
            return $result;
        }
    }

    // 2. СКАЧИВАНИЕ ОБНОВЛЕНИЙ: Если кэш устарел, идем на сервер Познани
    $url = "https://ztm.poznan.pl";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FAILONERROR => true, // Падать, если ZTM вернул ошибку сервера (например, 502)
        CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
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

    // Защита от пустых или поврежденных ответов сети
    if (strlen($data) < 100) {
        throw new Exception("Скачанный Protobuf файл слишком мал (" . strlen($data) . " байт). Сервер ZTM временно перегружен.");
    }

    // 3. ДЕКОДИРОВАНИЕ: Переводим бинарный файл в массив
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

    // 4. ЗАПИСЬ В КЭШ: Сохраняем полученный JSON на сервере Render
    if (file_put_contents(
        $cacheFile,
        $json,
        LOCK_EX
    ) === false) {
        throw new Exception("Не удалось записать vehicles.json");
    }

    return $result;
}
