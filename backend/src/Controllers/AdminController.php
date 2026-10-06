<?php
namespace App\Controllers;

use App\Core\{Auth, Database, Request, Response};

class AdminController
{
    /** GET /api/admin/review/queue?type=story&status=pending — 待审队列(admin/reviewer) */
    public function reviewQueue(): void
    {
        Auth::requireRole('admin', 'reviewer');
        $status = Request::query('status', 'pending');
        $page = max(1, (int)Request::query('page', 1));
        $size = min(100, max(1, (int)Request::query('page_size', 20)));
        $data = Database::paginate(
            "SELECT id,title,subtitle,theme,age_band,page_count,status,version,source_type,ai_generated,review_note,created_at,updated_at "
            . 'FROM stories WHERE status = ? ORDER BY created_at DESC',
            'SELECT COUNT(*) FROM stories WHERE status = ?',
            [$status], $page, $size
        );
        Response::ok($data);
    }

    /** POST /api/admin/review/{type}/{id} {action:approve|reject, note?} */
    public function review($type, $id): void
    {
        $user = Auth::requireRole('admin', 'reviewer');
        $in = Request::json();
        $action = (string)($in['action'] ?? '');
        if (!in_array($action, ['approve', 'reject'], true)) {
            Response::error(400, 'action 必须是 approve 或 reject');
        }
        if ($type === 'story') {
            if ($action === 'approve') {
                $stmt = Database::pdo()->prepare(
                    "UPDATE stories SET status='published', published_at=NOW(), review_note=NULL, reviewed_by=? WHERE id = ?"
                );
                $stmt->execute([$user['id'], $id]);
            } else {
                $stmt = Database::pdo()->prepare(
                    "UPDATE stories SET status='rejected', review_note=?, reviewed_by=? WHERE id = ?"
                );
                $stmt->execute([(string)($in['note'] ?? ''), $user['id'], $id]);
            }
        } elseif (in_array($type, ['material', 'symbol'], true)) {
            $table = $type === 'material' ? 'materials' : 'symbols';
            $newStatus = $action === 'approve' ? 'published' : 'draft';
            $stmt = Database::pdo()->prepare("UPDATE {$table} SET status=? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
        } else {
            Response::error(400, 'type 必须是 story/material/symbol');
        }
        Response::ok(null);
    }

    /** GET /api/admin/ai-logs?purpose&page — 调用日志(admin) */
    public function aiLogs(): void
    {
        Auth::requireRole('admin', 'reviewer');
        $purpose = Request::query('purpose');
        $page = max(1, (int)Request::query('page', 1));
        $size = min(200, max(1, (int)Request::query('page_size', 50)));
        $where = '';
        $params = [];
        if ($purpose) {
            $where = 'WHERE purpose = ?';
            $params[] = $purpose;
        }
        Response::ok(Database::paginate(
            'SELECT * FROM ai_logs ' . $where . ' ORDER BY id DESC',
            'SELECT COUNT(*) FROM ai_logs ' . $where,
            $params, $page, $size
        ));
    }

    /** GET /api/admin/ai-logs/export — CSV 下载(AIGC 申报材料,admin) */
    public function aiLogsExport(): void
    {
        Auth::requireRole('admin', 'reviewer');
        $stmt = Database::pdo()->query('SELECT * FROM ai_logs ORDER BY id DESC LIMIT 10000');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tonghua-canger-ai-logs.csv"');
        echo "\xEF\xBB\xBF"; // BOM,Excel 识别 UTF-8
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'purpose', 'model', 'status', 'input_tokens', 'output_tokens', 'latency_ms', 'http_status', 'error_msg', 'target_type', 'target_id', 'created_at']);
        while ($row = $stmt->fetch()) {
            fputcsv($out, [
                $row['id'], $row['purpose'], $row['model'], $row['status'],
                $row['input_tokens'], $row['output_tokens'], $row['latency_ms'],
                $row['http_status'], $row['error_msg'], $row['target_type'], $row['target_id'], $row['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    /** GET /api/admin/stats — 后台概览(admin/reviewer) */
    public function stats(): void
    {
        Auth::requireRole('admin', 'reviewer');
        $pdo = Database::pdo();
        $one = function (string $sql) use ($pdo): int {
            return (int)$pdo->query($sql)->fetchColumn();
        };
        $storiesByStatus = [];
        foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM stories GROUP BY status') as $r) {
            $storiesByStatus[$r['status']] = (int)$r['c'];
        }
        Response::ok([
            'materials'       => $one('SELECT COUNT(*) FROM materials'),
            'symbols'         => $one('SELECT COUNT(*) FROM symbols'),
            'stories_total'   => $one('SELECT COUNT(*) FROM stories'),
            'stories_by_status' => $storiesByStatus,
            'works'           => $one('SELECT COUNT(*) FROM works'),
            'ai_calls_total'  => $one('SELECT COUNT(*) FROM ai_logs'),
            'ai_success_today' => $one("SELECT COUNT(*) FROM ai_logs WHERE status='success' AND created_at >= CURDATE()"),
            'ai_fallback_total' => $one("SELECT COUNT(*) FROM ai_logs WHERE status='fallback'"),
        ]);
    }

    /** POST /api/admin/import {type:'materials'|'symbols', rows:[...]} — 批量导入(admin,兜底入口) */
    public function importRows(): void
    {
        Auth::requireRole('admin', 'reviewer');
        $in = Request::json();
        $type = (string)($in['type'] ?? '');
        $rows = $in['rows'] ?? [];
        if (!is_array($rows)) {
            Response::error(400, 'rows 必须是数组');
        }
        $imported = 0;
        $pdo = Database::pdo();
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['name'])) {
                continue;
            }
            if ($type === 'materials') {
                $stmt = $pdo->prepare(
                    'INSERT INTO materials (material_type,name,age_band,description,image_prompt,edu_goals,tags,status) VALUES (?,?,?,?,?,?,?,?) '
                    . 'ON DUPLICATE KEY UPDATE material_type=VALUES(material_type), age_band=VALUES(age_band), '
                    . 'description=VALUES(description), image_prompt=VALUES(image_prompt), edu_goals=VALUES(edu_goals), tags=VALUES(tags)'
                );
                $stmt->execute([
                    (string)($row['material_type'] ?? ''),
                    (string)$row['name'],
                    (string)($row['age_band'] ?? '4-5'),
                    (string)($row['description'] ?? ''),
                    (string)($row['image_prompt'] ?? ''),
                    json_encode($row['edu_goals'] ?? [], JSON_UNESCAPED_UNICODE),
                    json_encode($row['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                    'published',
                ]);
            } elseif ($type === 'symbols') {
                $stmt = $pdo->prepare(
                    'INSERT INTO symbols (name,category,description,symbol_trace,source,image_prompt,aliases,related_materials) VALUES (?,?,?,?,?,?,?,?) '
                    . 'ON DUPLICATE KEY UPDATE category=VALUES(category), description=VALUES(description), '
                    . 'symbol_trace=VALUES(symbol_trace), source=VALUES(source), image_prompt=VALUES(image_prompt)'
                );
                $stmt->execute([
                    (string)$row['name'],
                    (string)($row['category'] ?? '其他'),
                    (string)($row['description'] ?? ''),
                    (string)($row['symbol_trace'] ?? ''),
                    (string)($row['source'] ?? ''),
                    (string)($row['image_prompt'] ?? ''),
                    json_encode($row['aliases'] ?? [], JSON_UNESCAPED_UNICODE),
                    json_encode($row['related_materials'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            } else {
                Response::error(400, 'type 必须是 materials 或 symbols');
            }
            $imported++;
        }
        Response::ok(['imported' => $imported]);
    }
}
