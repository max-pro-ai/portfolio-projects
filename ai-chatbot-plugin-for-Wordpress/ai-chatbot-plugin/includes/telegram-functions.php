<?php
/**
 * Обработчик отправки уведомлений в Telegram
 */

function ai_chatbot_telegram_send($message, $is_user = true) {
    $token = get_option('ai_chatbot_telegram_token');
    $chat_id = get_option('ai_chatbot_telegram_chat_id');
    
    if (empty($token) || empty($chat_id)) {
        return false;
    }
    
    try {
        $sender = $is_user ? '👤 Посетитель' : '🤖 Бот';
        $text = sprintf("%s:\n%s", $sender, $message);
        
        wp_remote_post('https://api.telegram.org/bot' . $token . '/sendMessage', array(
            'timeout' => 5,
            'blocking' => false,
            'body' => array(
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'HTML'
            )
        ));
        
        return true;
    } catch (Exception $e) {
        error_log('AI Chatbot Telegram Error: ' . $e->getMessage());
        return false;
    }
}

function ai_chatbot_register_telegram_settings() {
    // Регистрируем настройки
    register_setting('ai_chatbot_options', 'ai_chatbot_telegram_token');
    register_setting('ai_chatbot_options', 'ai_chatbot_telegram_chat_id');
    
    // Добавляем секцию настроек
    add_settings_section(
        'ai_chatbot_telegram_section',
        'Настройки Telegram уведомлений',
        'ai_chatbot_telegram_section_callback',
        'ai_chatbot_options'
    );
    
    // Добавляем поля настроек
    add_settings_field(
        'ai_chatbot_telegram_token',
        'Telegram Bot Token',
        'ai_chatbot_telegram_token_callback',
        'ai_chatbot_options',
        'ai_chatbot_telegram_section'
    );
    
    add_settings_field(
        'ai_chatbot_telegram_chat_id',
        'Telegram Chat ID',
        'ai_chatbot_telegram_chat_id_callback',
        'ai_chatbot_options',
        'ai_chatbot_telegram_section'
    );
}
add_action('admin_init', 'ai_chatbot_register_telegram_settings');

function ai_chatbot_telegram_section_callback() {
    echo '<p>Настройте уведомления в Telegram для получения сообщений от посетителей.</p>';
}

function ai_chatbot_telegram_token_callback() {
    $token = get_option('ai_chatbot_telegram_token', '');
    echo '<input type="text" name="ai_chatbot_telegram_token" value="' . esc_attr($token) . '" class="regular-text">';
    echo '<p class="description">Получите токен у @BotFather в Telegram</p>';
}

function ai_chatbot_telegram_chat_id_callback() {
    $chat_id = get_option('ai_chatbot_telegram_chat_id', '');
    echo '<input type="text" name="ai_chatbot_telegram_chat_id" value="' . esc_attr($chat_id) . '" class="regular-text">';
    echo '<p class="description">ID чата можно получить у @userinfobot</p>';
}
