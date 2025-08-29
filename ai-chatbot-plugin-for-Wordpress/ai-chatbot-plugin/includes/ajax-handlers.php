<?php
// Безопасность
if (!defined('ABSPATH')) {
    exit;
}

// Определяем путь к плагину если константа не определена
if (!defined('AI_CHATBOT_PLUGIN_PATH')) {
    define('AI_CHATBOT_PLUGIN_PATH', plugin_dir_path(dirname(__FILE__)));
}

// Обработчики AJAX
add_action('wp_ajax_test_openai_connection', 'ai_chatbot_test_connection');
add_action('wp_ajax_ai_chatbot_clear_cache', 'ai_chatbot_clear_cache');
add_action('wp_ajax_ai_chatbot_test_email', 'ai_chatbot_test_email');
add_action('wp_ajax_ai_chatbot_send_email', 'ai_chatbot_handle_email');
add_action('wp_ajax_nopriv_ai_chatbot_send_email', 'ai_chatbot_handle_email');
add_action('wp_ajax_ai_chatbot_test_telegram', 'ai_chatbot_test_telegram');
add_action('wp_ajax_ai_chatbot_test_timer', 'ai_chatbot_test_timer');

/**
 * Тестирование email функциональности
 */
function ai_chatbot_test_email() {
    if (!check_ajax_referer('ai_chatbot_test_email', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error('Invalid email address');
        return;
    }
    
    $subject = 'Тестовое письмо AI ChatBot';
    $message = "Это тестовое письмо от плагина AI ChatBot.\n\n";
    $message .= "Если вы получили это письмо, значит настройки email работают корректно.\n\n";
    $message .= "Дата и время отправки: " . current_time('mysql') . "\n";
    $message .= "Сайт: " . get_bloginfo('name') . " (" . get_site_url() . ")";
    
    // Подготавливаем заголовки
    $site_name = get_bloginfo('name');
    $domain = parse_url(get_site_url(), PHP_URL_HOST);
    $headers_str = "MIME-Version: 1.0\r\n";
    $headers_str .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers_str .= "From: {$site_name} <noreply@{$domain}>\r\n";
    $headers_str .= "Reply-To: noreply@{$domain}\r\n";
    $headers_str .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // Кодируем тему для UTF-8
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    $sent = mail($email, $encoded_subject, $message, $headers_str);
    
    if ($sent) {
        wp_send_json_success('Тестовое письмо отправлено');
    } else {
        wp_send_json_error('Ошибка при отправке тестового письма');
    }
}

/**
 * Тестирование подключения к OpenAI API
 */
function ai_chatbot_test_connection() {
    if (!check_ajax_referer('test_openai_connection', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    
    if (empty($api_key)) {
        wp_send_json_error('API key is required');
        return;
    }
    
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'user', 'content' => 'Test connection')
            )
        )),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        wp_send_json_error('API Error: ' . $error_message);
        return;
    }
    
    wp_send_json_success('Connection successful');
}

/**
 * Очистка кеша плагина
 */
function ai_chatbot_clear_cache() {
    if (!check_ajax_referer('ai_chatbot_clear_cache', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        // Очищаем transients
        delete_transient('ai_chatbot_settings');
        
        // Очищаем кеш объектов WordPress
        wp_cache_flush();
        
        // Обновляем версию стилей
        $style_version = get_option('ai_chatbot_style_version', 1);
        update_option('ai_chatbot_style_version', $style_version + 1);
        
        // Очищаем OPcache если он включен
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        wp_send_json_success('Cache cleared successfully');
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Тестирование Telegram интеграции
 */
function ai_chatbot_test_telegram() {
    if (!check_ajax_referer('ai_chatbot_test_telegram', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $bot_token = isset($_POST['bot_token']) ? sanitize_text_field($_POST['bot_token']) : '';
    $chat_id = isset($_POST['chat_id']) ? sanitize_text_field($_POST['chat_id']) : '';
    
    if (empty($bot_token) || empty($chat_id)) {
        wp_send_json_error('Bot Token и Chat ID обязательны');
        return;
    }
    
    // Подключаем класс Telegram handler
    require_once AI_CHATBOT_PLUGIN_PATH . 'includes/class-telegram-handler.php';
    
    $telegram = new AI_ChatBot_Telegram_Handler($bot_token, $chat_id);
    $result = $telegram->test_connection();
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success('Тестовое сообщение отправлено в Telegram');
    }
}

/**
 * Тестирование таймера
 */
function ai_chatbot_test_timer() {
    if (!check_ajax_referer('ai_chatbot_test_timer', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $timeout = isset($_POST['timeout']) ? intval($_POST['timeout']) : 60000;
    
    if ($timeout < 1000) {
        wp_send_json_error('Таймаут должен быть минимум 1000 мс');
        return;
    }
    
    // Создаем тестовую историю чата
    $test_history = array(
        array(
            'sender' => 'user',
            'text' => 'Тестовое сообщение пользователя',
            'time' => date('H:i'),
            'timestamp' => time()
        ),
        array(
            'sender' => 'bot',
            'text' => 'Тестовый ответ бота для проверки таймера',
            'time' => date('H:i'),
            'timestamp' => time()
        )
    );
    
    // Отправляем тестовую историю через email handler
    $result = ai_chatbot_send_to_email($test_history, $timeout);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success('Тест таймера запущен. Проверьте почту через ' . ($timeout/1000) . ' секунд');
    }
}

/**
 * Отправка истории чата на email (для тестирования)
 */
function ai_chatbot_send_to_email($chat_history, $timeout = 60000) {
    if (empty($chat_history) || !is_array($chat_history)) {
        return new WP_Error('empty_history', 'История чата пуста');
    }
    
    // Получаем таймаут из настроек (мс) и конвертируем в секунды
    $timeout_ms = $timeout;
    if ($timeout_ms < 1000) {
        $timeout_ms = 1000;
    }
    $timeout_seconds = intval(ceil($timeout_ms / 1000));
    
    // Определяем время последнего сообщения
    $last = end($chat_history);
    $last_time = isset($last['timestamp']) ? intval($last['timestamp']) : time();
    
    $scheduled_time = $last_time + $timeout_seconds;
    $current_time = time();
    
    // Подготовим директорию для хранения временных данных
    $upload_dir = wp_upload_dir();
    $store_dir = trailingslashit($upload_dir['basedir']) . 'ai-chatbot-ajax-history/';
    if (!file_exists($store_dir)) {
        wp_mkdir_p($store_dir);
        // защита
        $ht = $store_dir . '.htaccess';
        if (!file_exists($ht)) {
            file_put_contents($ht, "Deny from all\n");
        }
    }
    
    // Уникальный идентификатор сессии для теста
    $session_id = 'test_' . md5(json_encode($chat_history) . microtime(true));
    $meta_file = $store_dir . $session_id . '.json';
    
    // Сохраняем информацию в файл
    $to_email = get_option('ai_chatbot_email_to', get_option('admin_email'));
    $data = array(
        'session_id' => $session_id,
        'to_email' => $to_email,
        'messages' => $chat_history,
        'scheduled_time' => $scheduled_time,
        'created' => $current_time
    );
    
    file_put_contents($meta_file, wp_json_encode($data));
    
    // Отменяем возможные прошлые события для этого session_id
    wp_clear_scheduled_hook('ai_chatbot_send_scheduled_email', array($meta_file));
    
    // Если время уже подошло или прошло — отправляем сразу
    if ($current_time >= $scheduled_time) {
        $result = ai_chatbot_process_scheduled_email($meta_file);
        if ($result) {
            return true;
        } else {
            return new WP_Error('email_send_failed', 'Ошибка при отправке email');
        }
    }
    
    // Планируем событие на scheduled_time
    wp_schedule_single_event($scheduled_time, 'ai_chatbot_send_scheduled_email', array($meta_file));
    
    error_log('AI ChatBot: Test timer scheduled email for ' . $meta_file . ' at ' . date('Y-m-d H:i:s', $scheduled_time));
    
    return true;
}
