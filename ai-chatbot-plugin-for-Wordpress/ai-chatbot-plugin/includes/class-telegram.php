<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chatbot_Telegram {
    private static $instance = null;
    private $token = null;
    private $chat_id = null;

    private function __construct() {
        $this->token = get_option('ai_chatbot_telegram_token', '');
        $this->chat_id = get_option('ai_chatbot_telegram_chat_id', '');
        
        // Добавляем поля настроек
        add_action('admin_init', array($this, 'register_settings'));
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_settings() {
        register_setting('ai_chatbot_options', 'ai_chatbot_telegram_token');
        register_setting('ai_chatbot_options', 'ai_chatbot_telegram_chat_id');

        add_settings_section(
            'ai_chatbot_telegram_section',
            'Настройки Telegram уведомлений',
            array($this, 'telegram_section_callback'),
            'ai_chatbot_options'
        );

        add_settings_field(
            'ai_chatbot_telegram_token',
            'Telegram Bot Token',
            array($this, 'telegram_token_callback'),
            'ai_chatbot_options',
            'ai_chatbot_telegram_section'
        );

        add_settings_field(
            'ai_chatbot_telegram_chat_id',
            'Telegram Chat ID',
            array($this, 'telegram_chat_id_callback'),
            'ai_chatbot_options',
            'ai_chatbot_telegram_section'
        );
    }

    public function telegram_section_callback() {
        echo '<p>Настройте уведомления в Telegram для получения сообщений от посетителей.</p>';
    }

    public function telegram_token_callback() {
        $token = esc_attr(get_option('ai_chatbot_telegram_token', ''));
        echo '<input type="text" class="regular-text" name="ai_chatbot_telegram_token" value="' . $token . '">';
        echo '<p class="description">Получите токен у @BotFather в Telegram</p>';
    }

    public function telegram_chat_id_callback() {
        $chat_id = esc_attr(get_option('ai_chatbot_telegram_chat_id', ''));
        echo '<input type="text" class="regular-text" name="ai_chatbot_telegram_chat_id" value="' . $chat_id . '">';
        echo '<p class="description">ID чата можно получить у @userinfobot</p>';
    }

    public function send_notification($message, $is_user = true) {
        if (empty($this->token) || empty($this->chat_id)) {
            return false;
        }

        try {
            $sender = $is_user ? '👤 Посетитель' : '🤖 Бот';
            $text = sprintf("%s:\n%s", $sender, $message);

            wp_remote_post('https://api.telegram.org/bot' . $this->token . '/sendMessage', array(
                'timeout' => 5,
                'blocking' => false,
                'body' => array(
                    'chat_id' => $this->chat_id,
                    'text' => $text,
                    'parse_mode' => 'HTML'
                )
            ));

            return true;
        } catch (Exception $e) {
            error_log('Telegram notification error: ' . $e->getMessage());
            return false;
        }
    }
}
