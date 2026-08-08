</main>
        </div>
    </div>
</div>
<footer class="site-footer">
    <?php echo e(setting('site_name', config('site.name'))); ?> - <?php echo e(setting('site_description', config('site.description'))); ?>
</footer>
<nav class="user-tabbar">
    <a class="<?php echo nav_active('/user/index.php', $requestPath); ?>" href="<?php echo base_url('user/index.php'); ?>"><?php echo svg_icon('home'); ?>首页</a>
    <a class="<?php echo nav_active('/user/tokens/', $requestPath); ?>" href="<?php echo base_url('user/tokens/index.php'); ?>"><?php echo svg_icon('key'); ?>令牌</a>
    <a class="<?php echo nav_active('/user/logs/', $requestPath); ?>" href="<?php echo base_url('user/logs/index.php'); ?>"><?php echo svg_icon('list'); ?>记录</a>
    <a class="<?php echo nav_active('/user/wallet/', $requestPath); ?>" href="<?php echo base_url('user/wallet/index.php'); ?>"><?php echo svg_icon('wallet'); ?>钱包</a>
    <a class="<?php echo nav_active('/user/profile/', $requestPath); ?>" href="<?php echo base_url('user/profile/index.php'); ?>"><?php echo svg_icon('user'); ?>我的</a>
</nav>
</body>
</html>
