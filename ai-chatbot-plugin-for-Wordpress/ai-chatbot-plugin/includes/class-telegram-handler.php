<?php
/**
 * Класс для работы с Telegram API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_ChatBot_Telegram_Handler {
    
    private $bot_token;
    private $chat_id;
    private $api_url = 'https://api.telegram.org/bot';
    
    public function __construct($bot_token = null, $chat_id = null) {
        $this->bot_token = $bot_token ?: get_option('ai_chatbot_telegram_bot_token', '');
        $this->chat_id = $chat_id ?: get_option('ai_chatbot_telegram_chat_id', '');
    }
    
    /**
     * Отправка сообщения в Telegram
     */
    public function send_message($message, $parse_mode = 'HTML') {
        if (empty($this->bot_token) || empty($this->chat_id)) {
            return new WP_Error('telegram_not_configured', 'Telegram не настроен');
        }
        
        $url = $this->api_url . $this->bot_token . '/sendMessage';
        
        $data = array(
            'chat_id' => $this->chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode
        );
        
        $response = wp_remote_post($url, array(
            'body' => $data,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['ok']) && $body['ok']) {
            return true;
        } else {
            $error_message = isset($body['description']) ? $body['description'] : 'Unknown error';
            return new WP_Error('telegram_api_error', $error_message);
        }
    }
    
    /**
     * Отправка истории чата в Telegram
     */
    public function send_chat_history($chat_history) {
        if (empty($chat_history) || !is_array($chat_history)) {
            return new WP_Error('empty_history', 'История чата пуста');
        }
        
        $site_name = get_bloginfo('name');
        $message = "📱 <b>История чата с сайта {$site_name}</b>\n\n";
        $message .= "📅 Дата: " . current_time('d.m.Y H:i:s') . "\n\n";
        $message .= "💬 <b>История переписки:</b>\n\n";
        
        foreach ($chat_history as $msg) {
            $sender = (isset($msg['sender']) && $msg['sender'] === 'user') ? '👤 Пользователь' : '🤖 Бот';
            $time = isset($msg['time']) ? $msg['time'] : '';
            $text = isset($msg['text']) ? strip_tags($msg['text']) : '';
            
            // Ограничиваем длину текста для Telegram
            if (strlen($text) > 1000) {
                $text = substr($text, 0, 1000) . '...';
            }
            
            $message .= "<b>[{$time}] {$sender}:</b>\n{$text}\n\n";
        }
        
        $message .= "🌐 <b>Информация:</b>\n";
        $message .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Неизвестно') . "\n";
        // $message .= "Сайт: " . get_site_url() . "\n";
        
        // Разбиваем длинное сообщение на части (Telegram ограничение 4096 символов)
        $messages = $this->split_message($message);
        
        $results = array();
        foreach ($messages as $msg_part) {
            $result = $this->send_message($msg_part);
            if (is_wp_error($result)) {
                $results[] = $result;
            } else {
                $results[] = true;
            }
        }
        
        return $results;
    }
    
    /**
     * Разбиение длинного сообщения на части
     */
    private function split_message($message, $max_length = 4000) {
        if (strlen($message) <= $max_length) {
            return array($message);
        }
        
        $messages = array();
        $parts = explode("\n\n", $message);
        $current_message = '';
        
        foreach ($parts as $part) {
            if (strlen($current_message . $part . "\n\n") > $max_length) {
                if (!empty($current_message)) {
                    $messages[] = trim($current_message);
                    $current_message = '';
                }
                
                // Если одна часть слишком длинная, разбиваем её
                if (strlen($part) > $max_length) {
                    $sub_parts = str_split($part, $max_length);
                    foreach ($sub_parts as $sub_part) {
                        $messages[] = $sub_part;
                    }
                } else {
                    $current_message = $part . "\n\n";
                }
            } else {
                $current_message .= $part . "\n\n";
            }
        }
        
        if (!empty($current_message)) {
            $messages[] = trim($current_message);
        }
        
        return $messages;
    }
    
    /**
     * Тестирование подключения к Telegram
     */
    public function test_connection() {
        if (empty($this->bot_token) || empty($this->chat_id)) {
            return new WP_Error('telegram_not_configured', 'Telegram не настроен');
        }
        
        $test_message = "🧪 Тестовое сообщение от AI ChatBot\n\n";
        $test_message .= "✅ Если вы получили это сообщение, значит Telegram настроен корректно!\n\n";
        $test_message .= "🌐 Сайт: " . get_site_url() . "\n";
        $test_message .= "📅 Время: " . current_time('d.m.Y H:i:s');
        
        return $this->send_message($test_message);
    }
    
    /**
     * Получение информации о боте
     */
    public function get_bot_info() {
        if (empty($this->bot_token)) {
            return new WP_Error('telegram_not_configured', 'Telegram не настроен');
        }
        
        $url = $this->api_url . $this->bot_token . '/getMe';
        
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['ok']) && $body['ok']) {
            return $body['result'];
        } else {
            $error_message = isset($body['description']) ? $body['description'] : 'Unknown error';
            return new WP_Error('telegram_api_error', $error_message);
        }
    }
}
