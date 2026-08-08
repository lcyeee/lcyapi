<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::logout();
redirect(base_url('admin/login.php'));