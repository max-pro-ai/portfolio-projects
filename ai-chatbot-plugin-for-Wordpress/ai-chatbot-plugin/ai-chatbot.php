<?php
/**
 * Plugin Name: AI ChatBot Assistant
 * Description: Умный чат-бот консультант с интеграцией OpenAI GPT
 * Version: 1.0.0
 * Author: Your Name
 */

// Предотвращение прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Определение констант
define('AI_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_CHATBOT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Подключаем обработчик email
require_once AI_CHATBOT_PLUGIN_PATH . 'includes/email-handler.php';

// Подключаем AJAX обработчики
require_once AI_CHATBOT_PLUGIN_PATH . 'includes/ajax-handlers.php';

// Подключаем класс обработчика чата
require_once AI_CHATBOT_PLUGIN_PATH . 'includes/class-chat-handler.php';

class AIChatBot {
    
    private $chat_handler;
    
    public function __construct() {
        // Инициализируем обработчик чата
        $this->chat_handler = new AI_ChatBot_Chat_Handler();
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_chat_widget'));
        add_action('wp_ajax_ai_chatbot_save_realtime_settings', array($this, 'handle_realtime_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        // Инициализация плагина
    }
    
    public function activate() {
        // Установка значений по умолчанию
        add_option('ai_chatbot_openai_key', '');
        // Устанавливаем современную модель по умолчанию
        add_option('ai_chatbot_openai_model', 'gpt-4o-mini');
        add_option('ai_chatbot_welcome_message', 'Привет! Я ваш AI-консультант. Чем могу помочь?');
        add_option('ai_chatbot_system_prompt', 'Ты helpful AI-ассистент, отвечающий на вопросы пользователей сайта.');
        add_option('ai_chatbot_bot_name', 'AI Консультант');
        add_option('ai_chatbot_avatar_url', AI_CHATBOT_PLUGIN_URL . 'assets/img/default-avatar.png');
        add_option('ai_chatbot_enabled', '1');
        add_option('ai_chatbot_avatar_size', 40);
        add_option('ai_chatbot_widget_size', 60);
        add_option('ai_chatbot_window_size', 'default');
        add_option('ai_chatbot_animation', 'bounce');
        add_option('ai_chatbot_color_scheme', 'default');
        add_option('ai_chatbot_primary_color', '#667eea');
        add_option('ai_chatbot_secondary_color', '#764ba2');
        add_option('ai_chatbot_bot_name_color', '#000000');
        add_option('ai_chatbot_font_family', 'system-default');
        add_option('ai_chatbot_font_size', 14);
        add_option('ai_chatbot_language', 'ru');
        add_option('ai_chatbot_margin', 20);
        add_option('ai_chatbot_email_to', get_option('admin_email'));
        add_option('ai_chatbot_inactivity_timeout', 300000); // 5 минут по умолчанию
        add_option('ai_chatbot_custom_text', array(
            'placeholder' => 'Напишите ваш вопрос...',
            'online_status' => 'В сети',
            'offline_status' => 'Не в сети',
            'send_button' => 'Отправить'
        ));
        
        // Telegram настройки
        add_option('ai_chatbot_telegram_enabled', '0');
        add_option('ai_chatbot_telegram_bot_token', '');
        add_option('ai_chatbot_telegram_chat_id', '');
        
        // Создаем таблицу для бесед
        AI_ChatBot_Chat_Handler::create_conversations_table();
    }
    
    public function enqueue_scripts() {
        if (get_option('ai_chatbot_enabled') == '1') {
            wp_enqueue_script('ai-chatbot-js', AI_CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('ai-chatbot-css', AI_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css', array(), '1.0.0');
            
            wp_localize_script('ai-chatbot-js', 'ai_chatbot_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_chatbot_nonce'),
                'welcome_message' => get_option('ai_chatbot_welcome_message'),
                'bot_name' => get_option('ai_chatbot_bot_name'),
                'avatar_url' => get_option('ai_chatbot_avatar_url')
            ));

            // Добавляем все настройки для JavaScript
            // Получаем все необходимые настройки
            $color_scheme = get_option('ai_chatbot_color_scheme', 'default');
            $primary_color = get_option('ai_chatbot_primary_color', '#667eea');
            $secondary_color = get_option('ai_chatbot_secondary_color', '#764ba2');
            $bot_name_color = get_option('ai_chatbot_bot_name_color', '#000000');
            $margin = intval(get_option('ai_chatbot_margin', 20));
            
            wp_localize_script('ai-chatbot-js', 'ai_chatbot_options', array(
                'animation' => get_option('ai_chatbot_animation', 'bounce'),
                'widget_size' => intval(get_option('ai_chatbot_widget_size', 60)),
                'window_size' => get_option('ai_chatbot_window_size', 'default'),
                'avatar_size' => intval(get_option('ai_chatbot_avatar_size', 40)),
                'color_scheme' => $color_scheme,
                'primary_color' => $primary_color,
                'secondary_color' => $secondary_color,
                'bot_name_color' => $bot_name_color,
                'margin' => $margin,
                'font_family' => get_option('ai_chatbot_font_family', 'system-default'),
                'font_size' => intval(get_option('ai_chatbot_font_size', 14)),
                'language' => get_option('ai_chatbot_language', 'ru'),
                'inactivity_timeout' => intval(get_option('ai_chatbot_inactivity_timeout', 300000)),
                'text' => get_option('ai_chatbot_custom_text', array(
                    'placeholder' => 'Напишите ваш вопрос...',
                    'online_status' => 'В сети',
                    'offline_status' => 'Не в сети',
                    'send_button' => 'Отправить'
                ))
            ));
        }
    }
    
    public function display_chat_widget() {
        if (get_option('ai_chatbot_enabled') == '1') {
            include AI_CHATBOT_PLUGIN_PATH . 'templates/chat-widget.php';
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            'AI ChatBot Settings',
            'AI ChatBot',
            'manage_options',
            'ai-chatbot-settings',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        // Регистрируем настройки
        register_setting('ai_chatbot_settings', 'ai_chatbot_enabled');
        register_setting('ai_chatbot_settings', 'ai_chatbot_openai_key');
        register_setting('ai_chatbot_settings', 'ai_chatbot_openai_model');
        register_setting('ai_chatbot_settings', 'ai_chatbot_welcome_message');
        register_setting('ai_chatbot_settings', 'ai_chatbot_system_prompt');
        register_setting('ai_chatbot_settings', 'ai_chatbot_bot_name');
        register_setting('ai_chatbot_settings', 'ai_chatbot_avatar_url');
        register_setting('ai_chatbot_settings', 'ai_chatbot_avatar_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_widget_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_window_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_animation');
        register_setting('ai_chatbot_settings', 'ai_chatbot_color_scheme');
        register_setting('ai_chatbot_settings', 'ai_chatbot_primary_color');
        register_setting('ai_chatbot_settings', 'ai_chatbot_secondary_color');
        register_setting('ai_chatbot_settings', 'ai_chatbot_bot_name_color');
        register_setting('ai_chatbot_settings', 'ai_chatbot_font_family');
        register_setting('ai_chatbot_settings', 'ai_chatbot_font_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_language');
        register_setting('ai_chatbot_settings', 'ai_chatbot_custom_text');
        register_setting('ai_chatbot_settings', 'ai_chatbot_margin');
        register_setting('ai_chatbot_settings', 'ai_chatbot_email_to');
        register_setting('ai_chatbot_settings', 'ai_chatbot_inactivity_timeout');
        
        // Telegram настройки
        register_setting('ai_chatbot_settings', 'ai_chatbot_telegram_enabled');
        register_setting('ai_chatbot_settings', 'ai_chatbot_telegram_bot_token');
        register_setting('ai_chatbot_settings', 'ai_chatbot_telegram_chat_id');

        add_settings_section(
            'ai_chatbot_main_section',
            'Основные настройки',
            null,
            'ai-chatbot-settings'
        );

        // Подключаем скрипты для админки
        if (isset($_GET['page']) && $_GET['page'] === 'ai-chatbot-settings') {
            wp_enqueue_style('ai-chatbot-admin', AI_CHATBOT_PLUGIN_URL . 'assets/css/admin.css');
            wp_enqueue_script('ai-chatbot-admin', AI_CHATBOT_PLUGIN_URL . 'assets/js/admin-settings.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('ai-chatbot-admin-realtime', AI_CHATBOT_PLUGIN_URL . 'assets/js/admin-realtime.js', array('jquery'), '1.0.0', true);
            wp_localize_script('ai-chatbot-admin', 'ai_chatbot_admin', array(
                'nonce' => wp_create_nonce('ai_chatbot_nonce')
            ));
        }
    }
    
    public function admin_page() {
        include AI_CHATBOT_PLUGIN_PATH . 'admin/settings-page.php';
    }

    public function handle_realtime_settings() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        // Сохраняем все настройки
        foreach ($settings as $key => $value) {
            $option_name = 'ai_chatbot_' . $key;
            
            switch ($key) {
                case 'bot_name_color':
                case 'primary_color':
                case 'secondary_color':
                    update_option($option_name, sanitize_hex_color($value));
                    break;
                    
                case 'margin':
                case 'widget_size':
                case 'avatar_size':
                case 'font_size':
                    update_option($option_name, intval($value));
                    break;
                    
                case 'color_scheme':
                case 'window_size':
                case 'font_family':
                case 'animation':
                    update_option($option_name, sanitize_text_field($value));
                    break;
                    
                case 'custom_text':
                    if (is_array($value)) {
                        $sanitized_text = array_map('sanitize_text_field', $value);
                        update_option($option_name, $sanitized_text);
                    }
                    break;
            }
        }
        
        // Генерируем новый CSS
        if (!class_exists('AI_ChatBot_CSS_Generator')) {
            require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-css-generator.php';
        }
        
        $css_generator = new AI_ChatBot_CSS_Generator();
        $css_url = $css_generator->save();
        
        wp_send_json_success(array(
            'css_url' => $css_url,
            'settings' => $settings
        ));
    }
}

// Инициализация плагина
new AIChatBot();