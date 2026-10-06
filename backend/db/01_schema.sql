-- ============================================================
-- 童画苍洱 数据库结构 (MySQL 8.0 / MariaDB 10.4 双兼容)
-- 执行顺序: 01_schema -> 02_seed_materials -> 03_seed_symbols
--           -> 04_seed_stories -> 05_seed_admin
-- ============================================================
CREATE DATABASE IF NOT EXISTS canger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE canger;
SET NAMES utf8mb4;

-- 用户(登录与审核角色)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(32) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  token CHAR(32) NULL,
  role ENUM('teacher','reviewer','admin') NOT NULL DEFAULT 'teacher',
  display_name VARCHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username),
  UNIQUE KEY uk_users_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 素材底座(生成时唯一事实来源)
CREATE TABLE IF NOT EXISTS materials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  material_type VARCHAR(20) NOT NULL COMMENT '民间故事/非遗工艺/建筑场景/人物角色/节日民俗/童谣儿歌',
  name VARCHAR(100) NOT NULL,
  age_band VARCHAR(10) NOT NULL DEFAULT '4-5' COMMENT '3-4/4-5/5-6',
  description TEXT NULL,
  image_prompt TEXT NULL,
  edu_goals JSON NULL COMMENT '[{"domain":"社会","goal":"..."}]',
  tags JSON NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  reviewed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_materials_name (name),
  KEY idx_materials_type_age (material_type, age_band),
  KEY idx_materials_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文化符号库(symbol_trace 为文化溯源核心字段)
CREATE TABLE IF NOT EXISTS symbols (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  category VARCHAR(20) NOT NULL DEFAULT '其他' COMMENT '建筑/服饰/饮食/工艺/节日/人物/自然/音乐/其他',
  description TEXT NULL,
  symbol_trace TEXT NULL COMMENT '起源/流变/地理身份',
  source VARCHAR(255) NULL,
  image_prompt TEXT NULL,
  aliases JSON NULL,
  related_materials JSON NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_symbols_name (name),
  KEY idx_symbols_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 故事库(绘本库;pages 整本存 JSON)
CREATE TABLE IF NOT EXISTS stories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(120) NOT NULL,
  subtitle VARCHAR(120) NOT NULL DEFAULT '',
  theme VARCHAR(30) NOT NULL DEFAULT '',
  material_id INT UNSIGNED NULL,
  age_band VARCHAR(10) NOT NULL DEFAULT '4-5',
  page_count TINYINT UNSIGNED NOT NULL DEFAULT 8,
  style VARCHAR(30) NOT NULL DEFAULT '水彩',
  cover_illus VARCHAR(255) NOT NULL DEFAULT '',
  pages JSON NOT NULL,
  edu_goals_summary JSON NULL,
  symbol_ids JSON NULL,
  characters JSON NULL COMMENT '[{"name","role","appearance"}] 角色设定卡,生图提示词注入用',
  source_type ENUM('seed','ai','user') NOT NULL DEFAULT 'user',
  ai_generated TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','pending','published','rejected') NOT NULL DEFAULT 'draft',
  review_note VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stories_theme_age (theme, age_band),
  KEY idx_stories_status (status),
  KEY idx_stories_source (source_type),
  CONSTRAINT fk_stories_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 教师作品(课件/环创/导出记录)
CREATE TABLE IF NOT EXISTS works (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_type ENUM('book','courseware','artwork') NOT NULL DEFAULT 'book',
  title VARCHAR(120) NOT NULL DEFAULT '',
  params JSON NULL,
  content JSON NULL,
  story_id INT UNSIGNED NULL,
  status ENUM('draft','done') NOT NULL DEFAULT 'draft',
  exported_formats JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_works_type_time (work_type, created_at),
  CONSTRAINT fk_works_story FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI 调用日志(成本控制 + 大赛 AIGC 披露)
CREATE TABLE IF NOT EXISTS ai_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purpose VARCHAR(30) NOT NULL DEFAULT 'story_generate' COMMENT 'story_generate/page_polish/goal_gen/title_gen/expand',
  model VARCHAR(40) NOT NULL DEFAULT '',
  prompt_hash CHAR(32) NULL,
  input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
  http_status SMALLINT UNSIGNED NULL,
  status ENUM('success','fallback','error') NOT NULL DEFAULT 'success' COMMENT 'fallback=降级拼装(计费0)',
  error_msg VARCHAR(500) NULL,
  target_type VARCHAR(20) NULL,
  target_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ailogs_ph (prompt_hash, created_at),
  KEY idx_ailogs_purpose (purpose, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
