<?php
namespace App\Services;

use App\Core\Config;

/**
 * 语音朗读服务:火山引擎 TTS 合成 MP3 落盘缓存,未配置/失败时返回 null(前端回退浏览器语音)
 * 缓存策略:每页一段,文件存在即直接复用,零重复成本;断网演示时已缓存的音频照常播放
 */
class TtsService
{
    /** @return string|null 合成音频的相对路径(如 audio/story1/p2.mp3);不可用返回 null */
    public static function synthesize(string $text, int $storyId, int $pageNo): ?string
    {
        $clean = self::cleanText($text);
        if ($clean === '') {
            return null;
        }
        $rel = sprintf('audio/story%d/p%d.mp3', $storyId, $pageNo);
        $file = self::audioRoot() . sprintf('/story%d/p%d.mp3', $storyId, $pageNo);
        if (is_file($file) && filesize($file) > 0) {
            return $rel; // 已缓存
        }
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0777, true)) {
            return null;
        }
        $cfg = Config::get('tts');
        if ($cfg['provider'] === 'sovits') {
            // 自建声音克隆优先,失败回退 edge,再失败回退浏览器
            $ok = self::sovitsSynthesize($clean, $file, $cfg) || self::edgeSynthesize($clean, $file, $cfg);
        } elseif ($cfg['provider'] === 'volcano') {
            $ok = self::volcanoSynthesize($clean, $file, $cfg);
        } else {
            $ok = self::edgeSynthesize($clean, $file, $cfg);
        }
        return $ok ? $rel : null;
    }

    /**
     * GPT-SoVITS 声音克隆 API(默认 9880 端口):
     * POST {sovits_api}/tts,ref_audio_path 为固定参考音频(录一段童声)
     * 响应 {code:0, data:[{data: base64 mp3}]}
     */
    private static function sovitsSynthesize(string $text, string $file, array $cfg): bool
    {
        if (empty($cfg['sovits_ref_audio'])) {
            return false; // 未配置参考音频
        }
        $payload = [
            'text'             => $text,
            'text_lang'        => 'zh',
            'ref_audio_path'   => $cfg['sovits_ref_audio'],
            'prompt_lang'      => 'zh',
            'prompt_text'      => $cfg['sovits_prompt_text'] ?? '',
            'text_split_method' => 'cut5',
            'media_type'       => 'wav', // api_v2 只支持 wav/raw/srt
            'streaming_mode'   => false,
        ];
        $ch = curl_init(rtrim($cfg['sovits_api'], '/') . '/tts');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body === false || strlen((string)$body) < 1000) {
            return false;
        }
        // api_v2 直接返回音频字节(RIFF=wav / ID3=mp3)
        if (str_starts_with((string)$body, 'RIFF') || str_starts_with((string)$body, 'ID3')) {
            return file_put_contents($file, $body) !== false;
        }
        // 兼容旧版 JSON 格式 {code:0, data:[{data:base64}]}
        $data = json_decode((string)$body, true);
        $b64 = $data['data'][0]['data'] ?? null;
        if (!is_string($b64) || $b64 === '') {
            return false;
        }
        $audio = base64_decode($b64, true);
        if ($audio === false || strlen($audio) < 1000) {
            return false;
        }
        return file_put_contents($file, $audio) !== false;
    }

    /**
     * 微软 Edge 朗读接口(免费童声"晓伊"):调用 edge-tts Python CLI 合成
     * 命令行全程用 ASCII 路径(临时目录),避免 Windows 中文路径编码问题,
     * 合成完成后再 rename 到目标目录(PHP 内部处理 Unicode 路径无碍)
     */
    private static function edgeSynthesize(string $text, string $file, array $cfg): bool
    {
        $python = $cfg['python'] ?? 'python';
        $tmpDir = sys_get_temp_dir();
        $tmpText = tempnam($tmpDir, 'canger_tts_');
        $tmpMp3 = $tmpDir . '/canger_tts_' . md5($file . microtime()) . '.mp3';
        if ($tmpText === false || file_put_contents($tmpText, $text) === false) {
            return false;
        }
        $cmd = sprintf(
            '%s -m edge_tts --voice %s --rate=-8%% --file %s --write-media %s 2>nul',
            escapeshellarg($python),
            escapeshellarg($cfg['edge_voice']),
            escapeshellarg($tmpText),
            escapeshellarg($tmpMp3)
        );
        @shell_exec($cmd);
        @unlink($tmpText);
        if (!is_file($tmpMp3) || filesize($tmpMp3) < 1000) {
            @unlink($tmpMp3);
            return false;
        }
        $ok = @rename($tmpMp3, $file);
        if (!$ok) {
            @unlink($tmpMp3);
        }
        return $ok;
    }

    /** 火山引擎 TTS(需 AppID/Token;未配置返回 false) */
    private static function volcanoSynthesize(string $text, string $file, array $cfg): bool
    {
        if (empty($cfg['app_id']) || empty($cfg['access_token'])) {
            return false;
        }
        $payload = [
            'app'     => ['appid' => $cfg['app_id'], 'token' => $cfg['access_token'], 'cluster' => $cfg['cluster']],
            'user'    => ['uid' => 'canger'],
            'audio'   => ['voice_type' => $cfg['voice'], 'encoding' => 'mp3', 'speed_ratio' => 0.9],
            'request' => ['reqid' => uniqid('canger', true), 'text' => $text, 'operation' => 'query'],
        ];
        $ch = curl_init($cfg['base_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body === false) {
            return false;
        }
        $data = json_decode($body, true);
        if (($data['code'] ?? -1) !== 3000 || empty($data['data'])) {
            return false;
        }
        $audio = base64_decode($data['data'], true);
        if ($audio === false || $audio === '') {
            return false;
        }
        return file_put_contents($file, $audio) !== false;
    }

    /** 朗读原文清洗:去 markdown 标记;换行按"行尾无标点补句号"处理,防止连读 */
    private static function cleanText(string $text): string
    {
        $t = preg_replace('/[*#`>~]/u', '', $text);
        $lines = preg_split('/\r?\n/', (string)$t);
        $out = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line === '') {
                continue;
            }
            // 行尾没有标点则补句号(绘本每行基本是一句)
            if (!preg_match('/[。！？!?，,；;：:…—]$/u', $line)) {
                $line .= '。';
            }
            $out[] = $line;
        }
        return implode(' ', $out);
    }

    /** 音频落盘根目录(前端 public/audio,随静态站一起部署) */
    private static function audioRoot(): string
    {
        return dirname(__DIR__, 3) . '/frontend/public/audio';
    }
}
