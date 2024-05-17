<?php

// 设置API密钥和网站ID
$apiKey = 'apiKey';
$secretKey = 'secretKey';
$siteId = 'siteId';
$code = '授权码';

// 通过授权码获取Access Token函数
function getAccessToken($apiKey, $secretKey, $code) {
    $url = "https://openapi.baidu.com/oauth/2.0/token";
    $params = array(
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $apiKey,
        'client_secret' => $secretKey,
        'redirect_uri' => 'oob',
    );

    $query = http_build_query($params);
    $response = file_get_contents($url . '?' . $query);
    if ($response === false) {
        die('Error fetching access token.');
    }
    $data = json_decode($response, true);

    return $data;
}

// 刷新Access Token函数
function refreshAccessToken($apiKey, $secretKey, $refreshToken) {
    $url = "https://openapi.baidu.com/oauth/2.0/token";
    $params = array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id' => $apiKey,
        'client_secret' => $secretKey,
    );

    $query = http_build_query($params);
    $response = file_get_contents($url . '?' . $query);
    if ($response === false) {
        die('Error refreshing access token.');
    }
    $data = json_decode($response, true);

    return $data;
}

// 定义获取数据的函数
function getData($startDate, $endDate, $metrics, $accessToken, $siteId) {
    $url = "https://openapi.baidu.com/rest/2.0/tongji/report/getData";
    $params = array(
        'access_token' => $accessToken,
        'site_id' => $siteId,
        'method' => 'overview/getTimeTrendRpt',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'metrics' => $metrics,
    );

    $query = http_build_query($params);
    $fullUrl = $url . '?' . $query;

    $response = file_get_contents($fullUrl);
    if ($response === false) {
        die('Error fetching data.');
    }
    return json_decode($response, true);
}

// 持久化存储令牌
function saveTokens($accessToken, $refreshToken) {
    file_put_contents('tokens.json', json_encode(array(
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
    )));
}

// 从文件中加载令牌
function loadTokens() {
    if (!file_exists('tokens.json')) {
        return null;
    }
    return json_decode(file_get_contents('tokens.json'), true);
}

// 检查并刷新令牌
function checkAndRefreshTokens($apiKey, $secretKey) {
    $tokens = loadTokens();
    if ($tokens === null) {
        global $code;
        $tokens = getAccessToken($apiKey, $secretKey, $code);
        saveTokens($tokens['access_token'], $tokens['refresh_token']);
    } else {
        $tokens = refreshAccessToken($apiKey, $secretKey, $tokens['refresh_token']);
        saveTokens($tokens['access_token'], $tokens['refresh_token']);
    }
    return $tokens['access_token'];
}

// 获取Access Token
$accessToken = checkAndRefreshTokens($apiKey, $secretKey);

// 定义缓存文件路径
$cacheFile = 'data_cache.json';
$cacheTime = 60; // 缓存时间，单位：秒

// 检查缓存文件是否存在且未过期
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    $data = json_decode(file_get_contents($cacheFile), true);
} else {
    // 准备数据
    $data = array(
        'today_uv' => null,
        'today_pv' => null,
        'yesterday_uv' => null,
        'yesterday_pv' => null,
        'last_month_pv' => null,
        'last_year_pv' => null,
    );

    // 获取一整年的数据
    $startDate = date('Ymd', strtotime('-1 year'));
    $endDate = date('Ymd');
    $yearData = getData($startDate, $endDate, 'pv_count,visitor_count', $accessToken, $siteId);

    // 处理并提取所需数据
    if (isset($yearData['result']['items'][1])) {
        $dataPoints = $yearData['result']['items'][1];
        $today = date('Y/m/d');
        $yesterday = date('Y/m/d', strtotime('-1 day'));
        $lastMonth = date('Y/m/d', strtotime('-30 days'));
        
        foreach ($yearData['result']['items'][0] as $index => $date) {
            if ($date[0] == $today) {
                $data['today_uv'] = $dataPoints[$index][1];
                $data['today_pv'] = $dataPoints[$index][0];
            } elseif ($date[0] == $yesterday) {
                $data['yesterday_uv'] = $dataPoints[$index][1];
                $data['yesterday_pv'] = $dataPoints[$index][0];
            } elseif ($date[0] == $lastMonth) {
                $data['last_month_pv'] = $dataPoints[$index][0];
            }
        }
        
        $data['last_year_pv'] = array_sum(array_column($dataPoints, 0));
    }

    // 保存数据到缓存文件
    file_put_contents($cacheFile, json_encode($data));
}

// 返回JSON数据
header('Content-Type: application/json');
echo json_encode($data);

?>
