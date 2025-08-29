<?php
/**
 * Класс для управления ресурсами плагина
 */
class AI_ChatBot_Assets {
    
    /**
     * Инициализация
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Подключение стилей и скриптов для фронтенда
     */
    public function enqueue_frontend_assets() {
        // Получаем версию стилей для предотвращения кеширования
        $styles_version = AI_ChatBot_Cache_Handler::get_styles_version();
        
        wp_enqueue_style(
            'ai-chatbot-styles',
            plugins_url('assets/css/ai-chatbot.css', AI_CHATBOT_PLUGIN_FILE),
            array(),
            $styles_version
        );

        wp_enqueue_script(
            'ai-chatbot-script',
            plugins_url('assets/js/ai-chatbot.js', AI_CHATBOT_PLUGIN_FILE),
            array('jquery'),
            $styles_version,
            true
        );

        // Передаем настройки в JavaScript
        wp_localize_script('ai-chatbot-script', 'aiChatbotSettings', array(
            'primaryColor' => get_option('ai_chatbot_primary_color', '#667eea'),
            'secondaryColor' => get_option('ai_chatbot_secondary_color', '#764ba2'),
            'botNameColor' => get_option('ai_chatbot_bot_name_color', '#000000'),
            'margin' => get_option('ai_chatbot_margin', 20),
            'colorScheme' => get_option('ai_chatbot_color_scheme', 'default'),
            'inactivity_timeout' => intval(get_option('ai_chatbot_inactivity_timeout', 300000))
        ));
    }

    /**
     * Подключение стилей и скриптов для админки
     */
    public function enqueue_admin_assets($hook) {
        if ('settings_page_ai-chatbot-settings' !== $hook) {
            return;
        }

        $styles_version = AI_ChatBot_Cache_Handler::get_styles_version();
        
        wp_enqueue_style(
            'ai-chatbot-admin-styles',
            plugins_url('assets/css/ai-chatbot-admin.css', AI_CHATBOT_PLUGIN_FILE),
            array(),
            $styles_version
        );

        wp_enqueue_media();
        
        wp_enqueue_script(
            'ai-chatbot-admin-script',
            plugins_url('assets/js/ai-chatbot-admin.js', AI_CHATBOT_PLUGIN_FILE),
            array('jquery'),
            $styles_version,
            true
        );
    }
}

// Инициализация управления ресурсами
new AI_ChatBot_Assets();
