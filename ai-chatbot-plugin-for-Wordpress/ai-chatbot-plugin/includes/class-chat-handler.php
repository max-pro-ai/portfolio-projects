<?php
/**
 * Класс для обработки чата
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once AI_CHATBOT_PLUGIN_PATH . 'includes/class-openai-api.php';

class AI_ChatBot_Chat_Handler {
    
    private $openai_api;
    
    public function __construct() {
        $this->openai_api = new AI_ChatBot_OpenAI_API();
        $this->init_hooks();
        
        // Проверяем и создаем таблицу если нужно
        $this->ensure_table_exists();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_ai_chatbot_message', array($this, 'handle_message'));
        add_action('wp_ajax_nopriv_ai_chatbot_message', array($this, 'handle_message'));
        add_action('wp_ajax_ai_chatbot_get_history', array($this, 'get_chat_history'));
        add_action('wp_ajax_nopriv_ai_chatbot_get_history', array($this, 'get_chat_history'));
    }
    
    private function ensure_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Проверяем существование таблицы
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            self::create_conversations_table();
        }
    }
    
    /**
     * Обработка входящего сообщения
     */
    public function handle_message() {
        // Проверка nonce для безопасности
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_nonce')) {
            wp_send_json_error('Ошибка безопасности');
            return;
        }
        
        $message = sanitize_text_field($_POST['message']);
        $session_id = $this->get_session_id();
        
        if (empty($message)) {
            wp_send_json_error('Пустое сообщение');
            return;
        }
        
        // Проверка длины сообщения
        if (strlen($message) > 1000) {
            wp_send_json_error('Сообщение слишком длинное. Максимум 1000 символов.');
            return;
        }
        
        // Анти-спам проверка
        if ($this->is_spam($message, $session_id)) {
            wp_send_json_error('Слишком много запросов. Подождите немного.');
            return;
        }
        
        // Получение истории беседы
        $conversation_history = $this->get_conversation_history($session_id);
        
        // Фильтрация сообщения
        $filtered_message = $this->filter_message($message);
        
        // Отправка в OpenAI
        $response = $this->openai_api->send_message_with_context(
            $filtered_message,
            $conversation_history,
            get_option('ai_chatbot_system_prompt')
        );
        
        if (is_wp_error($response)) {
            $error_message = $this->get_user_friendly_error($response->get_error_message());
            wp_send_json_error($error_message);
            return;
        }
        
        // Сохранение беседы
        $this->save_conversation($session_id, $message, $response['content']);
        
        // Обновление статистики
        if (isset($response['usage'])) {
            $this->openai_api->update_usage_stats($response['usage']);
        }
        
        // Постобработка ответа
        $processed_response = $this->process_response($response['content']);
        
        // Ставим историю в серверную очередь доставки, независимую от браузера
        if (!function_exists('ai_chatbot_enqueue_history')) {
            require_once AI_CHATBOT_PLUGIN_PATH . 'includes/email-handler.php';
        }
        $history = $this->get_conversation_history($session_id, 10);
        // Преобразуем историю в формат email-handler (sender/text/time/timestamp)
        $export = array();
        $now = time();
        foreach ($history as $h) {
            $export[] = array(
                'sender' => $h['role'] === 'user' ? 'user' : 'bot',
                'text' => $h['content'],
                'time' => date('H:i', $now),
                'timestamp' => $now
            );
        }
        $timeout_ms = intval(get_option('ai_chatbot_inactivity_timeout', 300000));
        ai_chatbot_enqueue_history($session_id, $export, $timeout_ms);

        wp_send_json_success($processed_response);
    }
    
    /**
     * Получение истории чата
     */
    public function get_chat_history() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        $session_id = $this->get_session_id();
        $history = $this->get_conversation_history($session_id);
        
        wp_send_json_success($history);
    }
    
    /**
     * Получение ID сессии пользователя
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['ai_chatbot_session_id'])) {
            $_SESSION['ai_chatbot_session_id'] = uniqid('chatbot_', true);
        }
        
        return $_SESSION['ai_chatbot_session_id'];
    }
    
    /**
     * Получение истории беседы из базы данных
     */
    private function get_conversation_history($session_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE session_id = %s 
             ORDER BY created_at ASC 
             LIMIT %d",
            $session_id,
            $limit * 2 // Умножаем на 2, так как каждая беседа содержит вопрос и ответ
        ));
        
        if (!$results) {
            return array();
        }
        
        $history = array();
        foreach ($results as $row) {
            if ($row->message_type === 'user') {
                $history[] = array(
                    'role' => 'user',
                    'content' => $row->message
                );
            } else {
                $history[] = array(
                    'role' => 'assistant',
                    'content' => $row->message
                );
            }
        }
        
        return $history;
    }
    
    /**
     * Сохранение беседы в базу данных
     */
    private function save_conversation($session_id, $user_message, $bot_response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Сохранение сообщения пользователя
        $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'message' => $user_message,
                'message_type' => 'user',
                'created_at' => current_time('mysql'),
                'user_ip' => $this->get_user_ip()
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        // Сохранение ответа бота
        $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'message' => $bot_response,
                'message_type' => 'bot',
                'created_at' => current_time('mysql'),
                'user_ip' => $this->get_user_ip()
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        // Проверяем ошибки вставки
        if ($wpdb->last_error) {
            error_log('AI ChatBot: Ошибка сохранения в базу данных: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Антиспам проверка
     */
    private function is_spam($message, $session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $user_ip = $this->get_user_ip();
        
        // Проверка количества сообщений за последние 5 минут
        $recent_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE (session_id = %s OR user_ip = %s) 
             AND message_type = 'user' 
             AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $session_id,
            $user_ip
        ));
        
        if ($recent_messages > 20) { // Максимум 20 сообщений за 5 минут
            return true;
        }
        
        // Проверка на повторяющиеся сообщения
        $duplicate_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE session_id = %s 
             AND message = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            $session_id,
            $message
        ));
        
        if ($duplicate_count > 2) { // Максимум 2 одинаковых сообщения за 10 минут
            return true;
        }
        
        return false;
    }
    
    /**
     * Фильтрация сообщения пользователя
     */
    private function filter_message($message) {
        // Удаление HTML тегов
        $message = strip_tags($message);
        
        // Удаление лишних пробелов
        $message = preg_replace('/\s+/', ' ', trim($message));
        
        // Базовая фильтрация нежелательного контента
        $bad_words = array('спам', 'реклама'); // Добавьте свои ключевые слова
        foreach ($bad_words as $word) {
            if (stripos($message, $word) !== false) {
                return 'Ваше сообщение содержит недопустимый контент.';
            }
        }
        
        return $message;
    }
    
    /**
     * Постобработка ответа от AI
     */
    private function process_response($response) {
        // Удаление лишних символов
        $response = trim($response);
        
        // Замена переносов строк на HTML
        $response = nl2br($response);
        
        // Простое форматирование markdown
        $response = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $response);
        $response = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $response);
        
        // Добавление ссылок (только для безопасных доменов)
        $response = preg_replace_callback(
            '/https?:\/\/(www\.)?(wikipedia\.org|github\.com|stackoverflow\.com)([^\s]+)/i',
            function($matches) {
                return '<a href="' . $matches[0] . '" target="_blank" rel="noopener noreferrer">' . $matches[0] . '</a>';
            },
            $response
        );
        
        return $response;
    }
    
    /**
     * Получение пользовательского IP
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Преобразование технических ошибок в понятные пользователю
     */
    private function get_user_friendly_error($error_message) {
        $error_mappings = array(
            'API ключ не настроен' => 'Сервис временно недоступен. Обратитесь к администратору.',
            'invalid_api_key' => 'Сервис временно недоступен. Попробуйте позже.',
            'rate_limit_exceeded' => 'Слишком много запросов. Попробуйте через минуту.',
            'insufficient_quota' => 'Сервис временно недоступен. Обратитесь к администратору.',
            'context_length_exceeded' => 'Ваше сообщение слишком длинное. Сократите его.'
        );
        
        foreach ($error_mappings as $technical => $user_friendly) {
            if (strpos($error_message, $technical) !== false) {
                return $user_friendly;
            }
        }
        
        return 'Произошла ошибка. Попробуйте еще раз.';
    }
    
    /**
     * Создание таблицы для хранения беседы при активации плагина
     */
    public static function create_conversations_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            message text NOT NULL,
            message_type varchar(10) NOT NULL,
            user_ip varchar(45) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Очистка старых записей беседы
     */
    public function cleanup_old_conversations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Удаление записей старше 30 дней
        $wpdb->query(
            "DELETE FROM {$table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
}