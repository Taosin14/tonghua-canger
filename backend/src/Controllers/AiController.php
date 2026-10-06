<?php
namespace App\Controllers;

use App\Core\{Config, Database, Request, Response};
use App\Services\{AiClient, Polisher, SdWebuiClient, StoryAssembler, TtsService};

class AiController
{
    /**
     * POST /api/ai/generate/story {theme,age_band,page_count,style,material_id?}
     * 生成管线: 查素材库 -> 模板拼装 -> GLM 生成(可选) -> 入库 status=draft
     * 降级时(无 Key/断网/超时/配额用尽)返回 ai_used:false 的完整草稿。
     */
    public function generate(): void
    {
        $in = Request::json();
        $theme = trim((string)($in['theme'] ?? ''));
        $age = (string)($in['age_band'] ?? '4-5');
        $pageCount = max(4, min(12, (int)($in['page_count'] ?? 8)));
        $style = (string)($in['style'] ?? '水彩');
        $materialId = !empty($in['material_id']) ? (int)$in['material_id'] : null;

        // 1. 找素材(生成的事实底座)
        $material = $this->findMaterial($theme, $age, $materialId);
        if (!$material) {
            $stmt = Database::pdo()->query("SELECT name FROM materials WHERE status='published' ORDER BY id");
            $names = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $hint = implode('、', array_slice($names, 0, 24));
            Response::error(404, "素材库中未找到主题「{$theme}」。可尝试:$hint 等(共 " . count($names) . ' 个主题,全部可在文化库-素材库查看)');
        }
        $material = Database::decodeJson($material, ['edu_goals', 'tags']);

        // 2. 24 小时内已有同参数草稿 -> 直接复用(省配额)
        $existing = $this->findRecentDraft($theme, $age, $pageCount, $style);
        if ($existing !== null) {
            Response::ok([
                'story'   => Database::decodeJson($existing, ['pages', 'edu_goals_summary', 'symbol_ids']),
                'ai_used' => false,
                'reused'  => true,
                'message' => '24 小时内已有同参数草稿,已为你载入(避免重复消耗 AI 配额)',
            ]);
        }

        // 3. 模板拼装(兜底,永远可用)
        $pages = StoryAssembler::assemble($material, $pageCount, $style);
        $aiUsed = false;
        $aiChars = [];
        $promptHash = md5(json_encode(['generate', $material['id'], $age, $pageCount, $style], JSON_UNESCAPED_UNICODE));
        $logStatus = 'fallback';
        $logResult = ['ok' => false, 'content' => null, 'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
                      'http_status' => null, 'error' => '未尝试', 'latency_ms' => 0];

        if (AiClient::available()) {
            if (AiClient::usedToday() >= (int)Config::get('glm.daily_limit')) {
                $logResult['error'] = '每日配额已用尽';
            } else {
                $symbolTrace = $this->symbolTraceFor($material['name'], $theme);
                [$sys, $usr] = Polisher::promptsForGenerate($material, $age, $pageCount, $style, $symbolTrace);
                $logResult = AiClient::chat(
                    [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $usr]],
                    ['response_format' => ['type' => 'json_object']]
                );
                if ($logResult['ok']) {
                    $aiPages = $this->parsePagesJson($logResult['content']);
                    if ($aiPages !== null) {
                        $pages = $this->mergePages($pages, $aiPages);
                        // 顺带解析角色设定卡(生图提示词用)
                        $contentData = $this->parseJsonContent($logResult['content']);
                        $aiChars = is_array($contentData['characters'] ?? null) ? $contentData['characters'] : [];
                        $aiUsed = true;
                        $logStatus = 'success';
                    } else {
                        $logResult['error'] = 'AI 返回 JSON 校验失败,已降级拼装';
                    }
                }
            }
        } else {
            $logResult['error'] = '未配置 GLM_API_KEY';
        }

        // 4. 入库(status=draft,进审核流)
        $title = $this->deriveTitle($theme, $material['name']);
        $summary = $this->computeSummary($pages);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO stories (title,subtitle,theme,material_id,age_band,page_count,style,cover_illus,pages,'
            . 'edu_goals_summary,symbol_ids,characters,source_type,ai_generated,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $title,
            $material['name'] . ' · ' . $style . '绘本',
            $theme,
            $material['id'],
            $age,
            $pageCount,
            $style,
            '',
            json_encode($pages, JSON_UNESCAPED_UNICODE),
            json_encode($summary, JSON_UNESCAPED_UNICODE),
            json_encode([], JSON_UNESCAPED_UNICODE),
            json_encode($aiChars, JSON_UNESCAPED_UNICODE),
            'ai',
            $aiUsed ? 1 : 0,
            'draft',
        ]);
        $storyId = (int)Database::pdo()->lastInsertId();
        AiClient::log('story_generate', $promptHash, $logResult, 'story', $storyId, $aiUsed ? null : 'fallback');

        Response::ok([
            'story' => [
                'id' => $storyId, 'title' => $title, 'theme' => $theme, 'age_band' => $age,
                'page_count' => $pageCount, 'style' => $style, 'pages' => $pages,
                'edu_goals_summary' => $summary, 'status' => 'draft',
            ],
            'ai_used' => $aiUsed,
            'message' => $aiUsed ? '已用 GLM 大模型生成,可进入编辑器查看并提交审核' : 'AI 不可用,已用素材库模板拼装生成草稿(内容基于人工审核素材,依然可靠)',
        ]);
    }

    /**
     * POST /api/ai/polish {story_id?,page_no,mode,text}
     * mode: polish 润色 / expand 扩写2页 / goal 配教育目标 / title 起标题
     */
    public function polish(): void
    {
        $in = Request::json();
        $mode = (string)($in['mode'] ?? 'polish');
        $text = trim((string)($in['text'] ?? ''));
        $storyId = !empty($in['story_id']) ? (int)$in['story_id'] : 0;
        $age = '4-5';
        $theme = '';
        if ($storyId) {
            $stmt = Database::pdo()->prepare('SELECT age_band, theme FROM stories WHERE id = ?');
            $stmt->execute([$storyId]);
            $s = $stmt->fetch();
            if ($s) {
                $age = $s['age_band'];
                $theme = $s['theme'];
            }
        }
        if ($text === '' && $mode !== 'title') {
            Response::error(400, '请提供待处理的文本');
        }
        $promptHash = md5(json_encode(['polish', $mode, $age, $text], JSON_UNESCAPED_UNICODE));
        $fallback = ['ok' => false, 'content' => null, 'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
                     'http_status' => null, 'error' => '未配置 GLM_API_KEY', 'latency_ms' => 0];

        if (!AiClient::available()) {
            AiClient::log($this->purposeFor($mode), $promptHash, $fallback, $storyId ? 'story' : null, $storyId ?: null);
            Response::ok(['ai_used' => false, 'message' => '未配置 GLM API Key,已返回原文', 'result' => ['text' => $text]]);
        }
        if (AiClient::usedToday() >= (int)Config::get('glm.daily_limit')) {
            Response::error(429, '今日 AI 配额已用完(控制成本),请明天再试或使用模板拼装');
        }

        [$sys, $usr] = Polisher::promptForPolish($mode, $age, $text, $theme);
        $res = AiClient::chat(
            [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $usr]],
            ['response_format' => ['type' => 'json_object']]
        );
        AiClient::log($this->purposeFor($mode), $promptHash, $res, $storyId ? 'story' : null, $storyId ?: null);

        if (!$res['ok']) {
            Response::ok(['ai_used' => false, 'message' => 'AI 调用失败,已返回原文: ' . $res['error'], 'result' => ['text' => $text]]);
        }
        $parsed = $this->parseJsonContent($res['content']);
        if ($parsed === null) {
            Response::ok(['ai_used' => false, 'message' => 'AI 返回格式异常,已返回原文', 'result' => ['text' => $text]]);
        }
        Response::ok(['ai_used' => true, 'result' => $parsed]);
    }

    /** GET /api/ai/quota — 当日用量 */
    public function quota(): void
    {
        Response::ok([
            'used_today' => AiClient::usedToday(),
            'limit'      => (int)Config::get('glm.daily_limit'),
            'available'  => AiClient::available(),
        ]);
    }

    /**
     * POST /api/ai/generate-image {story_id, page_no}
     * 生成本页插画,引擎按 image.provider 路由:
     *   sdwebui(自建,无水印/角色一致) -> 失败自动降级智谱 CogView -> 均失败返回提示
     * 提示词 = 角色卡(黑发黑眼黄皮肤白族孩子)+ 该页配图需求 + 统一风格后缀 + 名字歧义规避
     * 图片落盘 frontend/public/images/books/story{id}/p{no}.png,记 ai_logs(model 区分引擎)
     */
    public function generateImage(): void
    {
        set_time_limit(360); // 自建 SD 首次加载 IP-Adapter/CLIP 较慢,放宽 PHP 执行时间
        $in = Request::json();
        $storyId = (int)($in['story_id'] ?? 0);
        $pageNo = (int)($in['page_no'] ?? 0);
        if ($storyId <= 0 || $pageNo <= 0) {
            Response::error(400, 'story_id 与 page_no 必填');
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM stories WHERE id = ?');
        $stmt->execute([$storyId]);
        $story = $stmt->fetch();
        if (!$story) {
            Response::error(404, '故事不存在');
        }
        $pages = json_decode((string)$story['pages'], true) ?: [];
        $idx = null;
        foreach ($pages as $i => $p) {
            if ((int)($p['page_no'] ?? 0) === $pageNo) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            Response::error(404, '该页不存在');
        }
        if (trim((string)($pages[$idx]['illus_prompt'] ?? '')) === '') {
            Response::error(400, '该页没有配图需求,请先在编辑器中填写插画提示词');
        }
        // 配额只约束 GLM 引擎调用;自建 SD 生图不受限(翻译失败时自动降级中文提示词)

        [$enTags, $focus] = $this->ensureEnglishPrompt($story, $pages, $idx, $storyId);
        // 手动指定配图重点(编辑器设置,覆盖 AI 判定)
        $manual = (string)($pages[$idx]['focus_manual'] ?? '');
        if ($manual === 'object' || $manual === 'person') {
            $focus = $manual;
            $pages[$idx]['focus'] = $manual;
            if ($manual === 'object') {
                $pages[$idx]['illus_prompt_en'] = $this->stripPersonTags((string)($pages[$idx]['illus_prompt_en'] ?? ''));
            }
        }
        $prompt = $this->buildImagePrompt($story, $pages[$idx]);
        $warnings = [];
        $prompt = $this->applyLoraIfCompat($prompt, $warnings);
        // 事物为主的页(focus=object)不注入定妆照,否则 IP-Adapter 会把角色硬拽进画面
        $refB64 = ($focus === 'object') ? null : $this->characterRefImage($story);
        // 事物为主页负面提示词禁人脸/人物/拟人化眼睛,防止恐怖人脸与"景物长眼睛"
        $negative = null;
        if ($focus === 'object') {
            $negative = (string)Config::get('image.sd.negative_prompt')
                . ', person, people, human, face, child, boy, girl, man, woman, hands, fingers, portrait'
                . ', eyes, eye, faces on objects, kawaii face, anthropomorphic, cute face';
        }
        $hash = md5(json_encode(['image', $storyId, $pageNo, $prompt], JSON_UNESCAPED_UNICODE));
        $image = null;
        $engine = '';
        $errors = [];

        // 引擎1:自建 SD WebUI(人物页走 IP-Adapter 定妆照锁定长相)
        if (Config::get('image.provider') === 'sdwebui') {
            $res = SdWebuiClient::txt2img($prompt, $negative, $refB64);
            AiClient::log('image_gen', $hash, [
                'ok' => $res['ok'], 'content' => null, 'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
                'http_status' => $res['ok'] ? 200 : null, 'error' => $res['error'], 'latency_ms' => $res['latency_ms'],
            ], 'story', $storyId, null, 'sdwebui-guofeng3');
            if ($res['ok']) {
                $image = $res['image'];
                $engine = 'sdwebui';
            } else {
                $errors[] = 'SD WebUI: ' . $res['error'];
            }
        }
        // 引擎2(降级):智谱 CogView(受每日配额约束)
        if ($image === null && AiClient::available() && AiClient::usedToday() < (int)Config::get('glm.daily_limit')) {
            $res = AiClient::generateImage($prompt);
            AiClient::log('image_gen', $hash, [
                'ok' => $res['ok'], 'content' => null, 'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
                'http_status' => $res['http_status'] ?? null, 'error' => $res['error'], 'latency_ms' => $res['latency_ms'],
            ], 'story', $storyId, null, 'cogview');
            if ($res['ok']) {
                $img = $this->downloadFile($res['url']);
                if ($img !== null) {
                    $image = $img;
                    $engine = 'glm';
                } else {
                    $errors[] = '智谱图片下载失败(URL 过期)';
                }
            } else {
                $errors[] = '智谱: ' . $res['error'];
            }
        }
        if ($image === null) {
            Response::ok(['generated' => false, 'message' => '生图失败(已记录): ' . implode(';', $errors)]);
        }
        // 落盘入库
        $rel = sprintf('images/books/story%d/p%d.png', $storyId, $pageNo);
        $abs = dirname(__DIR__, 3) . '/frontend/public/' . $rel;
        if (!is_dir(dirname($abs)) && !mkdir(dirname($abs), 0777, true)) {
            Response::ok(['generated' => false, 'message' => '图片目录创建失败']);
        }
        if (file_put_contents($abs, $image) === false) {
            Response::ok(['generated' => false, 'message' => '图片保存失败']);
        }
        $pages[$idx]['illus'] = $rel;
        $stmt = Database::pdo()->prepare('UPDATE stories SET pages = ? WHERE id = ?');
        $stmt->execute([json_encode($pages, JSON_UNESCAPED_UNICODE), $storyId]);
        Response::ok(['generated' => true, 'illus' => $rel, 'engine' => $engine, 'warnings' => $warnings]);
    }

    /**
     * POST /api/ai/extract-characters {story_id}
     * 用 GLM 从故事文本提取角色设定卡(黑发黑眼黄皮肤白族孩子),写入 stories.characters
     */
    public function extractCharacters(): void
    {
        $in = Request::json();
        $storyId = (int)($in['story_id'] ?? 0);
        if ($storyId <= 0) {
            Response::error(400, 'story_id 必填');
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM stories WHERE id = ?');
        $stmt->execute([$storyId]);
        $story = $stmt->fetch();
        if (!$story) {
            Response::error(404, '故事不存在');
        }
        if (!AiClient::available()) {
            Response::error(400, '未配置 GLM_API_KEY,无法提取角色卡');
        }
        $pages = json_decode((string)$story['pages'], true) ?: [];
        $texts = [];
        foreach ($pages as $p) {
            if (($p['type'] ?? '') !== 'cover' && !empty($p['text'])) {
                $texts[] = $p['text'];
            }
        }
        $content = '书名:' . $story['title'] . ';面向年龄:' . $story['age_band'] . "岁\n" . implode("\n", array_slice($texts, 0, 5));
        $sys = '你是幼儿绘本角色设定师。从故事文本中提取所有出场角色,只输出 JSON:'
            . '{"characters":[{"name":"角色名","role":"主角/配角","appearance":"年龄、性别、发型发色、眼睛颜色、皮肤、服饰"}]}。'
            . '硬性要求:1.中国白族儿童统一"黑色头发、黑色眼睛、黄色皮肤";2.服饰写白族传统服饰;'
            . '3.儿童角色的年龄按故事面向的年龄段写具体岁数(如"3岁"),不要写"不详";'
            . '4.人类角色的 appearance 末尾必须写明"是人类";5.不要遗漏出场角色,也不要编造未出场角色。';
        $res = AiClient::chat(
            [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $content]],
            ['response_format' => ['type' => 'json_object']]
        );
        AiClient::log('character_extract', md5($content), $res, 'story', $storyId);
        if (!$res['ok']) {
            Response::error(502, '提取失败: ' . $res['error']);
        }
        $parsed = $this->parseJsonContent($res['content']);
        $chars = is_array($parsed['characters'] ?? null) ? $parsed['characters'] : [];
        if (!$chars) {
            Response::error(502, '未解析出角色');
        }
        $stmt = Database::pdo()->prepare('UPDATE stories SET characters = ? WHERE id = ?');
        $stmt->execute([json_encode($chars, JSON_UNESCAPED_UNICODE), $storyId]);
        Response::ok(['characters' => $chars]);
    }

    /**
     * POST /api/ai/set-character-image {story_id, name, page_no}
     * 把某页当前插画存为指定角色的定妆照(IP-Adapter 参考图),写入 characters[].ref_image
     */
    public function setCharacterImage(): void
    {
        $in = Request::json();
        $storyId = (int)($in['story_id'] ?? 0);
        $name = trim((string)($in['name'] ?? ''));
        $pageNo = (int)($in['page_no'] ?? 0);
        if ($storyId <= 0 || $name === '' || $pageNo <= 0) {
            Response::error(400, 'story_id、name、page_no 必填');
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM stories WHERE id = ?');
        $stmt->execute([$storyId]);
        $story = $stmt->fetch();
        if (!$story) {
            Response::error(404, '故事不存在');
        }
        $pages = json_decode((string)$story['pages'], true) ?: [];
        $src = '';
        foreach ($pages as $p) {
            if ((int)($p['page_no'] ?? 0) === $pageNo) {
                $src = (string)($p['illus'] ?? '');
                break;
            }
        }
        if ($src === '') {
            Response::error(400, '该页还没有插图,先生成或上传一张');
        }
        $srcAbs = dirname(__DIR__, 3) . '/frontend/public/' . ltrim($src, '/');
        if (!is_file($srcAbs)) {
            Response::error(404, '插图文件不存在');
        }
        $safe = preg_replace('/[^\x{4e00}-\x{9fa5}A-Za-z0-9_-]/u', '', $name);
        $rel = sprintf('images/books/story%d/char_%s.png', $storyId, $safe);
        $abs = dirname(__DIR__, 3) . '/frontend/public/' . $rel;
        if (!is_dir(dirname($abs)) && !mkdir(dirname($abs), 0777, true)) {
            Response::error(500, '目录创建失败');
        }
        if (!copy($srcAbs, $abs)) {
            Response::error(500, '图片复制失败');
        }
        $chars = json_decode((string)($story['characters'] ?? ''), true) ?: [];
        $found = false;
        foreach ($chars as &$c) {
            if ((string)($c['name'] ?? '') === $name) {
                $c['ref_image'] = $rel;
                $found = true;
                break;
            }
        }
        unset($c);
        if (!$found) {
            $chars[] = ['name' => $name, 'role' => '', 'appearance' => '', 'ref_image' => $rel];
        }
        $stmt = Database::pdo()->prepare('UPDATE stories SET characters = ? WHERE id = ?');
        $stmt->execute([json_encode($chars, JSON_UNESCAPED_UNICODE), $storyId]);
        Response::ok(['characters' => $chars, 'ref_image' => $rel]);
    }

    /**
     * POST /api/ai/tts {story_id?, page_no?, text?}
     * 火山引擎 TTS 合成 MP3 落盘缓存;未配置/失败返回 path=null(前端回退浏览器语音)
     */
    public function tts(): void
    {
        $in = Request::json();
        $storyId = (int)($in['story_id'] ?? 0);
        $pageNo = (int)($in['page_no'] ?? 0);
        $text = trim((string)($in['text'] ?? ''));
        if ($text === '' && $storyId > 0 && $pageNo > 0) {
            $stmt = Database::pdo()->prepare('SELECT pages FROM stories WHERE id = ?');
            $stmt->execute([$storyId]);
            $row = $stmt->fetch();
            if ($row) {
                $pages = json_decode((string)$row['pages'], true) ?: [];
                foreach ($pages as $p) {
                    if ((int)($p['page_no'] ?? 0) === $pageNo) {
                        $text = (string)($p['text'] ?? '');
                        break;
                    }
                }
            }
        }
        if ($text === '') {
            Response::error(400, '请提供文本');
        }
        Response::ok(['path' => TtsService::synthesize($text, $storyId, $pageNo)]);
    }

    // ---------- 私有辅助 ----------

    // ---------- 私有辅助 ----------

    private function findMaterial(string $theme, string $age, ?int $materialId): ?array
    {
        $pdo = Database::pdo();
        if ($materialId !== null) {
            $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ? AND status='published'");
            $stmt->execute([$materialId]);
            return $stmt->fetch() ?: null;
        }
        $stmt = $pdo->prepare(
            "SELECT * FROM materials WHERE status='published' AND (name LIKE ? OR description LIKE ? OR tags LIKE ?) AND age_band = ? ORDER BY id LIMIT 1"
        );
        $like = "%{$theme}%";
        $stmt->execute([$like, $like, $like, $age]);
        $row = $stmt->fetch();
        // 年龄段不匹配时放宽(主题优先)
        if (!$row) {
            $stmt = $pdo->prepare(
                "SELECT * FROM materials WHERE status='published' AND (name LIKE ? OR description LIKE ? OR tags LIKE ?) ORDER BY id LIMIT 1"
            );
            $stmt->execute([$like, $like, $like]);
            $row = $stmt->fetch();
        }
        return $row ?: null;
    }

    private function findRecentDraft(string $theme, string $age, int $pageCount, string $style): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM stories WHERE theme = ? AND age_band = ? AND page_count = ? AND style = ? "
            . "AND source_type='ai' AND created_at > NOW() - INTERVAL 24 HOUR ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$theme, $age, $pageCount, $style]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function symbolTraceFor(string $name, string $theme): string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT name, symbol_trace FROM symbols WHERE status='published' AND (name LIKE ? OR description LIKE ?) LIMIT 3"
        );
        $like = "%{$theme}%";
        $stmt->execute([$like, $like]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return '';
        }
        return implode("\n", array_map(fn($r) => "{$r['name']}: {$r['symbol_trace']}", $rows));
    }

    private function parsePagesJson(string $content): ?array
    {
        $data = $this->parseJsonContent($content);
        if (!is_array($data) || !isset($data['pages']) || !is_array($data['pages'])) {
            return null;
        }
        $pages = [];
        foreach ($data['pages'] as $i => $p) {
            if (!is_array($p)) {
                return null;
            }
            $pages[] = [
                'page_no'      => (int)($p['page_no'] ?? $i + 1),
                'type'         => (string)($p['type'] ?? 'content'),
                'text'         => trim((string)($p['text'] ?? '')),
                'goal_text'    => trim((string)($p['goal_text'] ?? '')),
                'goal_domains' => is_array($p['goal_domains'] ?? null) ? $p['goal_domains'] : [],
            ];
        }
        return $pages;
    }

    private function parseJsonContent(string $content): ?array
    {
        $c = trim($content);
        // 剥 ```json ... ``` 围栏
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $c, $m)) {
            $c = trim($m[1]);
        }
        $data = json_decode($c, true);
        return is_array($data) ? $data : null;
    }

    /** AI 页面合并到拼装骨架:只替换文本与目标,保留 illus_prompt 等 */
    private function mergePages(array $skeleton, array $aiPages): array
    {
        $byNo = [];
        foreach ($aiPages as $p) {
            $byNo[(int)$p['page_no']] = $p;
        }
        foreach ($skeleton as &$pg) {
            $no = (int)$pg['page_no'];
            if (!isset($byNo[$no])) {
                continue;
            }
            $ai = $byNo[$no];
            if (($ai['text'] ?? '') !== '') {
                $pg['text'] = $ai['text'];
            }
            if (($ai['goal_text'] ?? '') !== '') {
                $pg['goal_text'] = $ai['goal_text'];
                $pg['goal_domains'] = $ai['goal_domains'];
            }
        }
        return $skeleton;
    }

    private function computeSummary(array $pages): array
    {
        $count = [];
        foreach ($pages as $pg) {
            foreach (($pg['goal_domains'] ?? []) as $d) {
                if ($d !== '') {
                    $count[$d] = ($count[$d] ?? 0) + 1;
                }
            }
        }
        $out = [];
        foreach ($count as $d => $c) {
            $out[] = ['domain' => $d, 'count' => $c];
        }
        return $out;
    }

    private function deriveTitle(string $theme, string $materialName): string
    {
        $short = str_replace('白族', '', $materialName);
        if (str_ends_with($short, '的传说') || str_ends_with($short, '的故事') || str_ends_with($short, '绘本')) {
            return $short;
        }
        return "{$short}的故事";
    }

    private function purposeFor(string $mode): string
    {
        return match ($mode) {
            'expand' => 'expand',
            'goal'   => 'goal_gen',
            'title'  => 'title_gen',
            default  => 'page_polish',
        };
    }

    /** 组装生图提示词:场景主体优先 + 英文标签 + 角色简述 + 精简风格后缀 */
    private function buildImagePrompt(array $story, array $page): string
    {
        $chars = json_decode((string)($story['characters'] ?? ''), true) ?: [];
        $parts = [];
        // 1) 场景主体放最前且关键词加权(SD 注意力偏向前段+高权重词,保证画面贴合故事)
        $en = trim((string)($page['illus_prompt_en'] ?? ''));
        if ($en !== '') {
            // 前 4 个道具/动作标签加权 1.3,其余原样
            $tags = array_map('trim', explode(',', $en));
            $weighted = [];
            foreach ($tags as $i => $t) {
                if ($t === '') {
                    continue;
                }
                $weighted[] = $i < 4 ? "({$t}:1.3)" : $t;
            }
            $parts[] = implode(', ', $weighted);
        } else {
            $scene = trim((string)($page['illus_prompt'] ?? ''));
            if ($scene !== '') {
                $parts[] = '画面主体:' . $scene;
            }
        }
        // 3) 角色简述(仅当配图重点含人物;focus=object 时不注入,避免 SD 硬画人)
        $focus = (string)($page['focus'] ?? '');
        $includePerson = $focus !== 'object';
        if ($includePerson) {
            foreach ($chars as $c) {
                if (!empty($c['name'])) {
                    $app = (string)($c['appearance'] ?? '');
                    $short = mb_substr($app, 0, 40);
                    $parts[] = trim((string)$c['name'] . ':' . $short);
                }
            }
            if ($chars) {
                $parts[] = '所有角色外观保持一致';
            }
            // 4) 名字歧义规避
            foreach ($chars as $c) {
                $n = (string)($c['name'] ?? '');
                if (in_array($n, ['小白', '小黑', '小黄', '阿黄', '小灰', '小花'], true)) {
                    $parts[] = "注意:{$n}是人类孩子,不是动物";
                }
            }
        }
        // 5) 风格后缀(纯风格)+ 含人物的页追加人物特征标签
        $parts[] = (string)Config::get('image.sd.style_suffix');
        if ($includePerson) {
            $parts[] = (string)Config::get('image.sd.person_suffix');
        }
        return implode(',', array_filter($parts, fn($s) => $s !== ''));
    }

    /**
     * 用 GLM 把中文配图需求翻译成 SD 英文标签 + 判断配图重点(人/物/两者,免费模型,每页只一次并缓存)
     * @return array{0:string,1:?string} [英文标签, focus: person|object|both|null]
     */
    private function ensureEnglishPrompt(array $story, array &$pages, int $idx, int $storyId): array
    {
        $hasCache = array_key_exists('illus_prompt_en', $pages[$idx])
            && $pages[$idx]['illus_prompt_en'] !== ''
            && array_key_exists('focus', $pages[$idx]);
        if ($hasCache) {
            return [(string)$pages[$idx]['illus_prompt_en'], (string)$pages[$idx]['focus']];
        }
        $scene = trim((string)($pages[$idx]['illus_prompt'] ?? ''));
        if ($scene === '' || !AiClient::available() || AiClient::usedToday() >= (int)Config::get('glm.daily_limit')) {
            return ['', null]; // 配额用尽时降级:用中文需求原文当提示词
        }
        $sys = '你是 Stable Diffusion 提示词专家。输入一句中文画面描述,只输出 JSON:'
            . '{"tags":"英文提示词标签,逗号分隔短语,包含画面中所有关键物品与动作",'
            . '"focus":"person或object或both"}。'
            . '格式要求(严格遵守):tags 里只允许出现英文单词短语,禁止出现中文、等号、冒号,'
            . '如 "knotted white fabric, dye vat, blue dye water, fabric soaking"。'
            . '判定规则(严格遵守):'
            . '1.focus 表示"配图重点":情节着重于事物本身(物品/景物/工艺细节)时填 object,'
            . '着重于人物的动作/情感时填 person,人物与事物同等重要时填 both;'
            . '2.focus 为 object 时,tags 只描述事物,不得添加任何人物标签,可加 close-up、object focus;'
            . '3.focus 为 person 或 both 时,人物特征统一为 "Chinese Bai minority child, black hair, black eyes, yellow skin";'
            . '4.即使描述中出现了人物词,若句子的重点/主语是事物本身(如"每个小朋友的扎染花纹都不一样"重点是花纹,'
            . '"白布放进染水里"重点是布和染缸),也必须判 object 并去掉人物标签;'
            . '5.人物只是"看风景、散步、路过"这类背景陪衬、且句子重点在景物描写时(如"小宇在洱海边看蓝蓝的湖水"重点在湖水),'
            . '必须判 object 并去掉人物标签;'
            . '只有当人物的动作、表情、情感互动是画面核心(如"小白把布捏起来""阿叔笑着给朵朵讲道理""拉钩约定")时才判 person 或 both。'
            . '示例 输入:"扎好的白布被轻轻放进蓝色染水里" 输出:{"tags":"knotted white fabric, dye vat, blue dye water, fabric soaking, close-up, object focus","focus":"object"}'
            . '示例 输入:"每个小朋友的扎染花纹都不一样" 输出:{"tags":"tie-dye fabric patterns, various blue white patterns, close-up, object focus","focus":"object"}';
        $res = AiClient::chat(
            [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $scene]],
            ['max_tokens' => 150, 'temperature' => 0.4, 'response_format' => ['type' => 'json_object']]
        );
        AiClient::log('prompt_translate', md5($scene), $res, 'story', $storyId);
        if (!$res['ok']) {
            return ['', null];
        }
        $parsed = $this->parseJsonContent($res['content']);
        $tags = trim((string)($parsed['tags'] ?? ''));
        $focus = (string)($parsed['focus'] ?? '');
        if (!in_array($focus, ['person', 'object', 'both'], true)) {
            $focus = '';
        }
        if ($tags === '') {
            return ['', null];
        }
        // 缓存到 pages JSON,一页只翻一次
        $pages[$idx]['illus_prompt_en'] = mb_substr($tags, 0, 300);
        if ($focus !== '') {
            $pages[$idx]['focus'] = $focus;
        }
        $stmt = Database::pdo()->prepare('UPDATE stories SET pages = ? WHERE id = ?');
        $stmt->execute([json_encode($pages, JSON_UNESCAPED_UNICODE), $storyId]);
        return [(string)$pages[$idx]['illus_prompt_en'], $focus !== '' ? $focus : null];
    }

    /** 若配置了 LoRA 且与 SD1.5 底模兼容则追加触发词;不兼容返回警告 */
    private function applyLoraIfCompat(string $prompt, array &$warnings): string
    {
        $loraTag = trim((string)Config::get('image.sd.lora'));
        if ($loraTag === '') {
            return $prompt;
        }
        [$ok, $arch] = SdWebuiClient::checkLoraCompat($loraTag);
        if ($ok) {
            return $prompt . ',' . $loraTag;
        }
        $warnings[] = sprintf('LoRA 架构不匹配(%s),已自动跳过——请在 liblib 选"基础模型:SD 1.5"的儿童绘本模型', $arch);
        return $prompt;
    }

    /** 事物为主模式下从英文标签中剔除人物词(不追加任何词,特写与否由翻译标签自己决定) */
    private function stripPersonTags(string $tags): string
    {
        $tags = str_ireplace(
            ['Chinese Bai minority child', 'black hair', 'black eyes', 'yellow skin', 'child,', 'children,', 'teacher,'],
            '', $tags);
        return trim(preg_replace('/,\s*,/', ',', $tags), ', ');
    }

    /** 取第一个有定妆照的角色的 base64(IP-Adapter 参考图),无则 null */
    private function characterRefImage(array $story): ?string
    {
        $chars = json_decode((string)($story['characters'] ?? ''), true) ?: [];
        foreach ($chars as $c) {
            $ref = (string)($c['ref_image'] ?? '');
            if ($ref === '') {
                continue;
            }
            $abs = dirname(__DIR__, 3) . '/frontend/public/' . ltrim($ref, '/');
            if (is_file($abs)) {
                $b64 = base64_encode((string)file_get_contents($abs));
                if ($b64 !== '') {
                    return $b64;
                }
            }
        }
        return null;
    }

    /** 下载远端文件(生图 URL),失败返回 null */
    private function downloadFile(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body === false ? null : (string)$body;
    }
}
