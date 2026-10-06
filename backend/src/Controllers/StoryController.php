<?php
namespace App\Controllers;

use App\Core\{Auth, Database, Request, Response};

class StoryController
{
    /** GET /api/stories?theme&age&status&source_type&q&page — 图书馆(默认只出 published) */
    public function index(): void
    {
        $theme = Request::query('theme');
        $age = Request::query('age');
        $q = Request::query('q');
        $source = Request::query('source_type');
        $status = Request::query('status', 'published');
        $canSeeAll = Auth::isAdminOrReviewer();
        $page = max(1, (int)Request::query('page', 1));
        $size = min(100, max(1, (int)Request::query('page_size', 20)));

        $where = [];
        $params = [];
        if ($canSeeAll && $status === 'all') {
            // 不过滤状态
        } else {
            $where[] = 'status = ?';
            $params[] = $canSeeAll ? $status : 'published';
        }
        if ($theme)  { $where[] = 'theme = ?';      $params[] = $theme; }
        if ($age)    { $where[] = 'age_band = ?';   $params[] = $age; }
        if ($source) { $where[] = 'source_type = ?'; $params[] = $source; }
        if ($q)      { $where[] = '(title LIKE ? OR subtitle LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $data = Database::paginate(
            'SELECT id,title,subtitle,theme,age_band,page_count,style,cover_illus,edu_goals_summary,'
            . 'source_type,ai_generated,status,version,created_at,published_at FROM stories '
            . $w . ' ORDER BY id DESC',
            'SELECT COUNT(*) FROM stories ' . $w,
            $params, $page, $size
        );
        foreach ($data['list'] as &$r) {
            $r = Database::decodeJson($r, ['edu_goals_summary']);
        }
        Response::ok($data);
    }

    /** GET /api/stories/{id} — 全量(含 pages) */
    public function show($id): void
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM stories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error(404, '故事不存在');
        }
        Response::ok(Database::decodeJson($row, ['pages', 'edu_goals_summary', 'symbol_ids', 'characters']));
    }

    /** POST /api/stories — 手工新建(登录教师) */
    public function store(): void
    {
        Auth::requireRole('teacher', 'reviewer', 'admin');
        $in = Request::json();
        $title = trim((string)($in['title'] ?? ''));
        $pages = $in['pages'] ?? null;
        if ($title === '' || !is_array($pages)) {
            Response::error(400, 'title 与 pages(数组)必填');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO stories (title,subtitle,theme,material_id,age_band,page_count,style,cover_illus,pages,'
            . 'edu_goals_summary,symbol_ids,source_type,ai_generated,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $title,
            (string)($in['subtitle'] ?? ''),
            (string)($in['theme'] ?? ''),
            $in['material_id'] ? (int)$in['material_id'] : null,
            (string)($in['age_band'] ?? '4-5'),
            (int)($in['page_count'] ?? count($pages)),
            (string)($in['style'] ?? '水彩'),
            (string)($in['cover_illus'] ?? ''),
            json_encode($pages, JSON_UNESCAPED_UNICODE),
            json_encode($in['edu_goals_summary'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($in['symbol_ids'] ?? [], JSON_UNESCAPED_UNICODE),
            (string)($in['source_type'] ?? 'user'),
            !empty($in['ai_generated']) ? 1 : 0,
            'draft',
        ]);
        Response::ok(['id' => (int)Database::pdo()->lastInsertId()]);
    }

    /** PUT /api/stories/{id} — 编辑保存(version+1;published 仅审核角色可改) */
    public function update($id): void
    {
        $user = Auth::requireRole('teacher', 'reviewer', 'admin');
        $stmt = Database::pdo()->prepare('SELECT * FROM stories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error(404, '故事不存在');
        }
        if ($row['status'] === 'published' && !in_array($user['role'], ['admin', 'reviewer'], true)) {
            Response::error(403, '已发布的故事只有审核员可修改');
        }
        $in = Request::json();
        $sets = ['version = version + 1', 'status = ?'];
        $params = ['draft'];
        foreach (['title', 'subtitle', 'theme', 'age_band', 'style', 'cover_illus'] as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = (string)$in[$f];
            }
        }
        if (array_key_exists('material_id', $in)) {
            $sets[] = 'material_id = ?';
            $params[] = $in['material_id'] ? (int)$in['material_id'] : null;
        }
        if (array_key_exists('pages', $in) && is_array($in['pages'])) {
            $sets[] = 'pages = ?';
            $params[] = json_encode($in['pages'], JSON_UNESCAPED_UNICODE);
            $sets[] = 'page_count = ?';
            $params[] = count($in['pages']);
        }
        foreach (['edu_goals_summary', 'symbol_ids', 'characters'] as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = json_encode($in[$f], JSON_UNESCAPED_UNICODE);
            }
        }
        $params[] = $id;
        $stmt = Database::pdo()->prepare('UPDATE stories SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
        Response::ok(null);
    }

    /** POST /api/stories/{id}/submit — 提交审核 draft/rejected -> pending */
    public function submit($id): void
    {
        Auth::requireRole('teacher', 'reviewer', 'admin');
        $stmt = Database::pdo()->prepare("UPDATE stories SET status='pending' WHERE id = ? AND status IN ('draft','rejected')");
        $stmt->execute([$id]);
        Response::ok(null);
    }

    /** POST /api/stories/{id}/clone — 复制改编(新草稿) */
    public function cloneStory($id): void
    {
        Auth::requireRole('teacher', 'reviewer', 'admin');
        $stmt = Database::pdo()->prepare('SELECT * FROM stories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error(404, '故事不存在');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO stories (title,subtitle,theme,material_id,age_band,page_count,style,cover_illus,pages,'
            . 'edu_goals_summary,symbol_ids,source_type,ai_generated,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $row['title'] . '(副本)',
            $row['subtitle'],
            $row['theme'],
            $row['material_id'],
            $row['age_band'],
            $row['page_count'],
            $row['style'],
            $row['cover_illus'],
            $row['pages'],
            $row['edu_goals_summary'],
            $row['symbol_ids'],
            'user',
            0,
            'draft',
        ]);
        Response::ok(['id' => (int)Database::pdo()->lastInsertId()]);
    }

    /** DELETE /api/stories/{id} — 删除(published 仅审核角色) */
    public function destroy($id): void
    {
        $user = Auth::requireRole('teacher', 'reviewer', 'admin');
        $stmt = Database::pdo()->prepare('SELECT status FROM stories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error(404, '故事不存在');
        }
        if ($row['status'] === 'published' && !in_array($user['role'], ['admin', 'reviewer'], true)) {
            Response::error(403, '已发布的故事只有审核员可删除');
        }
        $stmt = Database::pdo()->prepare('DELETE FROM stories WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(null);
    }
}
