<?php
/**
 * Класс для работы с OpenAI API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_ChatBot_OpenAI_API {
    
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $model_option;
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('ai_chatbot_openai_key');
        $this->model_option = get_option('ai_chatbot_openai_model');
    }
    
    /**
     * Отправка сообщения в OpenAI API
     */
    public function send_message($message, $system_prompt = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API ключ OpenAI не настроен');
        }
        
        $system_prompt = $system_prompt ?: get_option('ai_chatbot_system_prompt', 'Ты helpful AI-ассистент.');
        
        $selected_model = $this->get_model_name();
        $data = array(
            'model' => $selected_model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $message
                )
            ),
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        $response = $this->make_api_request($data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->parse_response($response);
    }
    
    /**
     * Отправка сообщения с контекстом беседы
     */
    public function send_message_with_context($message, $conversation_history = array(), $system_prompt = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API ключ OpenAI не настроен');
        }
        
        $system_prompt = $system_prompt ?: get_option('ai_chatbot_system_prompt', 'Ты helpful AI-ассистент.');
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            )
        );
        
        // Добавляем историю беседы (последние 10 сообщений для экономии токенов)
        $recent_history = array_slice($conversation_history, -10);
        foreach ($recent_history as $msg) {
            $messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content']
            );
        }
        
        // Добавляем текущее сообщение
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        $selected_model = $this->get_model_name();
        $data = array(
            'model' => $selected_model,
            'messages' => $messages,
            'max_tokens' => 1000,
            'temperature' => 0.7
        );
        
        $response = $this->make_api_request($data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->parse_response($response);
    }
    
    /**
     * Тестирование подключения к API
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API ключ не настроен');
        }
        
        $selected_model = $this->get_model_name();
        $data = array(
            'model' => $selected_model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Привет! Это тестовое сообщение.'
                )
            ),
            'max_tokens' => 50
        );
        
        $response = $this->make_api_request($data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $parsed = $this->parse_response($response);
        
        if (is_wp_error($parsed)) {
            return $parsed;
        }
        
        return array(
            'success' => true,
            'message' => 'Подключение успешно установлено',
            'response' => $parsed
        );
    }
    
    /**
     * Выполнение запроса к API
     */
    private function make_api_request($data) {
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'User-Agent' => 'AI-ChatBot-WordPress-Plugin/1.0'
            ),
            'body' => json_encode($data),
            'data_format' => 'body'
        );
        
        $response = wp_remote_request($this->api_url, $args);
        
        // Логирование для отладки
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('OpenAI API Request: ' . json_encode($args));
            error_log('OpenAI API Response: ' . wp_remote_retrieve_body($response));
        }
        
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', 'Не удалось выполнить запрос к API: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : 'Неизвестная ошибка API';
            
            return new WP_Error('api_error', 'Ошибка API (код ' . $response_code . '): ' . $error_message);
        }
        
        return $response_body;
    }
    
    /**
     * Парсинг ответа от API
     */
    private function parse_response($response_body) {
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Ошибка декодирования JSON ответа');
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', 'Неверный формат ответа от API');
        }
        
        return array(
            'content' => trim($data['choices'][0]['message']['content']),
            'usage' => isset($data['usage']) ? $data['usage'] : null,
            'model' => isset($data['model']) ? $data['model'] : null
        );
    }

    /**
     * Возвращает валидное имя модели, учитывая настройки и обратную совместимость
     */
    private function get_model_name() {
        $model = $this->model_option ?: get_option('ai_chatbot_openai_model');
        // Фолбэк на современную универсальную модель, если ничего не задано
        if (empty($model)) {
            $model = 'gpt-4o-mini';
        }
        // Маппинг устаревших названий на актуальные аналоги
        $legacy_map = array(
            'gpt-3.5-turbo' => 'gpt-4o-mini',
            'gpt-4' => 'gpt-4o',
            'gpt-4-turbo' => 'gpt-4o'
        );
        if (isset($legacy_map[$model])) {
            $model = $legacy_map[$model];
        }
        return $model;
    }
    
    /**
     * Получение информации об использовании токенов
     */
    public function get_usage_info() {
        // Здесь можно реализовать логику для отслеживания использования токенов
        return array(
            'total_tokens_used' => get_option('ai_chatbot_total_tokens', 0),
            'requests_count' => get_option('ai_chatbot_requests_count', 0),
            'last_request' => get_option('ai_chatbot_last_request', '')
        );
    }
    
    /**
     * Обновление статистики использования
     */
    public function update_usage_stats($usage_data) {
        if (isset($usage_data['total_tokens'])) {
            $current_total = get_option('ai_chatbot_total_tokens', 0);
            update_option('ai_chatbot_total_tokens', $current_total + $usage_data['total_tokens']);
        }
        
        $current_requests = get_option('ai_chatbot_requests_count', 0);
        update_option('ai_chatbot_requests_count', $current_requests + 1);
        update_option('ai_chatbot_last_request', current_time('mysql'));
    }
    
    /**
     * Валидация API ключа
     */
    public function validate_api_key($api_key = null) {
        $key = $api_key ?: $this->api_key;
        
        if (empty($key)) {
            return false;
        }
        
        // Проверка формата ключа OpenAI
        if (!preg_match('/^sk-[a-zA-Z0-9]{48}$/', $key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Получение списка доступных моделей
     */
    public function get_available_models() {
        return array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Быстрый и экономичный)',
            'gpt-4' => 'GPT-4 (Более точный, но дорогой)',
            'gpt-4-turbo' => 'GPT-4 Turbo (Баланс скорости и качества)'
        );
    }
    
    /**
     * Обработка ошибок API
     */
    private function handle_api_error($error_code, $error_message) {
        $user_friendly_messages = array(
            'invalid_api_key' => 'Неверный API ключ. Проверьте правильность ключа в настройках.',
            'insufficient_quota' => 'Исчерпан лимит запросов API. Проверьте баланс на OpenAI.',
            'rate_limit_exceeded' => 'Превышен лимит запросов в минуту. Попробуйте позже.',
            'model_not_found' => 'Указанная модель недоступна.',
            'context_length_exceeded' => 'Сообщение слишком длинное.',
        );
        
        return isset($user_friendly_messages[$error_code]) 
            ? $user_friendly_messages[$error_code] 
            : $error_message;
    }
}