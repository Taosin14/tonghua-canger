# 童画苍洱 · 大理白族文化 AI 绘本工坊

高校赛道 AI+场景设计赛 · 智慧教育方向(非遗文化数字化 + 低龄 AI 教育普及)

面向大理乡村/民族幼儿园教师的 AI 绘本资源生成工具:**数据库优先,AI 辅助**。
故事内容以人工审核过的白族文化素材库为事实底座,智谱 GLM 大模型只做生成与润色;
AI 不可用(无 Key/断网/配额用尽)时自动降级为模板拼装,系统始终可用。

## 技术栈

- 前端:Vue3 + Vite + vue-router + pinia + axios(特效取自开源项目 cary-mao/small,MIT)
- 后端:PHP 8.1 原生 REST API(无框架,PSR-4 自动加载 + PDO 预编译)
- 数据库:MySQL 8.0 / MariaDB 10.4(utf8mb4,6 表:users/materials/symbols/stories/works/ai_logs)
- AI:智谱 GLM-4-Flash(OpenAI 兼容,免费);语音朗读用浏览器 speechSynthesis

## 目录

```
frontend/   Vue3 前端(Vite 构建)
backend/    PHP API(config.php 是唯一改配置的地方;db/ 下 SQL 按文件名序执行)
scripts/    数据导入脚本(import_xlsx.py / extract_book.py)
docs/       文档(设计方案/部署说明/测试用例等)
docker/     部署镜像(W4)
```

## 本地开发启动

前置:MySQL 已启动(本机 XAMPP 为 3307 端口,root/123456)、Node ≥ 18。

```bash
# 1. 后端(终端1)
cd backend
php -S 127.0.0.1:8080 -t public public/router.php

# 2. 前端(终端2)
cd frontend
npm install          # 首次
npm run dev          # http://localhost:5173(/api 自动代理到 8080)
```

## 数据库初始化

```bash
# 按序导入(首次;Docker 环境由 mysql 镜像自动执行)
mysql -u root -p < backend/db/01_schema.sql
mysql -u root -p < backend/db/02_seed_materials.sql
mysql -u root -p < backend/db/03_seed_symbols.sql
mysql -u root -p < backend/db/04_seed_stories.sql
mysql -u root -p < backend/db/05_seed_admin.sql
```

- 素材扩充后重跑:`D:\python311\python.exe scripts/import_xlsx.py && mysql ... < backend/db/02_seed_materials.sql`(按 name 幂等;导入脚本统一用 D:\python311,该环境已装 openpyxl)
- 新故事 HTML 导入:`D:\python311\python.exe scripts/extract_book.py 你的绘本.html`(通用导入器)

## 演示账号

| 账号 | 密码 | 角色 |
|---|---|---|
| admin | admin123 | 审核管理员(审核队列/AI日志报表/批量导入) |
| teacher | teacher123 | 教师(生成/编辑/提交审核) |

## 接入智谱 GLM(可选,不接也能跑)

1. 注册智谱开放平台 https://open.bigmodel.cn,创建 API Key(免费模型 glm-4-flash)
2. 填入 `backend/config.php` 的 `glm.api_key`(或设置环境变量 `GLM_API_KEY`)
3. 刷新工作台,「AI 配额」处显示"GLM 已接入";生成的绘本会打「✨ AI 生成」标记
4. 所有调用自动记录 ai_logs,后台可导出 CSV 作为大赛 AIGC 披露材料

## 核心流程

选主题 → 配参数(年龄段/页数/风格)→ 一键生成(拼装→GLM→draft 入库)
→ 编辑器修改/AI 润色 → 提交审核(pending)→ 管理员通过(published)→ 图书馆可见
→ 阅读器翻页 + 中文朗读 → 导出 PNG/PDF

## AIGC 与数据声明

- AI 生成内容一律 status=draft 入库,人工审核通过后才进入图书馆(文化准确性承诺)
- ai_logs 记录每次调用(含降级),CSV 可导出,满足大赛披露要求(详见 docs/与交付包 AIGC 披露)
- 特效素材来源 github.com/cary-mao/small(MIT)

### 图片与语音的 AI 使用(真实口径)

- **图片方针:AI 生图**。绘本插画全部由 AI 生成:主力为**自建 Stable Diffusion**(SD1.5 绘本底模 + 宫崎骏 LoRA + IP-Adapter 角色一致性),智谱 CogView 作降级备份。图片文件存放 `frontend/public/images/`、路径由数据库 `pages.illus` 字段统一管理,每次生成记 ai_logs(引擎/耗时),满足《AI 生成合成内容标识办法》披露要求
- **朗读**:主力为**自建 GPT-SoVITS 声音克隆**(参考音频来自开源儿童语音数据集),Edge TTS 作降级备份;音频按页落盘缓存
- 各引擎降级链与调用统计见 `AIGC使用披露.md`
