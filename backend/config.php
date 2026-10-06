<?php
/**
 * 全局配置 —— 部署时只改这里,或通过环境变量覆盖(环境变量优先)
 * 环境变量: DB_HOST DB_PORT DB_NAME DB_USER DB_PASS GLM_API_KEY GLM_MODEL GLM_BASE_URL
 * 本地开发默认值 = XAMPP(MariaDB @3307, root/123456)
 * 私密密钥(API Key 等)放同目录 config.local.php(已 gitignore),结构与本文件相同,自动覆盖
 */
$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3307),
        'name' => getenv('DB_NAME') ?: 'canger',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '123456',
    ],
    'glm' => [
        'api_key'     => getenv('GLM_API_KEY') !== false ? getenv('GLM_API_KEY') : '',
        'base_url'    => getenv('GLM_BASE_URL') ?: 'https://open.bigmodel.cn/api/paas/v4',
        'model'       => getenv('GLM_MODEL') ?: 'glm-4-flash',
        'image_model' => getenv('GLM_IMAGE_MODEL') ?: 'cogview-3-flash',
        'image_size'  => getenv('GLM_IMAGE_SIZE') ?: '1024x1024',
        'timeout'     => 60,   // 秒(cURL total)
        'connect_timeout' => 5,
        'daily_limit' => 100,  // 每日 AI 调用上限(控制成本)
    ],
    // 生图引擎: sdwebui = 自建 Stable Diffusion(秋叶整合包,无水印/角色一致);glm = 智谱 CogView
    'image' => [
        'provider' => getenv('IMAGE_PROVIDER') ?: 'sdwebui',
        'sd' => [
            'api_url'        => getenv('SD_API_URL') ?: 'http://127.0.0.1:7860',
            'steps'          => (int)(getenv('SD_STEPS') ?: 30),
            'cfg_scale'      => (float)(getenv('SD_CFG') ?: 5.0),
            'width'          => (int)(getenv('SD_WIDTH') ?: 768),
            'height'         => (int)(getenv('SD_HEIGHT') ?: 768),
            'sampler'        => getenv('SD_SAMPLER') ?: 'DPM++ 2M Karras',
            'timeout'        => 180,
            // 儿童绘本 LoRA 触发词(liblib.art 下载后填,如 '<lora:children_book2:0.8>')
            // 注意:必须是 SD 1.5 架构(页面标注"基础模型"),FLUX/SDXL 的加载不上
            'lora'           => getenv('SD_LORA') ?: '',
            'lora_dir'       => getenv('SD_LORA_DIR') ?: 'D:/ai/sd-forge/models/Lora',
            // IP-Adapter 角色一致性参数(定妆照机制)
            'ipadapter_module' => getenv('SD_IPADAPTER_MODULE') ?: 'ip-adapter_clip_sd15',
            'ipadapter_model'  => getenv('SD_IPADAPTER_MODEL') ?: 'ip-adapter-plus_sd15',
            'ipadapter_weight' => (float)(getenv('SD_IPADAPTER_WEIGHT') ?: 0.8),
            // 纯风格后缀(严禁出现人物词——否则每页都会被强制画人)
            'style_suffix'   => 'children\'s book illustration, pastel colors, crayon drawing, cute, best quality',
            // 人物特征标签(仅含人物的页面注入);构图约束:远景背影小人物,不露正脸
            'person_suffix'  => 'Chinese Bai minority child, black hair, black eyes, yellow skin, Bai ethnic clothing, small figure in distance, back view or side view, face not visible, scenery dominant',
            'negative_prompt' => 'lowres, bad anatomy, bad hands, extra fingers, deformed face, watermark, text, logo, 西方人, 外国人, 金发, 蓝眼睛, 狗, 小狗, 动物, tiny person, giant objects, person inside container, person in water, drowning, underwater, bad scale, huge head, huge face, horse face, long face, close-up face, portrait',
        ],
    ],
    // 绘本朗读语音合成
    // provider=edge:微软 Edge 免费接口,童声"晓伊",零成本免注册
    // provider=sovits:自建 GPT-SoVITS 声音克隆(录参考音频克隆童声),失败自动回退 edge
    // provider=volcano:火山引擎(个人账号只有标准版女声)
    'tts' => [
        'provider'     => getenv('TTS_PROVIDER') ?: 'edge',
        'edge_voice'   => getenv('TTS_EDGE_VOICE') ?: 'zh-CN-XiaoyiNeural',
        'python'       => getenv('PYTHON_BIN') ?: 'python',
        // GPT-SoVITS API(花佬一键包 / 官方 api.py,默认 9880 端口)
        'sovits_api'         => getenv('SOVITS_API') ?: 'http://127.0.0.1:9880',
        'sovits_ref_audio'   => getenv('SOVITS_REF_AUDIO') ?: '',
        'sovits_prompt_text' => getenv('SOVITS_PROMPT_TEXT') ?: '',
        'app_id'       => getenv('TTS_APP_ID') !== false ? getenv('TTS_APP_ID') : '',
        'access_token' => getenv('TTS_ACCESS_TOKEN') !== false ? getenv('TTS_ACCESS_TOKEN') : '',
        'cluster'      => 'volcano_tts',
        'voice'        => getenv('TTS_VOICE') ?: 'BV700_streaming',
        'base_url'     => 'https://openspeech.bytedance.com/api/v1/tts',
        'timeout'      => 30,
    ],
    'cors_origin' => '*',
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $config = array_replace_recursive($config, require $localFile);
}
return $config;
