-- 管理后台独立数据库
-- CREATE DATABASE IF NOT EXISTS `pun_admin` DEFAULT CHARSET utf8mb4;

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
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin')
ON DUPLICATE KEY UPDATE `username` = `username`;
