<?php
namespace App\Controllers;

use App\Core\{Auth, Database, Request, Response};

class SymbolController
{
    /** GET /api/symbols?category&q&page — 文化符号库 */
    public function index(): void
    {
        $cat = Request::query('category');
        $q = Request::query('q');
        $page = max(1, (int)Request::query('page', 1));
        $size = min(100, max(1, (int)Request::query('page_size', 50)));

        $where = [];
        $params = [];
        if ($cat) { $where[] = 'category = ?'; $params[] = $cat; }
        if ($q)    { $where[] = '(name LIKE ? OR description LIKE ? OR aliases LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $data = Database::paginate(
            'SELECT * FROM symbols ' . $w . ' ORDER BY id',
            'SELECT COUNT(*) FROM symbols ' . $w,
            $params, $page, $size
        );
        foreach ($data['list'] as &$r) {
            $r = Database::decodeJson($r, ['aliases', 'related_materials']);
        }
        Response::ok($data);
    }

    /** GET /api/symbols/{id} */
    public function show($id): void
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM symbols WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error(404, '符号不存在');
        }
        Response::ok(Database::decodeJson($row, ['aliases', 'related_materials']));
    }

    /** POST /api/symbols — 录入(reviewer+) */
    public function store(): void
    {
        Auth::requireRole('admin', 'reviewer');
        $in = Request::json();
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') {
            Response::error(400, 'name 必填');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO symbols (name,category,description,symbol_trace,source,image_prompt,aliases,related_materials) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $name,
            (string)($in['category'] ?? '其他'),
            (string)($in['description'] ?? ''),
            (string)($in['symbol_trace'] ?? ''),
            (string)($in['source'] ?? ''),
            (string)($in['image_prompt'] ?? ''),
            json_encode($in['aliases'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($in['related_materials'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        Response::ok(['id' => (int)Database::pdo()->lastInsertId()]);
    }

    /** PUT /api/symbols/{id} — 编辑(reviewer+) */
    public function update($id): void
    {
        Auth::requireRole('admin', 'reviewer');
        $in = Request::json();
        $sets = [];
        $params = [];
        foreach (['name', 'category', 'description', 'symbol_trace', 'source', 'image_prompt'] as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = (string)$in[$f];
            }
        }
        foreach (['aliases', 'related_materials'] as $f) {
            if (array_key_exists($f, $in)) {
                $sets[] = "$f = ?";
                $params[] = json_encode($in[$f], JSON_UNESCAPED_UNICODE);
            }
        }
        if (!$sets) {
            Response::error(400, '没有可更新的字段');
        }
        $params[] = $id;
        $stmt = Database::pdo()->prepare('UPDATE symbols SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
        Response::ok(null);
    }

    /** DELETE /api/symbols/{id} — 删除(reviewer+) */
    public function destroy($id): void
    {
        Auth::requireRole('admin', 'reviewer');
        $stmt = Database::pdo()->prepare('DELETE FROM symbols WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(null);
    }
}
