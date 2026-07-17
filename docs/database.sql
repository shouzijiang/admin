-- 管理后台独立数据库
-- CREATE DATABASE IF NOT EXISTS `qianzhi_admin` DEFAULT CHARSET utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(32) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `role`       VARCHAR(16) NOT NULL DEFAULT 'admin',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员';

-- 默认管理员 admin / admin123
INSERT INTO `admin_users` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$FylNE3.dO4fdrgsD8fy6puAfqbSYR9XWoeGKoO5XmeMNSIC3pqgk.', 'superadmin')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- 操作日志表
CREATE TABLE IF NOT EXISTS `admin_operation_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id`    INT UNSIGNED NOT NULL COMMENT '操作管理员ID',
  `method`      VARCHAR(10) NOT NULL COMMENT 'HTTP方法',
  `path`        VARCHAR(255) NOT NULL COMMENT '请求路径',
  `module`      VARCHAR(50) NOT NULL DEFAULT '' COMMENT '模块名',
  `action`      VARCHAR(100) NOT NULL DEFAULT '' COMMENT '操作名称',
  `target`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作目标',
  `before_val`  TEXT COMMENT '变更前值(JSON)',
  `after_val`   TEXT COMMENT '变更后值(JSON)',
  `ip`          VARCHAR(45) DEFAULT '' COMMENT 'IP地址',
  `status`      VARCHAR(10) DEFAULT 'success' COMMENT 'success/fail',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_admin` (`admin_id`),
  KEY `idx_module` (`module`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理后台操作日志';
