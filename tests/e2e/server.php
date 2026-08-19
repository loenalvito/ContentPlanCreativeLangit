<?php

$publicPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require $publicPath.DIRECTORY_SEPARATOR.'index.php';
