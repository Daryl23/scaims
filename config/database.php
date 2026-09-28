<?php

/**
 * Central, framework-free database configuration.
 *
 * Environment variables are read only when conn.php is first loaded. Existing
 * SCAIMS_DB_* constants still take precedence so the disposable test routers
 * and older deployment entry points remain compatible.
 */

if (!function_exists('scaims_env_value')) {
    function scaims_env_value(string $name, string $fallback): string
    {
        $value = getenv($name);
        return $value === false ? $fallback : $value;
    }
}

if (!function_exists('scaims_database_config')) {
    function scaims_database_config(): array
    {
        $password = getenv('SCAIMS_DB_PASSWORD');
        if ($password === false) {
            // SCAIMS_DB_PASS is accepted as a compatibility alias.
            $password = scaims_env_value('SCAIMS_DB_PASS', '');
        }

        $portValue = scaims_env_value('SCAIMS_DB_PORT', '3306');
        if (!preg_match('/^[0-9]+$/D', $portValue)) {
            throw new RuntimeException('Invalid database port configuration.');
        }

        $port = (int) $portValue;
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid database port configuration.');
        }

        $config = [
            'host' => trim(scaims_env_value('SCAIMS_DB_HOST', 'localhost')),
            // Preserve the current XAMPP database name as the compatibility
            // default. New installations should set SCAIMS_DB_NAME explicitly.
            'name' => trim(scaims_env_value('SCAIMS_DB_NAME', 'u330456998_apgorrsdb')),
            'user' => scaims_env_value('SCAIMS_DB_USER', 'root'),
            'password' => (string) $password,
            'port' => $port,
        ];

        if ($config['host'] === '' || $config['name'] === '' || $config['user'] === '') {
            throw new RuntimeException('Incomplete database configuration.');
        }

        return $config;
    }
}

