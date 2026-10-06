<?php
namespace App\Services;

use App\Core\Config;

/**
 * 自建 Stable Diffusion WebUI(秋叶整合包)文生图客户端
 * 接口: POST {api_url}/sdapi/v1/txt2img,返回 base64 图片
 * 本机 RTX 5060 8GB:SD1.5 底模(GuoFeng3/绘本 LoRA)约 5-15 秒/张
 */
class SdWebuiClient
{
    /**
     * LoRA 架构预检:读 safetensors 元数据,判断是否与当前底模(SD1.5)兼容
     * 返回 [是否兼容, 架构描述];文件不存在视为兼容(由 SD 自行报错)
     */
    public static function checkLoraCompat(string $loraTag): array
    {
        // <lora:名字:权重> -> 名字
        if (!preg_match('/<lora:([^:>]+)(?::[^>]*)?>/', $loraTag, $m)) {
            return [true, 'unknown'];
        }
        $name = trim($m[1]);
        $loraDir = (string)(Config::get('image.sd.lora_dir') ?: 'D:/ai/sd-forge/models/Lora');
        $file = rtrim($loraDir, '/\\') . '/' . $name . '.safetensors';
        if (!is_file($file)) {
            return [true, 'not-found'];
        }
        $fp = fopen($file, 'rb');
        if ($fp === false) {
            return [true, 'unreadable'];
        }
        $lenBytes = fread($fp, 8);
        if ($lenBytes === false || strlen($lenBytes) < 8) {
            fclose($fp);
            return [true, 'bad-header'];
        }
        $hdrLen = unpack('P', $lenBytes)[1];
        $hdrJson = fread($fp, (int)$hdrLen);
        fclose($fp);
        $hdr = json_decode((string)$hdrJson, true) ?: [];
        $arch = (string)($hdr['__metadata__']['modelspec.architecture'] ?? '');
        $base = (string)($hdr['__metadata__']['ss_base_model_version'] ?? '');
        $desc = $arch !== '' ? $arch : $base;
        // SD1.5 架构的 LoRA 才与 GuoFeng3 兼容;flux/sdxl/pony/illustrious 等一律拒绝
        $incompatible = preg_match('/(flux|sdxl|pony|illustrious|xl)/i', $desc) === 1;
        return [$incompatible ? false : true, $desc !== '' ? $desc : 'unknown'];
    }

    /**
     * @param string|null $refImageB64 角色定妆照 base64(IP-Adapter 参考图,锁定人物长相)
     * @return array {ok, image(binary|null), error, latency_ms}
     */
    public static function txt2img(string $prompt, ?string $negative = null, ?string $refImageB64 = null): array
    {
        $cfg = Config::get('image.sd');
        $start = microtime(true);
        $payload = [
            'prompt'          => $prompt,
            'negative_prompt' => $negative ?? $cfg['negative_prompt'],
            'steps'           => (int)$cfg['steps'],
            'cfg_scale'       => (float)$cfg['cfg_scale'],
            'width'           => (int)$cfg['width'],
            'height'          => (int)$cfg['height'],
            'sampler_name'    => $cfg['sampler'],
        ];
        // IP-Adapter:用定妆照锁定角色长相(经 alwayson_scripts -> ControlNet 传参)
        // Forge 内置版约定:args 直接是 unit 字典列表,image 为裸 base64 字符串
        if ($refImageB64 !== null && $refImageB64 !== '') {
            $payload['alwayson_scripts'] = [
                'controlnet' => [
                    'args' => [[
                        'enabled'         => true,
                        'module'          => (string)($cfg['ipadapter_module'] ?? 'CLIP-ViT-H (IPAdapter)'),
                        'model'           => (string)($cfg['ipadapter_model'] ?? 'ip-adapter-plus_sd15'),
                        'weight'          => (float)($cfg['ipadapter_weight'] ?? 0.8),
                        'image'           => $refImageB64,
                        'resize_mode'     => 'Crop and Resize',
                        'control_mode'    => 'Balanced',
                        'guidance_start'  => 0,
                        'guidance_end'    => 1,
                        'pixel_perfect'   => true,
                    ]],
                ],
            ];
        }
        $ch = curl_init(rtrim($cfg['api_url'], '/') . '/sdapi/v1/txt2img');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        if ($body === false || $status !== 200) {
            return ['ok' => false, 'image' => null,
                    'error' => $err ?: ('HTTP ' . $status . '(SD WebUI 未启动?)'), 'latency_ms' => $latency];
        }
        $data = json_decode($body, true);
        $b64 = $data['images'][0] ?? null;
        if (!is_string($b64) || $b64 === '') {
            return ['ok' => false, 'image' => null, 'error' => 'SD 响应缺少 images[0]', 'latency_ms' => $latency];
        }
        $image = base64_decode($b64, true);
        if ($image === false || $image === '') {
            return ['ok' => false, 'image' => null, 'error' => '图片解码失败', 'latency_ms' => $latency];
        }
        return ['ok' => true, 'image' => $image, 'error' => '', 'latency_ms' => $latency];
    }
}
