<?php
/**
 * Authorization Password Configuration
 * Stores a site-wide authorization password hash and metadata
 */

class AuthorizationConfig {
    private static $config = null;

    public static function getConfig() {
        if (self::$config === null) {
            self::$config = self::loadConfig();
        }
        return self::$config;
    }

    private static function loadConfig() {
        $configFile = __DIR__ . '/../config/authorization_config.json';

        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        } else {
            $config = [
                'enabled' => true,
                'password_hash' => '',
                'last_updated_by' => '',
                'last_updated_at' => null
            ];
            self::saveConfig($config);
        }

        return $config;
    }

    public static function saveConfig($config) {
        $configDir = __DIR__ . '/../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents(
            $configDir . '/authorization_config.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
        self::$config = $config;
    }

    public static function setPassword($plainPassword, $updatedBy) {
        $config = self::getConfig();
        $config['password_hash'] = password_hash($plainPassword, PASSWORD_DEFAULT);
        $config['last_updated_by'] = $updatedBy;
        $config['last_updated_at'] = date('Y-m-d H:i:s');
        self::saveConfig($config);
        return ['success' => true];
    }
}
?>