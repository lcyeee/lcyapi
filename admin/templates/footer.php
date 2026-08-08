</div>
        </div>
    </div>
    <nav class="admin-tabbar">
        <a class="<?php echo admin_nav('/admin/index.php', $requestPath); ?>" href="<?php echo base_url('admin/index.php'); ?>"><?php echo svg_icon('home'); ?>控制台</a>
        <a class="<?php echo admin_nav('/admin/channels/', $requestPath); ?>" href="<?php echo base_url('admin/channels/index.php'); ?>"><?php echo svg_icon('channel'); ?>渠道</a>
        <a class="<?php echo admin_nav('/admin/logs/', $requestPath); ?>" href="<?php echo base_url('admin/logs/index.php'); ?>"><?php echo svg_icon('list'); ?>日志</a>
        <a class="<?php echo admin_nav('/admin/users/', $requestPath); ?>" href="<?php echo base_url('admin/users/index.php'); ?>"><?php echo svg_icon('users'); ?>用户</a>
        <a class="<?php echo admin_nav('/admin/settings/', $requestPath); ?>" href="<?php echo base_url('admin/settings/index.php'); ?>"><?php echo svg_icon('settings'); ?>设置</a>
    </nav>
</div>
</body>
</html>
