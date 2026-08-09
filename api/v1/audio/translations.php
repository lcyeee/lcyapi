<?php
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 3) . '/includes/bootstrap.php';
}
Relay::handle('audio/translations', 'audio');
