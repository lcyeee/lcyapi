# lcyapi

基于纯 PHP + MySQL 实现的 OpenAI 兼容 AI 模型网关。无 Composer、无第三方框架、无前端构建，开箱即用。

## 特性

- **OpenAI 兼容 API**：chat/completions（含 SSE 流式）、completions、embeddings、images/generations、audio/transcriptions、audio/speech、models
- **多渠道负载均衡**：优先级 + 权重随机选渠道、失败自动重试（可配置次数）、失败次数达阈值自动停用渠道
- **按 token 计费**：模型输入/输出单价（每 1M token），精确到 6 位小数（DECIMAL(14,6)），图片/语音固定价
- **渠道模型云端获取**：编辑页一键拉取上游 /v1/models，按类型分组（对话/嵌入/重排/图像/语音）点选，支持通配符
- **用户系统**：注册/登录、每日签到、邀请返利、余额告警、令牌 IP 白名单
- **令牌管理**：sk- 前缀、哈希存储、独立额度、创建即得完整 key
- **管理后台**：仪表板图表、渠道/用户/模型/令牌/日志/错误/兑换码/审计/设置管理、CSV 导出
- **iOS 风格 UI**：CSS 变量设计系统、明暗主题引擎（预设/自定义取色）、磨砂弹窗组件、移动端底部导航
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
index.php             入口（未安装→安装向导；已安装→前台）
install.php           Web 安装向导
api/v1/               OpenAI 兼容接口处理器
includes/             核心类（DB/Auth/Relay/Channel/Token/Billing/...）
admin/                管理员后台
user/                 用户前台
assets/               样式/脚本（本地 Chart.js、主题引擎、弹窗组件）
sql/install.sql       建表脚本（12 张表）
tools/                开发工具（mock 上游、故障模拟、dev-server）
nginx.conf / .htaccess Web 服务器伪静态示例
```

## 部署注意事项

- Nginx 需合并 `nginx.conf` 的伪静态规则（/v1 try_files + 保护 data/includes/sql + `fastcgi_buffering off` 保 SSE）；Apache 使用自带 `.htaccess`
- 安装完成后建议删除 `install.php`
- 修改渠道/设置后如有缓存可清理 `data/cache/`（渠道缓存 5 分钟、设置缓存 60 秒）

## 开发

```bash
# 开发服务器（PHP 8.2，需 pdo_mysql/curl/mbstring）
php -S 127.0.0.1:8000 -t . -d display_errors=1 -d error_reporting=E_ALL

# mock 上游（模拟 OpenAI 多端点）
php -S 127.0.0.1:9001 tools/mock-router.php
```

## License

[MIT](LICENSE)
