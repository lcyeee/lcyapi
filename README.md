# lcyapi

基于纯 PHP + MySQL 实现的 OpenAI 兼容 AI 模型网关（AI API 分发与计费平台）。无 Composer、无第三方框架、无前端构建，全部手写，开箱即用。

聚合 30+ 渠道类型（OpenAI / Anthropic / Gemini / Azure / 国内厂商 / Ollama / 自定义兼容网关等）到统一 OpenAI 兼容 API 之后，提供用户体系、令牌管理、按 token 计费、订阅、兑换码、邀请返利、在线支付等一整套前后台能力，功能对齐 new-api。

## 特性

### API 与转发
- **OpenAI 兼容端点**：`/v1/chat/completions`（含 SSE 流式）、`/v1/completions`、`/v1/embeddings`、`/v1/images/generations|edits`、`/v1/audio/transcriptions|translations|speech`、`/v1/models`、`/v1/models/detail`、`/v1/moderations`、`/v1/dashboard/billing/*` 等
- **多协议端口**：Claude Messages（`/v1/messages`）、Responses API（`/v1/responses`、`/v1/responses/compact`）、Rerank（`/v1/rerank`）、Gemini `v1beta` 格式、OpenAI ⇄ Claude ⇄ Gemini 双向格式互转（含流式/工具调用/思考块）
- **MJ-Proxy 兼容路由**：`/mj`（Midjourney）、`/suno/submit/:action`（Suno），通用异步任务框架（tasks 表状态机 + 定时轮询 + 预扣/差额结算/退款）
- **渠道负载均衡**：优先级 + 权重随机、失败自动重试（可配置）、失败阈值/关键词/状态码自动禁用渠道、渠道亲和（同用户同模型固定上次成功渠道）、多 Key 轮询、渠道定期测试
- **云端模型获取**：编辑页一键拉取上游 `/v1/models`，按类型分组点选入库
- **格式转换与扩展**：模型映射、附加请求头、令牌模型限额、敏感词过滤、SSRF 防护、请求体大小限制

### 计费与运营
- **按 token 计费**：模型输入/输出单价（每 1M token），`DECIMAL(14,6)` 精度，图片/音频固定价；分组倍率（渠道组/用户组/令牌组、充值倍率）、模型价格预设库一键导入
- **订阅套餐**：额度池优先扣费（钱包溢出回退）、周期重置（日/周/月/自定义）、限购次数、升级/降级分组快照、订阅在线支付下单、幂等扣费（subscription_billing）
- **用户体系**：注册/登录、邮箱验证+域名白名单、找回密码（SMTP）、TOTP 2FA+恢复码+失败锁定、登录会话管理、OAuth（GitHub/Telegram/Discord/LinuxDO/OIDC/微信）、每日签到（随机奖励+连续奖励）、邀请返利+被邀请人列表、余额告警、自用模式
- **令牌管理**：sk- 前缀、哈希存储、独立额度、IP 白名单、auto 分组
- **在线支付**：易支付 / Stripe / Creem / Waffo，`pay_orders` 订单表 + 回调验签 + 充值倍率入账
- **运营管理后台**：仪表盘图表、渠道/用户/模型/令牌/日志/错误/兑换码/审计/登录日志/分组/订阅/任务管理、排行榜/性能指标/用量统计、Uptime Kuma、OAuth 绑定、2FA 统计、公开页编辑、系统占用、会话管理
- **系统能力**：定时任务（cron.php：日志清理/兑换码清理/订阅重置/任务轮询/渠道测试等）、通知系统（Email/Webhook/Bark/Gotify + 频控）、系统实例心跳、渠道费用排行

### 界面与体验
- **iOS 风格全站 UI**：CSS 变量设计系统、明暗主题引擎（预设/自定义取色）、磨砂弹窗组件、移动端底部导航+抽屉侧边栏
- **Playground**：用户中心/后台网页聊天测试页（流式直连本地端点验证转发链路）
- **Web 安装向导**：首次访问自动引导配置数据库与管理员账号

## 环境要求

- PHP ≥ 7.4（推荐 8.0+），需 pdo_mysql / curl / mbstring 扩展
- MySQL 5.7 / 8.0（utf8mb4）
- 转发依赖 curl 出站，需允许对外 HTTP 请求

## 快速开始

1. 将源码部署到站点根目录，保证 `data/` 目录可写
2. 浏览器访问站点根路径，自动进入安装向导，填写数据库信息与管理账号
3. 安装完成后进入后台，添加渠道（如 OpenAI、Anthropic 等兼容网关，可指向本地 mock）与模型
4. 后台「令牌」页创建 API 令牌，即可按 OpenAI 格式调用：

```bash
curl -X POST http://your-host/v1/chat/completions \
  -H "Authorization: Bearer sk-xxxx" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o","messages":[{"role":"user","content":"你好"}]}'
```

## 目录结构

```
├── index.php              # 入口（未安装→安装向导；已安装→前台）
├── install.php            # Web 安装向导
├── api/v1/                # OpenAI 兼容接口处理器（chat/embeddings/images/audio/models/messages/responses/rerank/moderations/dashboard/billing/mj/suno/v1beta…）
├── api/pay/               # 支付回调（epay_notify/stripe_webhook）
├── includes/              # 核心类库（DB/Auth/Relay/Channel/Token/Billing/Converter/Subscription/Mailer/TOTP/OAuth/PayOrder/TaskWorker…）
├── admin/                 # 管理员后台
├── user/                  # 用户前台
├── assets/                # 样式/脚本（本地 Chart.js、主题引擎、弹窗组件）
├── sql/install.sql        # 建表脚本（26 张表）
├── tools/                 # 开发工具（mock 上游、故障模拟、dev-server、cron 定时任务）
├── data/                  # 运行时数据（日志/缓存，git 已忽略）
├── nginx.conf / .htaccess # Web 服务器伪静态示例
```

## 部署注意事项

- Nginx 需合并 `nginx.conf` 的伪静态规则（/v1 try_files + 保护 data/includes/sql + `fastcgi_buffering off` 保 SSE）；Apache 使用自带 `.htaccess`
- 安装完成后建议删除 `install.php`
- 修改渠道/设置后如有缓存可清理 `data/cache/`（渠道缓存 5 分钟、设置缓存 60 秒）
- 上 HTTPS（OpenAI SDK 通常要求）

## 开发

```bash
# 开发服务器（PHP 8.2/8.4，需 pdo_mysql/curl/mbstring）
php -S 127.0.0.1:8000 -t . -d display_errors=1 -d error_reporting=E_ALL

# mock 上游（模拟 OpenAI 多端点）
php -S 127.0.0.1:9001 tools/mock-router.php

# 故障模拟（测试渠道容错）
php -S 127.0.0.1:9002 tools/fail-server.php
```

## 协议

本项目采用 [GNU Affero General Public License v3.0](LICENSE)（AGPL-3.0）开源。适用于网络服务场景的 copyleft 许可证：对运行该服务的用户开放其对应的修改版本源码。