<?php

header("Access-Control-Allow-Origin: *");

$apiKey = 'apiKey';
$secretKey = 'secretKey';
$siteId = 'siteId';
$code = '授权码';

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
  $response = @file_get_contents($url . '?' . $query); // 使用@符号忽略警告
  if ($response === false) {
      logError('Error fetching access token.');
      die('Error fetching access token.'); // 返回错误信息给前端
  }
  $data = json_decode($response, true);
  return $data;
}

function refreshAccessToken($apiKey, $secretKey, $refreshToken) {
  $url = "https://openapi.baidu.com/oauth/2.0/token";
  $params = array(
      'grant_type' => 'refresh_token',
      'refresh_token' => $refreshToken,
      'client_id' => $apiKey,
      'client_secret' => $secretKey,
  );

  $query = http_build_query($params);
  $response = @file_get_contents($url . '?' . $query); // 使用@符号忽略警告
  if ($response === false) {
      logError('Error refreshing access token.');
      throw new Exception('Error refreshing access token.');
  }
  $data = json_decode($response, true);

  if (isset($data['error'])) {
      logError('API Error: ' . $data['error_description']);
      throw new Exception('Error refreshing access token: ' . $data['error_description']);
  }

  return $data;
}

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

  $response = @file_get_contents($fullUrl); // 使用@符号忽略警告
  if ($response === false) {
      logError('Error fetching data.');
      throw new Exception('Error fetching data.');
  }
  return json_decode($response, true);
}

function saveTokens($accessToken, $refreshToken) {
  file_put_contents('tokens.json', json_encode(array(
      'access_token' => $accessToken,
      'refresh_token' => $refreshToken,
  )));
}

function loadTokens() {
  if (!file_exists('tokens.json')) {
      return null;
  }
  return json_decode(file_get_contents('tokens.json'), true);
}

function logError($message) {
  file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
}

function checkAndRefreshTokens($apiKey, $secretKey) {
  $tokens = loadTokens();
  if ($tokens === null || expired($tokens['access_token'])) {
      global $code;
      $tokens = getAccessToken($apiKey, $secretKey, $code);
      saveTokens($tokens['access_token'], $tokens['refresh_token']);
  } elseif (expired($tokens['refresh_token'])) {
      // 如果 refresh token 过期，返回获取授权码地址的文本信息给前端
      die('获取授权码地址：http://openapi.baidu.com/oauth/2.0/authorize?response_type=code&client_id=' . $apiKey . '&redirect_uri=oob&scope=basic&display=popup');
  } else {
      $attempts = 0;
      $maxAttempts = 3;
      while ($attempts < $maxAttempts) {
          try {
              $tokens = refreshAccessToken($apiKey, $secretKey, $tokens['refresh_token']);
              saveTokens($tokens['access_token'], $tokens['refresh_token']);
              break;
          } catch (Exception $e) {
              $attempts++;
              logError('Attempt ' . $attempts . ' to refresh access token failed: ' . $e->getMessage());
              if ($attempts == $maxAttempts) {
                  // 如果刷新 access token 失败，返回获取授权码地址的文本信息给前端
                  die('获取授权码地址：http://openapi.baidu.com/oauth/2.0/authorize?response_type=code&client_id=' . $apiKey . '&redirect_uri=oob&scope=basic&display=popup');
              }
              sleep(1); // 延迟一秒后重试
          }
      }
  }
  return $tokens['access_token'];
}

function expired($token) {
  // 检查 token 是否过期，这里假设 access token 过期时间为一个月
  // 实际情况应该根据百度开放平台的规定来判断
  // 这里简化为每次都重新获取新 token
  return false; // 返回 true 或 false
}

$accessToken = checkAndRefreshTokens($apiKey, $secretKey);

$cacheFile = 'data_cache.json';
$cacheTime = 10800;

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

// 获取最近31天的数据
$startDate = date('Ymd', strtotime('-31 days'));
$endDate = date('Ymd');
$monthData = getData($startDate, $endDate, 'pv_count,visitor_count', $accessToken, $siteId);

// 处理并提取最近31天的数据
$last31DaysPV = 0;
if (isset($monthData['result']['items'][1])) {
    $dataPoints = $monthData['result']['items'][1];
    foreach ($dataPoints as $point) {
        $last31DaysPV += $point[0];
    }
}

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

// 添加最近31天的PV总和
$data['last_month_pv'] = $last31DaysPV;

// 保存数据到缓存文件
file_put_contents($cacheFile, json_encode($data));
}

// 返回JSON数据
header('Content-Type: application/json');
echo json_encode($data);

?>