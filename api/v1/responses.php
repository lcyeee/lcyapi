<?php
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}
Relay::handle('responses', 'responses');
