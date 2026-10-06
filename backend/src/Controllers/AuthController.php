<?php
namespace App\Controllers;

use App\Core\{Auth, Database, Request, Response};

class AuthController
{
    /** POST /api/auth/login {username,password} -> {token,user} */
    public function login(): void
    {
        $in = Request::json();
        $username = trim((string)($in['username'] ?? ''));
        $password = (string)($in['password'] ?? '');
        if ($username === '' || $password === '') {
            Response::error(400, '请输入用户名和密码');
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            Response::error(401, '用户名或密码错误');
        }
        $token = Auth::newToken();
        $stmt = Database::pdo()->prepare('UPDATE users SET token = ? WHERE id = ?');
        $stmt->execute([$token, $u['id']]);
        unset($u['password_hash'], $u['token']);
        Response::ok(['token' => $token, 'user' => $u]);
    }

    /** GET /api/auth/me -> 当前用户 */
    public function me(): void
    {
        $u = Auth::user();
        if (!$u) {
            Response::error(401, '未登录');
        }
        unset($u['password_hash'], $u['token']);
        Response::ok($u);
    }
}
