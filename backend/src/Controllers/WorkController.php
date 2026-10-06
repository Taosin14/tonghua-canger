<?php
namespace App\Controllers;

use App\Core\{Auth, Database, Request, Response};

class WorkController
{
    /** GET /api/works?type&page — 我的作品(比赛演示阶段全局可见) */
    public function index(): void
    {
        $type = Request::query('type');
        $page = max(1, (int)Request::query('page', 1));
        $size = min(100, max(1, (int)Request::query('page_size', 20)));
        $where = [];
        $params = [];
        if ($type) { $where[] = 'work_type = ?'; $params[] = $type; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $data = Database::paginate(
            'SELECT * FROM works ' . $w . ' ORDER BY id DESC',
            'SELECT COUNT(*) FROM works ' . $w,
            $params, $page, $size
        );
        foreach ($data['list'] as &$r) {
            $r = Database::decodeJson($r, ['params', 'content', 'exported_formats']);
        }
        Response::ok($data);
    }

    /** POST /api/works — 保存作品 */
    public function store(): void
    {
        $in = Request::json();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO works (work_type,title,params,content,story_id,status,exported_formats) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            (string)($in['work_type'] ?? 'book'),
            (string)($in['title'] ?? ''),
            json_encode($in['params'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($in['content'] ?? [], JSON_UNESCAPED_UNICODE),
            !empty($in['story_id']) ? (int)$in['story_id'] : null,
            (string)($in['status'] ?? 'draft'),
            json_encode($in['exported_formats'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        Response::ok(['id' => (int)Database::pdo()->lastInsertId()]);
    }

    /** PUT /api/works/{id} */
    public function update($id): void
    {
        $in = Request::json();
        $sets = [];
        $params = [];
        foreach (['work_type', 'title', 'status'] as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = (string)$in[$f];
            }
        }
        if (array_key_exists('story_id', $in)) {
            $sets[] = 'story_id = ?';
            $params[] = !empty($in['story_id']) ? (int)$in['story_id'] : null;
        }
        foreach (['params', 'content', 'exported_formats'] as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = json_encode($in[$f], JSON_UNESCAPED_UNICODE);
            }
        }
        if (!$sets) {
            Response::error(400, '没有可更新的字段');
        }
        $params[] = $id;
        $stmt = Database::pdo()->prepare('UPDATE works SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
        Response::ok(null);
    }

    /** DELETE /api/works/{id} */
    public function destroy($id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM works WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(null);
    }

    /**
     * POST /api/upload {image: base64(不含前缀), type: 'artwork'|'courseware'}
     * 教师上传图片(环创素材/课件插图),落盘 frontend/public/images/{type}/,返回相对路径
     */
    public function upload(): void
    {
        Auth::requireRole('teacher', 'reviewer', 'admin');
        $in = Request::json();
        $b64 = (string)($in['image'] ?? '');
        $type = (string)($in['type'] ?? 'artwork');
        if (!in_array($type, ['artwork', 'courseware'], true)) {
            Response::error(400, 'type 必须是 artwork 或 courseware');
        }
        $b64 = preg_replace('#^data:image/\w+;base64,#', '', $b64);
        $img = base64_decode($b64, true);
        if ($img === false || strlen($img) < 100 || strlen($img) > 8 * 1024 * 1024) {
            Response::error(400, '图片数据无效(需 base64,大小 100B~8MB)');
        }
        $rel = sprintf('images/%s/u%d_%s.png', $type, time(), bin2hex(random_bytes(4)));
        $abs = dirname(__DIR__, 3) . '/frontend/public/' . $rel;
        $dir = dirname($abs);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            Response::error(500, '目录创建失败');
        }
        if (file_put_contents($abs, $img) === false) {
            Response::error(500, '图片保存失败');
        }
        Response::ok(['path' => $rel]);
    }
}
