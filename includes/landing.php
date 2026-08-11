<?php
/**
 * lcyapi 公开落地页（new-api 风格）
 * 依赖：bootstrap 已加载（setting/base_url/svg_icon/e/theme_head_scripts）
 */
$siteName = setting('site_name', config('site.name'));
$registerEnabled = setting('register_enabled', '1') === '1';
$landingPath = isset($_SERVER['REQUEST_URI']) ? rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
$loggedIn = Auth::check();
$primaryUrl = $loggedIn ? base_url('user/index.php') : base_url('user/login.php');
$primaryLabel = $loggedIn ? '进入控制台' : '登 录';

/* 实时运行数据（logs 实时聚合，空库时归零） */
$liveToday = date('Y-m-d');
$liveCalls = (int)DB::value('SELECT COUNT(*) FROM logs WHERE DATE(created_at) = ?', [$liveToday]);
$liveOk = (int)DB::value('SELECT COUNT(*) FROM logs WHERE status = 1 AND DATE(created_at) = ?', [$liveToday]);
$liveRate = $liveCalls > 0 ? round($liveOk / $liveCalls * 100, 1) : 0;
$liveChannels = (int)DB::value('SELECT COUNT(*) FROM channels WHERE status = 1');
$liveModels = (int)DB::value('SELECT COUNT(*) FROM models');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo e($siteName); ?> — 统一 AI API 网关</title>
<meta name="description" content="基于标准 OpenAI 兼容协议的统一 AI API 网关，支持渠道转发、令牌额度管理与多模型接入。">
<?php echo theme_head_scripts(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/css/home.css'); ?>">
</head>
<body>
<div class="landing">

    <!-- 顶栏（滚动收缩悬浮胶囊） -->
    <header class="pub-header">
        <div class="pub-nav-wrap">
            <nav class="pub-nav">
                <a class="pub-logo" href="<?php echo base_url('/'); ?>">
                    <span class="pub-logo-mark"><?php echo svg_icon('zap'); ?></span>
                    <span class="pub-logo-name"><?php echo e($siteName); ?></span>
                </a>
                <div class="pub-nav-center">
                    <a class="pub-nav-link <?php echo ($landingPath === '' || $landingPath === '/') ? 'active' : ''; ?>" href="<?php echo base_url('/'); ?>">首页</a>
                    <a class="pub-nav-link" href="#features">功能</a>
                    <a class="pub-nav-link" href="#how-it-works">上手</a>
                    <a class="pub-nav-link" href="<?php echo base_url('user/pricing/index.php'); ?>">价格</a>
                </div>
                <div class="pub-nav-right">
                    <button type="button" class="icon-btn pub-theme-toggle" data-theme-toggle title="切换明暗模式"><?php echo svg_icon('moon'); ?></button>
                    <span class="pub-nav-divider"></span>
                    <a class="pub-auth-btn" href="<?php echo e($primaryUrl); ?>"><?php echo e($primaryLabel); ?></a>
                    <button type="button" class="pub-burger" aria-label="菜单">
                        <div class="bars"><span></span><span></span><span></span></div>
                    </button>
                </div>
            </nav>
        </div>
    </header>

    <!-- 移动端全屏菜单 -->
    <div class="pub-mobile">
        <nav class="pub-mobile-links">
            <a class="pub-mobile-link <?php echo ($landingPath === '' || $landingPath === '/') ? 'active' : ''; ?>" href="<?php echo base_url('/'); ?>">首页 <?php echo svg_icon('chevron'); ?></a>
            <a class="pub-mobile-link" href="#features">功能 <?php echo svg_icon('chevron'); ?></a>
            <a class="pub-mobile-link" href="#how-it-works">上手 <?php echo svg_icon('chevron'); ?></a>
            <a class="pub-mobile-link" href="<?php echo base_url('user/pricing/index.php'); ?>">价格 <?php echo svg_icon('chevron'); ?></a>
        </nav>
        <a class="pub-mobile-cta" href="<?php echo e($primaryUrl); ?>"><?php echo e($primaryLabel); ?></a>
    </div>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-grid"></div>
        <div class="hero-inner">
            <div class="hero-head landing-fade-up">
                <div class="hero-pill"><span class="dot"></span>AI 应用基础设施 · 开箱即用</div>
                <h1 class="hero-title">
                    统一 API 网关<br>
                    <span class="gradient-text">接入海量 AI 模型</span>
                </h1>
                <p class="hero-desc">
                    基于标准、统一的 API 协议接入数百种模型。一键管理渠道、令牌与额度，
                    分钟级完成 AI 应用的对接交付。
                </p>
                <div class="hero-cta">
                    <a class="hero-btn hero-btn-primary" href="<?php echo e($primaryUrl); ?>">
                        <?php echo e($primaryLabel); ?><?php echo svg_icon('arrow'); ?>
                    </a>
                    <a class="hero-btn hero-btn-outline" href="<?php echo base_url('user/pricing/index.php'); ?>">查看价格</a>
                </div>
            </div>

            <!-- 实时运行数据（logs 实时聚合） -->
            <div class="hero-live landing-fade-up" style="--d:140ms">
                <div class="live-card">
                    <span class="live-value"><?php echo number_format($liveCalls); ?></span>
                    <span class="live-label">今日 API 调用</span>
                </div>
                <div class="live-card">
                    <span class="live-value"><?php echo e($liveRate); ?>%</span>
                    <span class="live-label">今日请求成功率</span>
                </div>
                <div class="live-card">
                    <span class="live-value"><?php echo number_format($liveChannels); ?></span>
                    <span class="live-label">在线渠道</span>
                </div>
                <div class="live-card">
                    <span class="live-value"><?php echo number_format($liveModels); ?></span>
                    <span class="live-label">支持模型</span>
                </div>
            </div>

            <!-- 终端演示 + 客户端 -->
            <div class="hero-demo landing-fade-up" style="--d:220ms">
                <div class="term-card" id="termDemo">
                    <div class="term-tabs">
                        <button type="button" class="term-tab">Chat</button>
                        <button type="button" class="term-tab">Responses</button>
                        <button type="button" class="term-tab">Claude</button>
                        <button type="button" class="term-tab">Gemini</button>
                        <span class="term-status"><span class="dot"></span>200 ok</span>
                    </div>
                    <div class="term-endpoint">
                        <span class="term-method">POST</span>
                        <span class="term-url">/v1/chat/completions</span>
                    </div>
                    <div class="term-body">
                        <div class="term-section term-request">
                            <span class="term-section-label">Request</span>
                            <div class="code-scroll"><pre></pre></div>
                        </div>
                        <div class="term-section term-response">
                            <span class="term-section-label">Response</span>
                            <div class="code-scroll"><pre></pre></div>
                        </div>
                    </div>
                    <div class="term-metrics">
                        <div class="nums">
                            <span><b data-metric="latency">142</b>&nbsp;ms</span>
                            <span class="sep"></span>
                            <span><b data-metric="tokens">27</b>&nbsp;tokens</span>
                            <span class="sep"></span>
                            <span>cost <b data-metric="cost">$0.00081</b></span>
                        </div>
                        <span class="term-sse">stream · sse</span>
                    </div>
                </div>
                <div class="hero-apps">
                    <span class="hero-apps-label">支持一键配置</span>
                    <div class="hero-apps-list">
                        <a class="app-chip" href="https://cherry-ai.com" target="_blank" rel="noopener noreferrer"><?php echo svg_icon('message'); ?>Cherry Studio</a>
                        <a class="app-chip" href="https://ccswitch.io" target="_blank" rel="noopener noreferrer"><?php echo svg_icon('zap'); ?>CC Switch</a>
                        <span class="app-chip more"><?php echo svg_icon('activity'); ?>更多应用</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 数据条带 -->
    <section class="home-stats">
        <div class="home-stats-inner">
            <div class="home-stat">
                <span class="home-stat-num" data-count="50" data-suffix="+">0+</span>
                <span class="home-stat-label">上游渠道类型接入</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-num" data-count="100" data-suffix="+">0+</span>
                <span class="home-stat-label">模型计费支持</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-num" data-count="50" data-suffix="+">0+</span>
                <span class="home-stat-label">兼容 API 路由</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-num" data-count="10" data-suffix="+">0+</span>
                <span class="home-stat-label">调度与限制策略</span>
            </div>
        </div>
    </section>

    <!-- 特性 -->
    <section class="home-features" id="features">
        <span class="section-kicker landing-fade-up">Features</span>
        <h2 class="section-title landing-fade-up" style="--d:60ms">为 AI 应用打造的网关</h2>
        <div class="features-grid">
            <div class="feat-card landing-fade-up" style="--d:80ms">
                <div class="feat-head"><span class="feat-num">01</span></div>
                <div class="feat-title-row"><span class="feat-icon" style="color:#3B82F6;"><?php echo svg_icon('zap'); ?></span><span class="feat-title">快如闪电</span></div>
                <p class="feat-desc">智能路由与渠道亲和，毫秒级响应，稳定支撑高并发 AI 调用。</p>
                <div class="feat-chips">
                    <span class="feat-chip">OpenAI</span><span class="feat-chip">Claude</span><span class="feat-chip">Gemini</span>
                    <span class="feat-chip">DeepSeek</span><span class="feat-chip">Qwen</span><span class="feat-chip">Llama</span>
                </div>
            </div>
            <div class="feat-card landing-fade-up" style="--d:140ms">
                <div class="feat-head"><span class="feat-num">02</span></div>
                <div class="feat-title-row"><span class="feat-icon" style="color:#16A34A;"><?php echo svg_icon('shield'); ?></span><span class="feat-title">安全可靠</span></div>
                <p class="feat-desc">SSRF 防护、敏感词过滤、2FA 与令牌额度管控，企业级安全防护。</p>
                <div class="feat-shield">
                    <div class="feat-shield-box">
                        <?php echo svg_icon('shield'); ?>
                        <span class="feat-shield-check"><?php echo svg_icon('check'); ?></span>
                    </div>
                </div>
            </div>
            <div class="feat-card landing-fade-up" style="--d:200ms">
                <div class="feat-head"><span class="feat-num">03</span></div>
                <div class="feat-title-row"><span class="feat-icon" style="color:#8B5CF6;"><?php echo svg_icon('globe'); ?></span><span class="feat-title">全球覆盖</span></div>
                <p class="feat-desc">多区域部署，结合负载均衡与用量追踪，保障全球稳定访问。</p>
                <div class="feat-steps">
                    <div class="feat-step"><span class="feat-step-num">1</span><span class="line"></span><span>负载均衡</span></div>
                    <div class="feat-step hl"><span class="feat-step-num">2</span><span class="line"></span><span>限流控制</span></div>
                    <div class="feat-step"><span class="feat-step-num">3</span><span class="line"></span><span>成本追踪</span></div>
                </div>
            </div>
            <div class="feat-card landing-fade-up" style="--d:260ms">
                <div class="feat-head"><span class="feat-num">04</span></div>
                <div class="feat-title-row"><span class="feat-icon" style="color:#F59E0B;"><?php echo svg_icon('code'); ?></span><span class="feat-title">开发者友好</span></div>
                <p class="feat-desc">完全兼容 OpenAI / Claude / Gemini 等协议，一套代码全端接入。</p>
                <div class="feat-avatars">
                    <div class="feat-avatar-stack">
                        <span class="feat-avatar">API</span><span class="feat-avatar">SDK</span><span class="feat-avatar">CLI</span><span class="feat-avatar">Docs</span>
                    </div>
                    <span class="feat-avatar-tip">标准 RESTful 接口，即插即用</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 三步上手 -->
    <section class="hiw" id="how-it-works">
        <div class="hiw-inner">
            <span class="section-kicker landing-fade-up">How It Works</span>
            <h2 class="section-title landing-fade-up" style="--d:60ms">三步开始使用</h2>
            <div class="hiw-grid">
                <div class="hiw-step landing-fade-up" style="--d:80ms">
                    <div class="hiw-icon-wrap">
                        <div class="hiw-icon"><?php echo svg_icon('settings'); ?></div>
                        <span class="hiw-num">1</span>
                    </div>
                    <h3 class="hiw-step-title">配置渠道</h3>
                    <p class="hiw-step-desc">添加你的上游 API Key，配置渠道与访问权限。</p>
                </div>
                <div class="hiw-step landing-fade-up" style="--d:160ms">
                    <div class="hiw-icon-wrap">
                        <div class="hiw-icon"><?php echo svg_icon('key'); ?></div>
                        <span class="hiw-num">2</span>
                    </div>
                    <h3 class="hiw-step-title">创建令牌</h3>
                    <p class="hiw-step-desc">创建专属 API 令牌，设定额度与模型范围。</p>
                </div>
                <div class="hiw-step landing-fade-up" style="--d:240ms">
                    <div class="hiw-icon-wrap">
                        <div class="hiw-icon"><?php echo svg_icon('send'); ?></div>
                        <span class="hiw-num">3</span>
                    </div>
                    <h3 class="hiw-step-title">开始调用</h3>
                    <p class="hiw-step-desc">通过 OpenAI 兼容接口开始调用，实时用量与费用追踪。</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <div class="cta-bg"></div>
        <div class="cta-inner landing-scale-in">
            <h2 class="cta-title">准备好开始<br><span class="gradient-text">你的 AI 集成了吗？</span></h2>
            <p class="cta-desc">部署你自己的网关，把你的渠道接入统一 API，一分钟上手。</p>
            <div class="cta-btns">
                <a class="hero-btn hero-btn-primary" href="<?php echo e($primaryUrl); ?>">
                    <?php echo e($primaryLabel); ?><?php echo svg_icon('arrow'); ?>
                </a>
                <a class="hero-btn hero-btn-outline" href="<?php echo base_url('user/pricing/index.php'); ?>">查看价格</a>
            </div>
        </div>
    </section>

    <!-- 页脚 -->
    <footer class="pub-footer">
        <div class="pub-footer-inner">
            <div class="pub-footer-main">
                <div class="pub-footer-brand">
                    <a class="pub-footer-logo" href="<?php echo base_url('/'); ?>">
                        <span class="pub-logo-mark"><?php echo svg_icon('zap'); ?></span>
                        <span class="pub-footer-logo-name"><?php echo e($siteName); ?></span>
                    </a>
                    <p class="pub-footer-tagline">强大的 AI API 管理与转发平台，让每个应用都能接入大模型。</p>
                </div>
                <div class="pub-footer-cols">
                    <div>
                        <p class="pub-footer-col-title">功能</p>
                        <div class="pub-footer-col">
                            <a class="pub-footer-link" href="<?php echo base_url('user/pricing/index.php'); ?>">模型价格</a>
                            <a class="pub-footer-link" href="<?php echo base_url('user/login.php'); ?>">登录</a>
                            <?php if ($registerEnabled) : ?>
                                <a class="pub-footer-link" href="<?php echo base_url('user/register.php'); ?>">注册</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <p class="pub-footer-col-title">相关项目</p>
                        <div class="pub-footer-col">
                            <a class="pub-footer-link" href="https://github.com/QuantumNous/new-api" target="_blank" rel="noopener noreferrer">new-api</a>
                            <a class="pub-footer-link" href="https://github.com/songquanpeng/one-api" target="_blank" rel="noopener noreferrer">one-api</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pub-footer-bottom">
                <div class="pub-footer-legal">
                    <span>&copy; <?php echo date('Y'); ?> <?php echo e($siteName); ?> · 保留所有权利</span>
                </div>
                <span class="pub-footer-attr">界面设计参考 new-api 开源项目 · 本文档仅作内容展示</span>
            </div>
        </div>
    </footer>

</div>
<script src="<?php echo base_url('assets/js/home.js'); ?>"></script>
</body>
</html><?php exit; ?>