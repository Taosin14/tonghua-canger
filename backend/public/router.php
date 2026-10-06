<?php
/**
 * php -S 开发服务器路由:
 *   存在的静态文件直接返回,其余交给 index.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}
require __DIR__ . '/index.php';
