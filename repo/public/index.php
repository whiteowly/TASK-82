<?php
// +----------------------------------------------------------------------
// | ThinkPHP Application Entry
// +----------------------------------------------------------------------

namespace think;

require __DIR__ . '/../vendor/autoload.php';

$http = (new App())->http;
$response = $http->run();
$response->send();
$http->end($response);
