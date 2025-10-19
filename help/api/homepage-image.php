<?php
// 实时返回首页图片（优先背景图，其次LOGO，最后回退为动态生成PNG）
// 禁止缓存，确保实时
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../admin/db.php';

$setting = $pdo->query('SELECT * FROM settings LIMIT 1')->fetch();
$path = '';
if ($setting) {
    if (!empty($setting['background'])) {
        $path = $setting['background'];
    } elseif (!empty($setting['logo'])) {
        $path = $setting['logo'];
    }
}

// 输出本地或远程图片
function output_image_file($webPath) {
    $baseDir = realpath(__DIR__ . '/..'); // /help 目录
    if (!$baseDir) { return false; }

    // 远程URL
    if (preg_match('#^https?://#i', $webPath)) {
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $data = @file_get_contents($webPath, false, $ctx);
        if ($data === false) return false;
        $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? finfo_buffer($finfo, $data) : 'image/png';
        if ($finfo) finfo_close($finfo);
        header('Content-Type: ' . $mime);
        echo $data;
        return true;
    }

    // 映射到真实路径（支持以 /help/ 开头或相对路径）
    if (strpos($webPath, '/help/') === 0) {
        $local = $baseDir . substr($webPath, strlen('/help'));
    } else {
        $local = $baseDir . '/' . ltrim($webPath, '/');
    }

    if (!is_readable($local)) return false;

    $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? @finfo_file($finfo, $local) : 'image/png';
    if ($finfo) @finfo_close($finfo);
    // 兜底 mime
    if (!$mime || !preg_match('#^image/#', $mime)) {
        // 根据扩展名猜测
        $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
        $map = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $mime = $map[$ext] ?? 'application/octet-stream';
    }

    header('Content-Type: ' . $mime);
    readfile($local);
    return true;
}

if ($path && output_image_file($path)) {
    exit;
}

// 如果没有设置图片或无法读取，则动态生成一个PNG（优先不依赖GD）
$onePxPng = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII='
);

if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/png');
    echo $onePxPng; // 1x1 透明像素作为兜底
    exit;
}

$w = 1200; $h = 630;
$img = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($img, 33, 150, 243); // 蓝色背景
imagefilledrectangle($img, 0, 0, $w, $h, $bg);
$white = imagecolorallocate($img, 255, 255, 255);
$title = ($setting && !empty($setting['site_title'])) ? $setting['site_title'] : 'Help';

// 使用内置字体简单绘制标题
$text = $title;
$font = 5;
$textWidth = imagefontwidth($font) * strlen($text);
$x = max(20, (int)(($w - $textWidth) / 2));
$y = (int)($h / 2);
imagestring($img, $font, $x, $y - 8, $text, $white);

header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
exit;
