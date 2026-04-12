<?php
/**
 * PHPUnit test bootstrap.
 * Loads the ThinkPHP application for testing.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load common helpers
require_once __DIR__ . '/../app/common.php';

// Initialize ThinkPHP so tests can use Db facade and other services directly
(new \think\App())->initialize();
