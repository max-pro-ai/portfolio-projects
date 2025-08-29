<?php
// Проверка безопасности
if (!defined('ABSPATH')) {
    exit;
}

function ai_chatbot_settings_page() {
    // Проверка прав доступа
    if (!current_user_can('manage_options')) {
        return;
    }

    // Сохранение настроек
    if (isset($_POST['ai_chatbot_settings_nonce']) && wp_verify_nonce($_POST['ai_chatbot_settings_nonce'], 'ai_chatbot_settings')) {
        update_option('ai_chatbot_telegram_token', sanitize_text_field($_POST['ai_chatbot_telegram_token']));
        update_option('ai_chatbot_telegram_chat_id', sanitize_text_field($_POST['ai_chatbot_telegram_chat_id']));
        echo '<div class="notice notice-success"><p>Настройки сохранены.</p></div>';
    }

    // Получение текущих значений
    $telegram_token = get_option('ai_chatbot_telegram_token', '');
    $telegram_chat_id = get_option('ai_chatbot_telegram_chat_id', '');
    ?>

    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('ai_chatbot_settings', 'ai_chatbot_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ai_chatbot_telegram_token">Telegram Bot Token</label>
                    </th>
                    <td>
                        <input type="text" id="ai_chatbot_telegram_token" 
                               name="ai_chatbot_telegram_token" 
                               value="<?php echo esc_attr($telegram_token); ?>" 
                               class="regular-text">
                        <p class="description">
                            Введите токен вашего Telegram бота. Его можно получить у @BotFather.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ai_chatbot_telegram_chat_id">Telegram Chat ID</label>
                    </th>
                    <td>
                        <input type="text" id="ai_chatbot_telegram_chat_id" 
                               name="ai_chatbot_telegram_chat_id" 
                               value="<?php echo esc_attr($telegram_chat_id); ?>" 
                               class="regular-text">
                        <p class="description">
                            Введите ID чата, куда будут приходить уведомления. 
                            Можно получить, написав боту @userinfobot.
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" 
                       class="button button-primary" 
                       value="Сохранить изменения">
            </p>
        </form>
    </div>
    <?php
}
