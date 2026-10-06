<?php
namespace App\Core;

class Config
{
    private static ?array $data = null;

    /** 点号路径取值,如 Config::get('glm.api_key') */
    public static function get(string $key, $default = null)
    {
        if (self::$data === null) {
            self::$data = require dirname(__DIR__, 2) . '/config.php';
        }
        $val = self::$data;
        foreach (explode('.', $key) as $k) {
            if (!is_array($val) || !array_key_exists($k, $val)) {
                return $default;
            }
            $val = $val[$k];
        }
        return $val;
    }
}
