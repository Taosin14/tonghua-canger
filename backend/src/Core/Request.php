<?php
namespace App\Core;

class Request
{
    /** 解析 JSON 请求体 */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /** 取 Bearer token(Apache 环境兼容) */
    public static function bearer(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $h, $m)) {
            return $m[1];
        }
        // Apache 下 HTTP_AUTHORIZATION 可能缺失,需从 apache_request_headers 取
        if ($h === '' && function_exists('apache_request_headers')) {
            $hdrs = apache_request_headers();
            if (!empty($hdrs['Authorization']) && preg_match('/Bearer\s+(\S+)/i', $hdrs['Authorization'], $m)) {
                return $m[1];
            }
        }
        return null;
    }
}
