<?php
// Helper functions to load and save site settings (contact info and page content)
function get_site_settings() {
    $settings_file = __DIR__ . '/site_settings.json';
    if (!file_exists($settings_file)) {
        return [
            'contact' => [
                'phone' => '',
                'email' => '',
                'address' => '',
                'facebook' => ''
            ],
            'pages' => [
                'about' => '',
                'findus' => ''
            ]
        ];
    }
    $json = file_get_contents($settings_file);
    return json_decode($json, true);
}

function save_site_settings($settings) {
    $settings_file = __DIR__ . '/site_settings.json';
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
}
?>
