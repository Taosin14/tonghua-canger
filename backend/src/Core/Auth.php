<?php
namespace App\Core;

class Auth
{
    /** 当前登录用户(无 token 返回 null) */
    public static function user(): ?array
    {
        $token = Request::bearer();
        if (!$token) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE token = ?');
        $stmt->execute([$token]);
        $u = $stmt->fetch();
        return $u ?: null;
    }

    /** 要求登录且角色在 $roles 中,否则 401/403 */
    public static function requireRole(string ...$roles): array
    {
        $u = self::user();
        if (!$u) {
            Response::error(401, '请先登录');
        }
        if (!in_array($u['role'], $roles, true)) {
            Response::error(403, '无权限执行此操作');
        }
        return $u;
    }

    public static function isAdminOrReviewer(): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['role'], ['admin', 'reviewer'], true);
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
