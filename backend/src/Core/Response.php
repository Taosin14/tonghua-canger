<?php
namespace App\Core;

class Response
{
    /** 成功: {code:0, data:...} */
    public static function ok($data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** 失败: {code:4xx/5xx, message} */
    public static function error(int $code, string $message): void
    {
        http_response_code($code >= 400 ? $code : 400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => $code, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
