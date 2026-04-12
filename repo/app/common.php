<?php
// Application common functions

if (!function_exists('bootstrap_config')) {
    /**
     * Load bootstrap config value.
     * Reads from the dev-bootstrap generated config file.
     */
    function bootstrap_config(string $key = null, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $paths = [
                '/app/runtime/bootstrap/app_config.php',
                dirname(__DIR__) . '/runtime/bootstrap/app_config.php',
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $config = require $path;
                    break;
                }
            }
            if ($config === null) {
                $config = [];
            }
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}

if (!function_exists('generate_request_id')) {
    /**
     * Generate a unique request ID.
     */
    function generate_request_id(): string
    {
        return 'req-' . bin2hex(random_bytes(12));
    }
}
