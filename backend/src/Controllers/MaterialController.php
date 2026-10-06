<?php
namespace App\Controllers;

use App\Core\{Auth, Database, Request, Response};

class MaterialController
{
    /** GET /api/materials?type&age&tag&status&q&page — 素材分页 */
    public function index(): void
    {
        $type = Request::query('type');
        $age = Request::query('age');
        $q = Request::query('q');
        $status = Request::query('status', 'published');
        $canSeeAll = Auth::isAdminOrReviewer();
        $page = max(1, (int)Request::query('page', 1));
        $size = min(100, max(1, (int)Request::query('page_size', 20)));

        $where = [];
        $params = [];
        // 非审核角色只能看 published
        if ($canSeeAll && $status === 'all') {
            // 不过滤状态
        } else {
            $where[] = 'status = ?';
            $params[] = $canSeeAll ? $status : 'published';
        }
        if ($type) { $where[] = 'material_type = ?'; $params[] = $type; }
        if ($age)  { $where[] = 'age_band = ?';     $params[] = $age; }
        if ($q)    { $where[] = '(name LIKE ? OR description LIKE ? OR tags LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $data = Database::paginate(
            'SELECT * FROM materials ' . $w . ' ORDER BY id',
            'SELECT COUNT(*) FROM materials ' . $w,
            $params, $page, $size
        );
        foreach ($data['list'] as &$r) {
            $r = Database::decodeJson($r, ['edu_goals', 'tags']);
        }
        Response::ok($data);
    }

    /** GET /api/materials/{id} */
    public function show($id): void
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM materials WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error(404, '素材不存在');
        }
        Response::ok(Database::decodeJson($row, ['edu_goals', 'tags']));
    }

    /** POST /api/materials — 录入(reviewer+) */
    public function store(): void
    {
        Auth::requireRole('admin', 'reviewer');
        $in = Request::json();
        $name = trim((string)($in['name'] ?? ''));
        $type = trim((string)($in['material_type'] ?? ''));
        if ($name === '' || $type === '') {
            Response::error(400, 'name 与 material_type 必填');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO materials (material_type,name,age_band,description,image_prompt,edu_goals,tags,status) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $type,
            $name,
            (string)($in['age_band'] ?? '4-5'),
            (string)($in['description'] ?? ''),
            (string)($in['image_prompt'] ?? ''),
            json_encode($in['edu_goals'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($in['tags'] ?? [], JSON_UNESCAPED_UNICODE),
            (string)($in['status'] ?? 'published'),
        ]);
        Response::ok(['id' => (int)Database::pdo()->lastInsertId()]);
    }

    /** PUT /api/materials/{id} — 编辑(reviewer+) */
    public function update($id): void
    {
        Auth::requireRole('admin', 'reviewer');
        $in = Request::json();
        $allowed = ['material_type', 'name', 'age_band', 'description', 'image_prompt', 'status'];
        $sets = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = (string)$in[$f];
            }
        }
        foreach (['edu_goals', 'tags'] as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = json_encode($in[$f], JSON_UNESCAPED_UNICODE);
            }
        }
        if (!$sets) {
            Response::error(400, '没有可更新的字段');
        }
        $params[] = $id;
        $stmt = Database::pdo()->prepare('UPDATE materials SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
        Response::ok(null);
    }

    /** DELETE /api/materials/{id} — 删除(reviewer+) */
    public function destroy($id): void
    {
        Auth::requireRole('admin', 'reviewer');
        $stmt = Database::pdo()->prepare('DELETE FROM materials WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(null);
    }
}
