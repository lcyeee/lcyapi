<?php
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 3) . '/includes/bootstrap.php';
}
$contentType = isset($_SERVER['CONTENT_TYPE']) ? (string)$_SERVER['CONTENT_TYPE'] : 'application/json';
Relay::handle('images/edits', 'image', 'openai', null, $contentType);
