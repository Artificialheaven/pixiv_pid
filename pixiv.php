<?php
// pixiv.php - 更安全和鲁棒的实现
// - 校验 pid（只允许纯数字）
// - 支持 master=1（regular）或 master=0（original）
// - 使用 cURL 并包含超时、referer、UA
// - 更健壮的正则解析并处理转义的 URL
// - 错误处理和合适的 HTTP 状态码

function respond($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

$pid = isset($_GET['pid']) ? trim($_GET['pid']) : '';
$master = isset($_GET['master']) && $_GET['master'] === '1' ? 1 : 0;

if ($pid === '' || !ctype_digit($pid)) {
    respond(400, 'Bad Request: invalid or missing pid');
}

$artworkUrl = "https://www.pixiv.net/artworks/{$pid}";

// Fetch artwork page
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $artworkUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; pixiv_pid/1.0; +https://github.com/Artificialheaven/pixiv_pid)',
    CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
]);
curl_setopt($ch, CURLOPT_REFERER, 'https://www.pixiv.net/');
$page = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($page === false || $httpCode >= 400) {
    curl_close($ch);
    respond(502, 'Bad Gateway: failed to fetch artwork page');
}
curl_close($ch);

// Try to extract image URL. Pixiv 页面中经常包含 JSON，其中 urls.original 或 urls.regular 保存地址。
$imgUrl = '';
$patterns = [];
if ($master) {
    $patterns[] = '/"regular"\s*:\s*"([^"]+)"/';
    $patterns[] = '/"urls"\s*:\s*\{[^}]*"regular"\s*:\s*"([^"]+)"/s';
    // 备用 original
    $patterns[] = '/"original"\s*:\s*"([^"]+)"/';
    $patterns[] = '/"urls"\s*:\s*\{[^}]*"original"\s*:\s*"([^"]+)"/s';
} else {
    $patterns[] = '/"original"\s*:\s*"([^"]+)"/';
    $patterns[] = '/"urls"\s*:\s*\{[^}]*"original"\s*:\s*"([^"]+)"/s';
    // 备用 regular
    $patterns[] = '/"regular"\s*:\s*"([^"]+)"/';
    $patterns[] = '/"urls"\s*:\s*\{[^}]*"regular"\s*:\s*"([^"]+)"/s';
}

foreach ($patterns as $pat) {
    if (preg_match($pat, $page, $m)) {
        $imgUrl = $m[1];
        break;
    }
}

if ($imgUrl === '') {
    // 有时候页面使用转义的斜杠，需要先尝试 unescape 整段 JSON 再匹配
    $unescaped = str_replace('\\/', '/', $page);
    foreach ($patterns as $pat) {
        if (preg_match($pat, $unescaped, $m)) {
            $imgUrl = $m[1];
            break;
        }
    }
}

if ($imgUrl === '') {
    respond(404, 'Not Found: image url not found in artwork page');
}

// 反转义被 JSON 转义的字符串
$imgUrl = str_replace('\\/', '/', $imgUrl);
$imgUrl = trim($imgUrl, '"');

if (!filter_var($imgUrl, FILTER_VALIDATE_URL)) {
    respond(502, 'Bad Gateway: invalid image url parsed');
}

// Fetch image
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $imgUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; pixiv_pid/1.0; +https://github.com/Artificialheaven/pixiv_pid)',
]);
curl_setopt($ch, CURLOPT_REFERER, 'https://www.pixiv.net/');
$data = curl_exec($ch);
$imgHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($data === false || $imgHttp >= 400 || empty($contentType)) {
    respond(502, 'Bad Gateway: failed to fetch image');
}

// 防止极大文件占用带宽（例如限制为 20MB）
$maxSize = 20 * 1024 * 1024; // 20MB
$size = strlen($data);
if ($size > $maxSize) {
    respond(413, 'Payload Too Large: image exceeds allowed size');
}

// 输出图片并合适的 headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=86400');
echo $data;
exit;
