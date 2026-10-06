<?php
/**
 * 自动加载(PSR-4 风格: App\ -> src/)与全局异常处理
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        // App\X\Y -> src/X/Y.php(本文件在 src/Core 下,基目录取上一级)
        $path = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

set_exception_handler(function (\Throwable $e): void {
    \App\Core\Response::error(500, '服务器内部错误: ' . $e->getMessage());
});
