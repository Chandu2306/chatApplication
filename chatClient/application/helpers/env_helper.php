<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('env')) {
    function env($key, $default = null) {
        static $env = null;

        if ($env === null) {
            $envPath = FCPATH . '.env';

            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    list($name, $value) = array_map('trim', explode('=', $line, 2));
                    $env[$name] = $value;
                }
            }
        }

        return isset($env[$key]) ? $env[$key] : $default;
    }
}
