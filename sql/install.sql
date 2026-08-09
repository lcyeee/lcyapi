SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checkin_streak INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续签到天数',
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    email_verified TINYINT NOT NULL DEFAULT 0 COMMENT '邮箱已验证',
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(50) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    quota DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    used_quota DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    total_quota DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    status TINYINT NOT NULL DEFAULT 1,
    totp_secret VARCHAR(64) DEFAULT NULL COMMENT 'TOTP 密钥(Base32)',
    totp_enabled TINYINT NOT NULL DEFAULT 0 COMMENT '已开启2FA',
    backup_codes TEXT DEFAULT NULL COMMENT '2FA备份码(哈希JSON)',
    api_count INT UNSIGNED NOT NULL DEFAULT 0,
    aff_code VARCHAR(16) DEFAULT NULL COMMENT '我的邀请码',
    aff_by INT UNSIGNED DEFAULT NULL COMMENT '邀请人用户ID',
    aff_quota DECIMAL(14,6) NOT NULL DEFAULT 0.000000 COMMENT '待转入的邀请收益',
    aff_history_quota DECIMAL(14,6) NOT NULL DEFAULT 0.000000 COMMENT '累计邀请收益',
    `group` VARCHAR(32) NOT NULL DEFAULT 'default' COMMENT '用户分组',
    last_login_at DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username),
    UNIQUE KEY uk_email (email),
    UNIQUE KEY uk_aff_code (aff_code),
    KEY idx_status (status),
    KEY idx_aff_by (aff_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'openai',
    base_url VARCHAR(255) NOT NULL,
    api_key TEXT NOT NULL,
    api_keys TEXT DEFAULT NULL COMMENT '多 Key JSON 数组，转发时随机选取',
    tags VARCHAR(255) DEFAULT NULL COMMENT '标签，逗号分隔',
    models TEXT DEFAULT NULL,
    balance DECIMAL(14,6) DEFAULT NULL COMMENT '渠道剩余额度(USD)，NULL=不限',
    weight INT UNSIGNED NOT NULL DEFAULT 1,
    priority INT NOT NULL DEFAULT 0,
    `group` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '服务分组，逗号分隔，空=全部',
    model_mapping TEXT DEFAULT NULL COMMENT '模型映射 JSON：{目标模型:上游模型}',
    extra_headers TEXT DEFAULT NULL COMMENT '附加请求头 JSON',
    status TINYINT NOT NULL DEFAULT 1,
    success_count INT UNSIGNED NOT NULL DEFAULT 0,
    fail_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_use_at DATETIME DEFAULT NULL,
    remark VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    `key` VARCHAR(64) NOT NULL,
    hash VARCHAR(64) NOT NULL,
    remain_quota DECIMAL(14,6) NOT NULL DEFAULT -1.000000,
    used_quota DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    `group` VARCHAR(32) NOT NULL DEFAULT 'default' COMMENT '令牌分组', 
    model_limits TEXT DEFAULT NULL COMMENT '模型额度限制 JSON：{模型:单次最大token}',
    auto_groups VARCHAR(255) DEFAULT NULL COMMENT '自动分组列表，逗号分隔',
    status TINYINT NOT NULL DEFAULT 1,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    expired_at DATETIME DEFAULT NULL,
    allow_ips VARCHAR(500) DEFAULT NULL COMMENT 'IP白名单，逗号分隔，空=不限',
    last_used_at DATETIME DEFAULT NULL,
    last_used_ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_key (`key`),
    KEY idx_hash (hash),
    KEY idx_user (user_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL COMMENT '模型描述',
    tags VARCHAR(255) DEFAULT NULL COMMENT '标签，逗号分隔',
    input_price DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
    output_price DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
    cache_input_price DECIMAL(10,6) NOT NULL DEFAULT -1.000000 COMMENT '缓存命中输入价(US$/1M, -1=与input_price相同)',
    context_length INT UNSIGNED NOT NULL DEFAULT 4096,
    max_output INT UNSIGNED NOT NULL DEFAULT 2048,
    type ENUM('chat','completion','embedding','image','audio') NOT NULL DEFAULT 'chat',
    enabled TINYINT NOT NULL DEFAULT 1,
    sort INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_name (name),
    KEY idx_enabled (enabled),
    KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_id INT UNSIGNED NOT NULL,
    channel_id INT UNSIGNED NOT NULL,
    model VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'chat',
    prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    cost DECIMAL(12,6) NOT NULL DEFAULT 0.000000,
    duration INT UNSIGNED NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    error_msg TEXT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    request_body TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_token (token_id),
    KEY idx_channel (channel_id),
    KEY idx_model (model),
    KEY idx_created (created_at),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS error_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    channel_id INT UNSIGNED DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    type VARCHAR(50) DEFAULT NULL,
    message TEXT NOT NULL,
    request_data TEXT DEFAULT NULL,
    response_data TEXT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at),
    KEY idx_type (type),
    KEY idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redemptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    quota DECIMAL(14,6) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    used_by INT UNSIGNED DEFAULT NULL,
    used_at DATETIME DEFAULT NULL,
    used_ip VARCHAR(45) DEFAULT NULL,
    batch VARCHAR(50) DEFAULT NULL,
    remark VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_code (code),
    KEY idx_status (status),
    KEY idx_batch (batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) NOT NULL PRIMARY KEY,
    value TEXT DEFAULT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'string',
    description VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    username VARCHAR(50) NOT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    status TINYINT NOT NULL,
    reason VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_ip (ip),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recharge_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,6) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'redeem',
    redemption_id INT UNSIGNED DEFAULT NULL,
    operator_id INT UNSIGNED DEFAULT NULL,
    remark VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_type (type),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    reward DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_date (user_id, checkin_date),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    target VARCHAR(100) DEFAULT NULL,
    detail TEXT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin (admin_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'email' COMMENT 'email/forgot',
    code VARCHAR(32) NOT NULL,
    used TINYINT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email (email, type),
    KEY idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    sid_hash VARCHAR(64) NOT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    device VARCHAR(100) DEFAULT NULL,
    last_active_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sid (sid_hash),
    KEY idx_user (user_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_affinity (
    user_id INT UNSIGNED NOT NULL,
    model VARCHAR(100) NOT NULL,
    channel_id INT UNSIGNED NOT NULL,
    pinned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, model),
    KEY idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_bindings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    provider VARCHAR(20) NOT NULL COMMENT 'github/telegram',
    openid VARCHAR(255) NOT NULL,
    username VARCHAR(50) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_provider_openid (provider, openid),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pay_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(40) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    provider VARCHAR(20) NOT NULL DEFAULT 'epay' COMMENT 'epay/stripe',
    amount DECIMAL(14,6) NOT NULL,
    quota DECIMAL(14,6) NOT NULL COMMENT '实际入账额度=金额×充值倍率',
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/paid/failed/closed',
    prepay_id VARCHAR(255) DEFAULT NULL,
    pay_url TEXT DEFAULT NULL,
    transaction_id VARCHAR(255) DEFAULT NULL,
    raw_data TEXT DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_order_no (order_no),
    KEY idx_user (user_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    quota DECIMAL(14,6) NOT NULL DEFAULT 0.000000 COMMENT '周期额度',
    price DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    days INT UNSIGNED NOT NULL DEFAULT 30 COMMENT '有效期天数',
    status TINYINT NOT NULL DEFAULT 1,
    sort INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1有效 0过期',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'clean_logs/close_expired_orders/expire_subscriptions/clean_verifications',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
    `interval` INT UNSIGNED NOT NULL DEFAULT 3600 COMMENT '执行间隔秒',
    last_run_at DATETIME DEFAULT NULL,
    last_result VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_tasks (name, type, status, `interval`) VALUES
('清理请求日志', 'clean_logs', 1, 86400),
('清理已用验证码', 'clean_verifications', 1, 86400),
('关闭超时支付订单', 'close_expired_orders', 1, 1800),
('过期订阅标记', 'expire_subscriptions', 1, 3600),
('清理过期会话', 'expire_sessions', 1, 86400),
('渠道健康检查', 'auto_health', 1, 3600),
('上游模型自动同步', 'sync_upstream_models', 1, 86400),
('清理过期令牌', 'clean_expired_tokens', 1, 86400);

CREATE TABLE IF NOT EXISTS system_instances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    node_name VARCHAR(50) NOT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    last_heartbeat DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_node (node_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_url VARCHAR(255) DEFAULT '',
    api_key TEXT,
    description TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deployments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    model VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255) DEFAULT '',
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prefill_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'channel',
    data TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;