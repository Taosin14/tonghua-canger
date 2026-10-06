-- 初始账号(密码为 bcrypt 哈希,由 PHP password_hash 生成)
-- admin / admin123  (管理员:审核、报表、导入)
-- teacher / teacher123 (教师:生成、编辑、提交审核)
USE canger;
SET NAMES utf8mb4;
INSERT INTO users (username, password_hash, role, display_name) VALUES
('admin', '$2y$10$MJ38o.MBZiSBZOkzSgytnu5IHsOt0yxycJjmjTiMpJn7sy6pf/9ZO', 'admin', '审核管理员'),
('teacher', '$2y$10$VMyYoYZ0/ZCS6HLLzlEp1utOsNNCPYWD/19O4AF/6KxgFV2yAA3jS', 'teacher', '示范教师')
ON DUPLICATE KEY UPDATE role=VALUES(role);
