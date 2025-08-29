<?php
/**
 * Обработчик кеширования для AI ChatBot
 */
class AI_ChatBot_Cache_Handler {
    
    public function __construct() {
        add_action('wp_ajax_ai_chatbot_clear_cache', array($this, 'clear_cache'));
    }

    /**
     * Очистка кеша плагина
     */
    public function clear_cache() {
        check_ajax_referer('ai_chatbot_clear_cache', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав для выполнения операции');
            return;
        }

        try {
            // Очищаем transients
            delete_transient('ai_chatbot_settings');
            
            // Очищаем кеш объектов WordPress
            wp_cache_flush();
            
            // Очищаем кеш opcache если включен
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Обновляем версию стилей
            $current_version = get_option('ai_chatbot_styles_version', 1);
            update_option('ai_chatbot_styles_version', $current_version + 1);
            
            wp_send_json_success('Кеш успешно очищен');
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Получить версию стилей для предотвращения кеширования
     */
    public static function get_styles_version() {
        return get_option('ai_chatbot_styles_version', 1);
    }
}

// Инициализация обработчика кеша
new AI_ChatBot_Cache_Handler();
