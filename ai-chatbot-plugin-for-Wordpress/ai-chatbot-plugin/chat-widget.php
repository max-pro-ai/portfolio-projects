<?php
/**
 * Шаблон виджета чата
 */

// Получение настроек
$bot_name = get_option('ai_chatbot_bot_name', 'AI Консультант');
$avatar_url = get_option('ai_chatbot_avatar_url', AI_CHATBOT_PLUGIN_URL . 'assets/img/default-avatar.png');
$welcome_message = get_option('ai_chatbot_welcome_message', 'Привет! Я ваш AI-консультант. Чем могу помочь?');
?>

<div class="ai-chatbot-container">
    <!-- Кнопка открытия чата -->
    <button class="ai-chatbot-toggle" type="button" aria-label="Открыть чат с консультантом">
        <svg class="ai-chatbot-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
        </svg>
    </button>

    <!-- Окно чата -->
    <div class="ai-chatbot-window">
        <!-- Заголовок чата -->
        <div class="ai-chatbot-header">
            <img src="<?php echo esc_url($avatar_url); ?>" 
                 alt="<?php echo esc_attr($bot_name); ?>" 
                 class="ai-chatbot-avatar">
            
            <div class="ai-chatbot-info">
                <h3><?php echo esc_html($bot_name); ?></h3>
                <div class="ai-chatbot-status">В сети</div>
            </div>
            
            <button class="ai-chatbot-close" type="button" aria-label="Закрыть чат">
                ×
            </button>
        </div>

        <!-- Область сообщений -->
        <div class="ai-chatbot-messages" role="log" aria-live="polite" aria-label="Сообщения чата">
            <!-- Сообщения будут добавляться через JavaScript -->
        </div>

        <!-- Поле ввода -->
        <div class="ai-chatbot-input-container">
            <textarea 
                class="ai-chatbot-input" 
                placeholder="Напишите ваш вопрос..." 
                rows="1"
                aria-label="Введите сообщение"
                maxlength="1000">
            </textarea>
            
            <button class="ai-chatbot-send" type="button" aria-label="Отправить сообщение">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2,21L23,12L2,3V10L17,12L2,14V21Z"/>
                </svg>
            </button>
        </div>
    </div>
</div>