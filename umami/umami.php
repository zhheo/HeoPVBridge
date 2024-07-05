<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// 配置 Umami API 的凭据
$apiBaseUrl = 'https://um.zhheo.com';
$token = '你的tocken';
$websiteId = '你的网站id';
$cacheFile = 'umami_cache.json';
$cacheTime = 600; // 缓存时间为10分钟（600秒）

// 获取当前时间戳（毫秒级）
$currentTimestamp = time() * 1000;

// Umami API 的起始时间戳（毫秒级）
$startTimestampToday = strtotime("today") * 1000;
$startTimestampYesterday = strtotime("yesterday") * 1000;
$startTimestampLastMonth = strtotime("-1 month") * 1000;
$startTimestampLastYear = strtotime("-1 year") * 1000;

// 定义 Umami API 请求函数
function fetchUmamiData($apiBaseUrl, $websiteId, $startAt, $endAt, $token) {
    $url = "$apiBaseUrl/api/websites/$websiteId/stats?" . http_build_query([
        'startAt' => $startAt,
        'endAt' => $endAt
    ]);
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => [
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ]
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === FALSE) {
        $error = error_get_last();
        echo "Error fetching data: " . $error['message'] . "\n";
        echo "URL: " . $url . "\n";
        return null;
    }

    return json_decode($response, true);
}

// 检查缓存文件是否存在且未过期
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    // 读取缓存文件
    $cachedData = file_get_contents($cacheFile);
    echo $cachedData;
} else {
    // 获取统计数据
    $todayData = fetchUmamiData($apiBaseUrl, $websiteId, $startTimestampToday, $currentTimestamp, $token);
    $yesterdayData = fetchUmamiData($apiBaseUrl, $websiteId, $startTimestampYesterday, $startTimestampToday, $token);
    $lastMonthData = fetchUmamiData($apiBaseUrl, $websiteId, $startTimestampLastMonth, $currentTimestamp, $token);
    $lastYearData = fetchUmamiData($apiBaseUrl, $websiteId, $startTimestampLastYear, $currentTimestamp, $token);

    // 组装返回的 JSON 数据
    $responseData = [
        "today_uv" => $todayData['visitors']['value'] ?? null,
        "today_pv" => $todayData['pageviews']['value'] ?? null,
        "yesterday_uv" => $yesterdayData['visitors']['value'] ?? null,
        "yesterday_pv" => $yesterdayData['pageviews']['value'] ?? null,
        "last_month_pv" => $lastMonthData['pageviews']['value'] ?? null,
        "last_year_pv" => $lastYearData['pageviews']['value'] ?? null
    ];

    // 将数据写入缓存文件
    file_put_contents($cacheFile, json_encode($responseData));

    // 输出 JSON 数据
    echo json_encode($responseData);
}
?>
