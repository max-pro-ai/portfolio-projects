<?php
/**
 * Обработчик отправки email уведомлений (планирование через AJAX)
 */

if (!defined('ABSPATH')) {
    exit;
}

// AJAX хендлер — сохраняет историю и планирует отправку по таймауту из настроек
function ai_chatbot_handle_email() {
    check_ajax_referer('ai_chatbot_nonce', 'nonce');

    $chat_history = json_decode(stripslashes($_POST['history']), true);
    $client_session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

    if (empty($chat_history) || !is_array($chat_history)) {
        wp_send_json_error('История чата пуста или неверный формат');
        return;
    }

    // Игнорируем истории без сообщений пользователя (защита от отправки приветствия бота)
    $has_user_message = false;
    foreach ($chat_history as $msg) {
        if (isset($msg['sender']) && $msg['sender'] === 'user' && !empty(trim((string)($msg['text'] ?? '')))) {
            $has_user_message = true;
            break;
        }
    }
    if (!$has_user_message) {
        wp_send_json_success('История без сообщений пользователя — сохранение и отправка не требуются');
        return;
    }

    // Получаем таймаут из настроек (мс) и конвертируем в секунды
    $timeout_ms = intval(get_option('ai_chatbot_inactivity_timeout', 300000)); // 5 минут по умолчанию
    if ($timeout_ms < 1000) {
        $timeout_ms = 1000;
    }
    $timeout_seconds = intval(ceil($timeout_ms / 1000));

    // Определяем время последнего сообщения
    $last = end($chat_history);
    $last_time = null;
    
    if (isset($last['timestamp'])) {
        $last_time = intval($last['timestamp']);
    } elseif (isset($last['time'])) {
        // Парсим время из формата "HH:MM"
        $time_parts = explode(':', $last['time']);
        if (count($time_parts) === 2) {
            $current_date = current_time('Y-m-d');
            $last_time = strtotime($current_date . ' ' . $last['time']);
        }
    }
    
    // Если не удалось определить время, используем текущее
    if (!$last_time) {
        $last_time = time();
    }

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

    // Стабильный идентификатор сессии: используем клиентский, иначе генерируем новый
    if (!empty($client_session_id)) {
        $session_id = 'ajax_' . md5($client_session_id);
    } else {
        $session_id = 'ajax_' . md5(json_encode($chat_history) . microtime(true));
    }
    $meta_file = $store_dir . $session_id . '.json';

    // Сохраняем/обновляем информацию в файле (email, messages, scheduled_time)
    $to_email = get_option('ai_chatbot_email_to', get_option('admin_email'));
    $data = array(
        'session_id' => $session_id,
        'to_email' => $to_email,
        'messages' => $chat_history,
        'scheduled_time' => $scheduled_time,
        'created' => $current_time
    );

    // Если файл уже существует (раньше сохраняли историю) — перезаписываем свежей полной историей
    file_put_contents($meta_file, wp_json_encode($data));

    // Отменяем возможные прошлые события для этого session_id
    wp_clear_scheduled_hook('ai_chatbot_send_scheduled_email', array($meta_file));

    // Если клиент просит отправить сразу (он уже выждал таймер на фронте) ИЛИ время подошло
    $send_now = isset($_POST['send_now']) && intval($_POST['send_now']) === 1;
    if ($send_now || $current_time >= $scheduled_time) {
        // Выполняем сразу
        $result = ai_chatbot_process_scheduled_email($meta_file);
        if ($result) {
            wp_send_json_success('История отправлена немедленно');
        } else {
            wp_send_json_error('Ошибка при отправке истории');
        }
        return;
    }

    // Планируем событие на scheduled_time
    wp_schedule_single_event($scheduled_time, 'ai_chatbot_send_scheduled_email', array($meta_file));

    error_log('AI ChatBot: Scheduled email for ' . $meta_file . ' at ' . date('Y-m-d H:i:s', $scheduled_time));

    wp_send_json_success('История сохранена. Уведомление будет отправлено после таймаута.');
}

add_action('wp_ajax_ai_chatbot_send_email', 'ai_chatbot_handle_email');
add_action('wp_ajax_nopriv_ai_chatbot_send_email', 'ai_chatbot_handle_email');

/**
 * Серверная постановка истории в очередь без участия браузера
 * @param string $client_session_id
 * @param array $chat_history  Массив вида [{sender:'user|bot', text:'...', time:'HH:MM', timestamp:int}, ...]
 * @param int $timeout_ms      Таймаут неактивности в миллисекундах
 * @return bool|WP_Error
 */
function ai_chatbot_enqueue_history($client_session_id, $chat_history, $timeout_ms) {
    if (empty($chat_history) || !is_array($chat_history)) {
        return new WP_Error('empty_history', 'История чата пуста');
    }

    // Проверяем на дубликаты: создаем хеш содержимого истории
    $history_hash = md5(json_encode($chat_history));
    
    // Проверяем, не была ли уже отправлена история с таким же содержимым
    $sent_histories = get_option('ai_chatbot_sent_histories', array());
    if (in_array($history_hash, $sent_histories)) {
        error_log('AI ChatBot: Пропускаем дубликат истории для session_id: ' . $client_session_id);
        return true; // Уже отправляли, считаем успехом
    }

    // Таймаут в секундах
    $timeout_ms = intval($timeout_ms);
    if ($timeout_ms < 1000) { $timeout_ms = 1000; }
    $timeout_seconds = intval(ceil($timeout_ms / 1000));

    // Определяем время последнего сообщения
    $last = end($chat_history);
    $last_time = isset($last['timestamp']) ? intval($last['timestamp']) : time();
    $scheduled_time = $last_time + $timeout_seconds;
    $current_time = time();

    // Директория хранения
    $upload_dir = wp_upload_dir();
    $store_dir = trailingslashit($upload_dir['basedir']) . 'ai-chatbot-ajax-history/';
    if (!file_exists($store_dir)) {
        wp_mkdir_p($store_dir);
        $ht = $store_dir . '.htaccess';
        if (!file_exists($ht)) { file_put_contents($ht, "Deny from all\n"); }
    }

    // Стабильный session_id на базе клиентского
    $session_id = 'ajax_' . md5($client_session_id ?: (json_encode($chat_history) . microtime(true)));
    $meta_file = $store_dir . $session_id . '.json';

    // Сохраняем файл
    $to_email = get_option('ai_chatbot_email_to', get_option('admin_email'));
    $data = array(
        'session_id' => $session_id,
        'to_email' => $to_email,
        'messages' => $chat_history,
        'scheduled_time' => $scheduled_time,
        'created' => $current_time,
        'history_hash' => $history_hash // Сохраняем хеш для проверки дубликатов
    );
    file_put_contents($meta_file, wp_json_encode($data));

    // Снимаем прошлые запланированные события для этого файла и планируем новое
    wp_clear_scheduled_hook('ai_chatbot_send_scheduled_email', array($meta_file));
    if ($current_time >= $scheduled_time) {
        return ai_chatbot_process_scheduled_email($meta_file) ? true : new WP_Error('send_failed', 'Не удалось отправить немедленно');
    } else {
        wp_schedule_single_event($scheduled_time, 'ai_chatbot_send_scheduled_email', array($meta_file));
        return true;
    }
}

// Cron handler — читает файл и отправляет письмо через mail(), затем удаляет файл
function ai_chatbot_process_scheduled_email($meta_file) {
    if (!file_exists($meta_file)) {
        error_log('AI ChatBot: Scheduled file not found: ' . $meta_file);
        return false;
    }

    $content = json_decode(file_get_contents($meta_file), true);
    if (empty($content) || !isset($content['messages']) || !is_array($content['messages'])) {
        error_log('AI ChatBot: Invalid scheduled file content: ' . $meta_file);
        @unlink($meta_file);
        return false;
    }

    $to_email = sanitize_email($content['to_email'] ?? get_option('ai_chatbot_email_to', get_option('admin_email')));
    if (!is_email($to_email)) {
        error_log('AI ChatBot: Invalid to_email in scheduled file: ' . $meta_file);
        @unlink($meta_file);
        return false;
    }

    // Формируем тело письма
    $site_name = get_bloginfo('name');
    $email_content = "История чата с сайта {$site_name}\n\n";
    $email_content .= "Дата и время отправки: " . current_time('mysql') . "\n\n";
    $email_content .= "История переписки:\n\n";

    foreach ($content['messages'] as $message) {
        $sender = (isset($message['sender']) && $message['sender'] === 'user') ? 'Пользователь' : 'Бот';
        $time = isset($message['time']) ? $message['time'] : (isset($message['timestamp']) ? date('d.m.Y H:i:s', intval($message['timestamp'])) : '');
        $text = isset($message['text']) ? $message['text'] : '';
        
        // Очищаем текст от HTML тегов
        $text = strip_tags($text);
        
        $email_content .= "[{$time}] {$sender}: {$text}\n\n";
    }

    $email_content .= "\nИнформация о пользователе:\n";
    $email_content .= "IP адрес: " . ($_SERVER['REMOTE_ADDR'] ?? 'Неизвестно') . "\n";
    $email_content .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Неизвестно') . "\n";
    $email_content .= "URL страницы: " . ($_SERVER['HTTP_REFERER'] ?? 'Неизвестно') . "\n";
    $email_content .= "Сессия: " . ($content['session_id'] ?? 'Неизвестно') . "\n";

    // Заголовки для mail()
    $domain = parse_url(get_site_url(), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '');
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: {$site_name} <noreply@{$domain}>\r\n";
    $headers .= "Reply-To: noreply@{$domain}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $subject = 'История чата с сайта ' . $site_name;
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // Жёстко используем mail() по требованию
    $sent = mail($to_email, $encoded_subject, $email_content, $headers);

    // Отправляем Telegram
    $telegram_enabled = get_option('ai_chatbot_telegram_enabled', '0');
    $telegram_ok = true;
    if ($telegram_enabled === '1') {
        $tg = ai_chatbot_send_to_telegram($content['messages']);
        $telegram_ok = !is_wp_error($tg);
        if ($telegram_ok) {
            error_log('AI ChatBot: Telegram message sent successfully');
        } else {
            error_log('AI ChatBot: Telegram error: ' . $tg->get_error_message());
        }
    }

    // Удаляем файл только если все каналы успешны
    if ($sent && $telegram_ok) {
        error_log('AI ChatBot: Delivery successful (email' . ($telegram_enabled==='1'?'+telegram':'') . ') for file: ' . $meta_file);
        
        // Отмечаем историю как отправленную, чтобы избежать дубликатов
        if (isset($content['history_hash'])) {
            $sent_histories = get_option('ai_chatbot_sent_histories', array());
            $sent_histories[] = $content['history_hash'];
            // Ограничиваем размер массива (последние 1000 хешей)
            if (count($sent_histories) > 1000) {
                $sent_histories = array_slice($sent_histories, -1000);
            }
            update_option('ai_chatbot_sent_histories', $sent_histories);
        }
        
        @unlink($meta_file);
        return true;
    }

    // Иначе перепланируем повтор на +5 минут
    if (!$sent) {
        error_log('AI ChatBot: Failed to send scheduled email to ' . $to_email . ' (file: ' . $meta_file . ')');
    }
    if (!$telegram_ok && $telegram_enabled==='1') {
        error_log('AI ChatBot: Will retry Telegram with email on next attempt');
    }
    $retry_time = time() + 300;
    wp_schedule_single_event($retry_time, 'ai_chatbot_send_scheduled_email', array($meta_file));
    error_log('AI ChatBot: Re-scheduled email for ' . date('Y-m-d H:i:s', $retry_time));
    return false;
}

add_action('ai_chatbot_send_scheduled_email', 'ai_chatbot_process_scheduled_email', 10, 1);

// Функция для очистки старых файлов истории
function ai_chatbot_cleanup_old_files() {
    $upload_dir = wp_upload_dir();
    $store_dir = trailingslashit($upload_dir['basedir']) . 'ai-chatbot-ajax-history/';
    
    if (!file_exists($store_dir)) {
        return;
    }
    
    $files = glob($store_dir . '*.json');
    $current_time = time();
    $max_age = 24 * 60 * 60; // 24 часа
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $file_time = filemtime($file);
            if ($current_time - $file_time > $max_age) {
                @unlink($file);
                error_log('AI ChatBot: Удален старый файл истории: ' . $file);
            }
        }
    }
}

// Функция для очистки старых хешей отправленных историй
function ai_chatbot_cleanup_old_hashes() {
    $sent_histories = get_option('ai_chatbot_sent_histories', array());
    if (count($sent_histories) > 1000) {
        // Оставляем только последние 500 хешей
        $sent_histories = array_slice($sent_histories, -500);
        update_option('ai_chatbot_sent_histories', $sent_histories);
        error_log('AI ChatBot: Очищены старые хеши отправленных историй');
    }
}

// Регистрируем cron задачу для очистки старых файлов (каждый час)
if (!wp_next_scheduled('ai_chatbot_cleanup_files')) {
    wp_schedule_event(time(), 'hourly', 'ai_chatbot_cleanup_files');
}
add_action('ai_chatbot_cleanup_files', 'ai_chatbot_cleanup_old_files');

// Регистрируем cron задачу для очистки старых хешей (каждый день)
if (!wp_next_scheduled('ai_chatbot_cleanup_hashes')) {
    wp_schedule_event(time(), 'daily', 'ai_chatbot_cleanup_hashes');
}
add_action('ai_chatbot_cleanup_hashes', 'ai_chatbot_cleanup_old_hashes');

/**
 * Резервный обработчик: каждую минуту проверяет директорию с отложенными файлами
 * и отправляет все, у кого подошло scheduled_time, в том числе застрявшие
 */
function ai_chatbot_process_due_histories() {
    $upload_dir = wp_upload_dir();
    $store_dir = trailingslashit($upload_dir['basedir']) . 'ai-chatbot-ajax-history/';
    if (!file_exists($store_dir)) {
        return;
    }

    $files = glob($store_dir . '*.json');
    if (!$files) return;

    $now = time();
    foreach ($files as $file) {
        $content = @json_decode(@file_get_contents($file), true);
        if (empty($content) || !isset($content['scheduled_time'])) {
            continue;
        }
        if (intval($content['scheduled_time']) <= $now) {
            ai_chatbot_process_scheduled_email($file);
        }
    }
}

// Регистрируем ежеминутное событие
add_action('ai_chatbot_process_due_histories', 'ai_chatbot_process_due_histories');
if (!wp_next_scheduled('ai_chatbot_process_due_histories')) {
    // Если в системе нет интервала 'minutely', добавим временно через filter
    add_filter('cron_schedules', function($schedules) {
        if (!isset($schedules['minutely'])) {
            $schedules['minutely'] = array('interval' => 60, 'display' => 'Every Minute');
        }
        return $schedules;
    });
    wp_schedule_event(time() + 60, 'minutely', 'ai_chatbot_process_due_histories');
}

/**
 * Отправка истории чата в Telegram
 */
function ai_chatbot_send_to_telegram($chat_history) {
    // Проверяем включены ли Telegram уведомления
    $telegram_enabled = get_option('ai_chatbot_telegram_enabled', '0');
    if ($telegram_enabled !== '1') {
        return false;
    }
    
    // Проверяем наличие токена и chat ID
    $bot_token = get_option('ai_chatbot_telegram_bot_token', '');
    $chat_id = get_option('ai_chatbot_telegram_chat_id', '');
    
    if (empty($bot_token) || empty($chat_id)) {
        return new WP_Error('telegram_not_configured', 'Telegram не настроен');
    }
    
    // Подключаем класс Telegram handler
    if (!class_exists('AI_ChatBot_Telegram_Handler')) {
        require_once AI_CHATBOT_PLUGIN_PATH . 'includes/class-telegram-handler.php';
    }
    
    try {
        $telegram = new AI_ChatBot_Telegram_Handler($bot_token, $chat_id);
        $result = $telegram->send_chat_history($chat_history);
        
        if (is_wp_error($result)) {
            error_log('AI ChatBot: Telegram error: ' . $result->get_error_message());
            return $result;
        }
        
        return true;
    } catch (Exception $e) {
        error_log('AI ChatBot: Telegram exception: ' . $e->getMessage());
        return new WP_Error('telegram_exception', $e->getMessage());
    }
}
