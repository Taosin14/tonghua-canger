<?php
/**
 * 童画苍洱 API 唯一入口(前端控制器)
 * 开发: php -S 127.0.0.1:8080 -t public public/router.php
 * 生产: Apache 经 .htaccess 转发 /api 请求到本文件
 */
require __DIR__ . '/../src/Core/bootstrap.php';

use App\Core\Router;
use App\Core\Config;
use App\Controllers\{
    HealthController, AuthController, MaterialController, SymbolController,
    StoryController, WorkController, AiController, AdminController
};

// CORS
header('Access-Control-Allow-Origin: ' . Config::get('cors_origin', '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$r = new Router();
// 健康检查
$r->add('GET', '/api/health', HealthController::class, 'index');
// 认证
$r->add('POST', '/api/auth/login', AuthController::class, 'login');
$r->add('GET', '/api/auth/me', AuthController::class, 'me');
// 素材
$r->add('GET', '/api/materials', MaterialController::class, 'index');
$r->add('GET', '/api/materials/{id}', MaterialController::class, 'show');
$r->add('POST', '/api/materials', MaterialController::class, 'store');
$r->add('PUT', '/api/materials/{id}', MaterialController::class, 'update');
$r->add('DELETE', '/api/materials/{id}', MaterialController::class, 'destroy');
// 符号
$r->add('GET', '/api/symbols', SymbolController::class, 'index');
$r->add('GET', '/api/symbols/{id}', SymbolController::class, 'show');
$r->add('POST', '/api/symbols', SymbolController::class, 'store');
$r->add('PUT', '/api/symbols/{id}', SymbolController::class, 'update');
$r->add('DELETE', '/api/symbols/{id}', SymbolController::class, 'destroy');
// 故事
$r->add('GET', '/api/stories', StoryController::class, 'index');
$r->add('GET', '/api/stories/{id}', StoryController::class, 'show');
$r->add('POST', '/api/stories', StoryController::class, 'store');
$r->add('PUT', '/api/stories/{id}', StoryController::class, 'update');
$r->add('DELETE', '/api/stories/{id}', StoryController::class, 'destroy');
$r->add('POST', '/api/stories/{id}/submit', StoryController::class, 'submit');
$r->add('POST', '/api/stories/{id}/clone', StoryController::class, 'cloneStory');
// 作品
$r->add('GET', '/api/works', WorkController::class, 'index');
$r->add('POST', '/api/works', WorkController::class, 'store');
$r->add('POST', '/api/upload', WorkController::class, 'upload');
$r->add('PUT', '/api/works/{id}', WorkController::class, 'update');
$r->add('DELETE', '/api/works/{id}', WorkController::class, 'destroy');
// AI
$r->add('POST', '/api/ai/generate/story', AiController::class, 'generate');
$r->add('POST', '/api/ai/polish', AiController::class, 'polish');
$r->add('POST', '/api/ai/generate-image', AiController::class, 'generateImage');
$r->add('POST', '/api/ai/extract-characters', AiController::class, 'extractCharacters');
$r->add('POST', '/api/ai/set-character-image', AiController::class, 'setCharacterImage');
$r->add('POST', '/api/ai/tts', AiController::class, 'tts');
$r->add('GET', '/api/ai/quota', AiController::class, 'quota');
// 后台
$r->add('GET', '/api/admin/review/queue', AdminController::class, 'reviewQueue');
$r->add('POST', '/api/admin/review/{type}/{id}', AdminController::class, 'review');
$r->add('GET', '/api/admin/ai-logs', AdminController::class, 'aiLogs');
$r->add('GET', '/api/admin/ai-logs/export', AdminController::class, 'aiLogsExport');
$r->add('GET', '/api/admin/stats', AdminController::class, 'stats');
$r->add('POST', '/api/admin/import', AdminController::class, 'importRows');

$r->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
