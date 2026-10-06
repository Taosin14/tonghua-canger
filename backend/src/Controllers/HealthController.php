<?php
namespace App\Controllers;

use App\Core\{Database, Response};
use App\Services\AiClient;

class HealthController
{
    /** GET /api/health — 冒烟检查(前端据此提示降级) */
    public function index(): void
    {
        $db = 'ok';
        try {
            Database::pdo()->query('SELECT 1');
        } catch (\Throwable $e) {
            $db = 'error: ' . $e->getMessage();
        }
        Response::ok([
            'service' => 'tonghua-canger-api',
            'db'      => $db,
            'ai'      => AiClient::available() ? 'ready' : 'unconfigured',
            'time'    => date('Y-m-d H:i:s'),
        ]);
    }
}
