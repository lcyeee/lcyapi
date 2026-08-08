# New API PHP 版

基于纯 PHP + MySQL 实现的 AI 模型网关系统，参考 [new-api](https://github.com/Calcium-Ion/new-api) 行为设计。

## 特性

- OpenAI 兼容 API（chat/completions、embeddings、images、audio、models）
- 多渠道负载均衡（加权随机 + 优先级 + 失败降级）
- 用户额度计费（美元，models 表按 千 token 定价）
- API 令牌管理（sk- 前缀，哈希存储）
- 兑换码充值、使用/错误日志、CSV 导出
- 管理员后台 + 用户前台

## 目录

```
index.php             /v1/* API 统一入口路由
config.php            主配置（数据库/站点/安全/转发）
includes/             核心类（DB/Auth/Relay/Token/...）
api/v1/               OpenAI 兼容接口处理器
admin/                管理员后台
user/                 用户前台
sql/install.sql       建表脚本（10 张表）
sql/seed.sql          初始数据（管理员 admin/admin123）
nginx.conf            Nginx 配置示例
```

## 安装

1. 导入 `sql/install.sql` 与 `sql/seed.sql` 到 MySQL
2. 修改 `config.php` 中的数据库连接
3. 配置 Web 服务器（参考 `nginx.conf` 或 `.htaccess`）
4. 访问后台：`/admin`（默认账号 `admin` / `admin123`），首次登录后请立即修改密码

详细说明见 `docs/`，环境要求与部署步骤见 `INSTALL.md`。