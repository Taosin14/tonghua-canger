<?php
namespace App\Services;

use App\Core\{Config, Database};

/**
 * 智谱 GLM 客户端(OpenAI 兼容)
 * 任何异常都返回 ok=false,由调用方降级到模板拼装 —— 系统永不因 AI 不可用而瘫痪。
 */
class AiClient
{
    public static function available(): bool
    {
        return Config::get('glm.api_key') !== '';
    }

    /**
     * @param array $messages OpenAI 格式消息
     * @param array $opts     覆盖 payload 的选项(如 response_format)
     * @return array {ok, content, usage, http_status, error, latency_ms}
     */
    public static function chat(array $messages, array $opts = []): array
    {
        $cfg = Config::get('glm');
        $payload = array_merge([
            'model'       => $cfg['model'],
            'messages'    => $messages,
            'temperature' => 0.8,
            'max_tokens'  => 1500,
        ], $opts);

        $last = ['ok' => false, 'content' => null, 'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
                 'http_status' => null, 'error' => 'no attempt', 'latency_ms' => 0];
        for ($attempt = 0; $attempt < 2; $attempt++) {
            [$ok, $body, $status, $err, $latency] = self::request($cfg['base_url'] . '/chat/completions', $payload, $cfg);
            $last = [
                'ok' => false, 'content' => null,
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
                'http_status' => $status ?: null, 'error' => $err ?: '', 'latency_ms' => $latency,
            ];
            if (!$ok || $status !== 200) {
                continue; // 重试一次
            }
            $data = json_decode($body, true);
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (!is_string($content)) {
                $last['error'] = '响应缺少 choices[0].message.content';
                continue;
            }
            $last['ok'] = true;
            $last['content'] = $content;
            $last['usage'] = [
                'prompt_tokens'     => (int)($data['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int)($data['usage']['completion_tokens'] ?? 0),
            ];
            break;
        }
        return $last;
    }

    /** 写 ai_logs(成功/降级/失败都记,供成本报表与 AIGC 披露);$statusOverride 可强制 fallback;$modelOverride 记录自建引擎 */
    public static function log(string $purpose, string $promptHash, array $result, ?string $targetType = null, ?int $targetId = null, ?string $statusOverride = null, ?string $modelOverride = null): void
    {
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO ai_logs (purpose,model,prompt_hash,input_tokens,output_tokens,latency_ms,http_status,status,error_msg,target_type,target_id) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $purpose,
                $modelOverride ?? Config::get('glm.model'),
                $promptHash !== '' ? $promptHash : null,
                (int)($result['usage']['prompt_tokens'] ?? 0),
                (int)($result['usage']['completion_tokens'] ?? 0),
                (int)($result['latency_ms'] ?? 0),
                $result['http_status'] ?? null,
                $statusOverride ?? ($result['ok'] ? 'success' : 'error'),
                $result['error'] !== '' ? mb_substr((string)$result['error'], 0, 490) : null,
                $targetType,
                $targetId,
            ]);
        } catch (\Throwable $e) {
            // 日志失败不影响主流程
        }
    }

    /**
     * 智谱 CogView 文生图(与 GLM 同一把 Key)
     * @return array {ok, url, error, latency_ms}
     */
    public static function generateImage(string $prompt): array
    {
        $cfg = Config::get('glm');
        if (!self::available()) {
            return ['ok' => false, 'url' => null, 'error' => '未配置 GLM_API_KEY', 'latency_ms' => 0,
                    'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0], 'http_status' => null];
        }
        $start = microtime(true);
        $ch = curl_init($cfg['base_url'] . '/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'  => $cfg['image_model'],
                'prompt' => $prompt,
                'size'   => $cfg['image_size'],
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['api_key'],
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90, // 生图较慢
            CURLOPT_CONNECTTIMEOUT => (int)$cfg['connect_timeout'],
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $base = ['ok' => false, 'url' => null, 'error' => $err ?: '', 'latency_ms' => $latency,
                 'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0], 'http_status' => $status ?: null];
        if ($body === false || $status !== 200) {
            $base['error'] = $err ?: ('HTTP ' . $status . ' ' . mb_substr((string)$body, 0, 200));
            return $base;
        }
        $data = json_decode($body, true);
        $url = $data['data'][0]['url'] ?? null;
        if (!is_string($url) || $url === '') {
            $base['error'] = '生图响应缺少 data[0].url';
            return $base;
        }
        $base['ok'] = true;
        $base['url'] = $url;
        return $base;
    }

    /** 今日成功调用数(配额判断) */
    public static function usedToday(): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM ai_logs WHERE status='success' AND created_at >= CURDATE()"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /** @return array{0:bool,1:string,2:?int,3:string,4:int} [ok, body, httpStatus, curlError, latencyMs] */
    private static function request(string $url, array $payload, array $cfg): array
    {
        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['api_key'],
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
            CURLOPT_CONNECTTIMEOUT => (int)$cfg['connect_timeout'],
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        if ($body === false) {
            return [false, '', $status > 0 ? $status : null, $err, $latency];
        }
        return [true, (string)$body, $status, $err, $latency];
    }
}
