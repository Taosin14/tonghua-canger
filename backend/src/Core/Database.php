<?php
namespace App\Core;

class Database
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            $cfg = Config::get('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
            self::$pdo = new \PDO($dsn, $cfg['user'], $cfg['pass'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec('SET NAMES utf8mb4');
        }
        return self::$pdo;
    }

    /** 分页查询(位置占位符 ?,$params 可安全复用两次) */
    public static function paginate(string $baseSql, string $countSql, array $params, int $page, int $pageSize): array
    {
        $stmt = self::pdo()->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        $stmt = self::pdo()->prepare($baseSql . sprintf(' LIMIT %d, %d', ($page - 1) * $pageSize, $pageSize));
        $stmt->execute($params);
        return [
            'list'      => $stmt->fetchAll(),
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /** 把 JSON 列解码为数组(供 API 输出) */
    public static function decodeJson(array $row, array $fields): array
    {
        foreach ($fields as $f) {
            if (isset($row[$f])) {
                $row[$f] = json_decode((string)$row[$f], true) ?? [];
            }
        }
        return $row;
    }
}
