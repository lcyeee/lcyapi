SET NAMES utf8mb4;

INSERT INTO users (username, email, password, nickname, role) VALUES
('admin', 'admin@example.com', '$2y$12$ehoNsZaWEpiIdIjn0ZbunO1rV3nrB.6C.DSp5SxURmEbCNk4jMOQ.', '管理员', 'admin')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO settings (`key`, value, type, description) VALUES
('site_name', 'New API', 'string', '站点名称'),
('site_description', 'AI 模型网关', 'string', '站点描述'),
('register_enabled', '1', 'bool', '是否开放注册'),
('default_quota', '0.0000', 'decimal', '新用户默认额度'),
('api_rate_limit', '60', 'int', 'API 每分钟请求上限'),
('api_rate_window', '60', 'int', 'API 限流窗口（秒）'),
('login_attempts', '5', 'int', '登录失败次数上限'),
('login_lock_time', '900', 'int', '登录锁定时间（秒）')
ON DUPLICATE KEY UPDATE `key` = `key`;

INSERT INTO models (name, display_name, input_price, output_price, type, sort) VALUES
('gpt-4o', 'GPT-4o', 0.002500, 0.010000, 'chat', 1),
('gpt-4o-mini', 'GPT-4o mini', 0.000150, 0.000600, 'chat', 2),
('gpt-3.5-turbo', 'GPT-3.5 Turbo', 0.000500, 0.001500, 'chat', 3),
('text-embedding-3-small', 'Embedding 3 small', 0.000020, 0.000020, 'embedding', 4)
ON DUPLICATE KEY UPDATE id = id;