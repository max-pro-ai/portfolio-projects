<?php
// Подключаем динамические стили
add_action('wp_head', 'ai_chatbot_output_dynamic_styles');
add_action('admin_head', 'ai_chatbot_output_dynamic_styles');

function ai_chatbot_output_dynamic_styles() {
    require_once plugin_dir_path(__FILE__) . 'class-dynamic-styles.php';
    $styles = new AI_Chatbot_Dynamic_Styles();
    $styles->output_dynamic_styles();
}

// Обновляем стили при сохранении настроек
add_action('update_option_ai_chatbot_options', 'ai_chatbot_update_dynamic_styles', 10, 2);

function ai_chatbot_update_dynamic_styles($old_value, $new_value) {
    require_once plugin_dir_path(__FILE__) . 'class-dynamic-styles.php';
    $styles = new AI_Chatbot_Dynamic_Styles();
    $styles->save_dynamic_styles();
}
