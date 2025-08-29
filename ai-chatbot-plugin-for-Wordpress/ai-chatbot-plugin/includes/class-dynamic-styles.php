<?php
class AI_Chatbot_Dynamic_Styles {
    private $options;

    public function __construct() {
        $this->options = get_option('ai_chatbot_options', array());
    }

    public function generate_css_variables() {
        // Получаем значения из настроек или используем значения по умолчанию
        $primary_color = isset($this->options['primary_color']) ? $this->options['primary_color'] : '#667eea';
        $secondary_color = isset($this->options['secondary_color']) ? $this->options['secondary_color'] : '#764ba2';
        $font_size = isset($this->options['font_size']) ? intval($this->options['font_size']) : 14;
        $toggle_size = isset($this->options['toggle_size']) ? intval($this->options['toggle_size']) : 60;
        $window_width = isset($this->options['window_width']) ? intval($this->options['window_width']) : 380;
        $window_height = isset($this->options['window_height']) ? intval($this->options['window_height']) : 500;
        $spacing = isset($this->options['spacing']) ? intval($this->options['spacing']) : 20;
        $border_radius = isset($this->options['border_radius']) ? intval($this->options['border_radius']) : 16;
        $font_family = isset($this->options['font_family']) ? $this->options['font_family'] : '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif';

        // Генерируем CSS переменные
        $css = "
        :root {
            --ai-chatbot-primary: {$primary_color};
            --ai-chatbot-secondary: {$secondary_color};
            --ai-chatbot-gradient: linear-gradient(135deg, var(--ai-chatbot-primary) 0%, var(--ai-chatbot-secondary) 100%);
            --ai-chatbot-font-family: {$font_family};
            --ai-chatbot-font-size: {$font_size}px;
            --ai-chatbot-toggle-size: {$toggle_size}px;
            --ai-chatbot-window-width: {$window_width}px;
            --ai-chatbot-window-height: {$window_height}px;
            --ai-chatbot-spacing: {$spacing}px;
            --ai-chatbot-border-radius: {$border_radius}px;
            --ai-chatbot-shadow: 0 4px 12px " . $this->hex2rgba($primary_color, 0.4) . ";
        }";

        // Добавляем специфичные стили для мобильных устройств
        $css .= "
        @media (max-width: 480px) {
            :root {
                --ai-chatbot-window-width: calc(100% - " . ($spacing * 2) . "px);
                --ai-chatbot-window-height: calc(100vh - " . ($toggle_size + $spacing * 3) . "px);
                --ai-chatbot-spacing: " . max(12, $spacing) . "px;
            }
        }";

        return $css;
    }

    public function hex2rgba($color, $opacity = false) {
        $default = 'rgba(0,0,0,0.4)';
        
        if(empty($color)) return $default;
        
        if ($color[0] == '#' ) {
            $color = substr($color, 1);
        }
        
        if (strlen($color) == 6) {
            $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
        } elseif (strlen($color) == 3) {
            $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
        } else {
            return $default;
        }
        
        $rgb = array_map('hexdec', $hex);
        
        if($opacity){
            if(abs($opacity) > 1) $opacity = 1.0;
            return 'rgba('.implode(",",$rgb).','.$opacity.')';
        }
        
        return 'rgb('.implode(",",$rgb).')';
    }

    public function output_dynamic_styles() {
        echo '<style id="ai-chatbot-dynamic-styles">' . $this->generate_css_variables() . '</style>';
    }

    public function save_dynamic_styles() {
        $upload_dir = wp_upload_dir();
        $css_dir = $upload_dir['basedir'] . '/ai-chatbot';
        
        // Создаем директорию если её нет
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }

        $css_file = $css_dir . '/dynamic-styles.css';
        file_put_contents($css_file, $this->generate_css_variables());
        
        return $upload_dir['baseurl'] . '/ai-chatbot/dynamic-styles.css';
    }
}
