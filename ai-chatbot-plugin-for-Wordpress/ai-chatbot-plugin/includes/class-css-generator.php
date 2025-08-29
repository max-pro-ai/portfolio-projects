<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_ChatBot_CSS_Generator {
    private $styles = [];
    private $options = [];

    public function __construct($options = []) {
        $this->load_options($options);
    }

    private function load_options($options = []) {
        // Опции из базы
        $db_options = [
            'color_scheme' => get_option('ai_chatbot_color_scheme', 'default'),
            'primary_color' => get_option('ai_chatbot_primary_color', '#667eea'),
            'secondary_color' => get_option('ai_chatbot_secondary_color', '#764ba2'),
            'bot_name_color' => get_option('ai_chatbot_bot_name_color', '#000000'),
            'font_family' => get_option('ai_chatbot_font_family', 'system-default'),
            'font_size' => get_option('ai_chatbot_font_size', 14),
            'margin' => get_option('ai_chatbot_margin', 20),
            'widget_size' => get_option('ai_chatbot_widget_size', 60),
            'avatar_size' => get_option('ai_chatbot_avatar_size', 40),
        ];
        // Если переданы опции — они имеют приоритет
        $this->options = array_merge($db_options, $options);
    }
    
    public function generate() {
        $this->add_base_styles();
        $this->add_color_styles();
        $this->add_size_styles();
        $this->add_font_styles();
        
        return implode("\n", $this->styles);
    }
    
    private function add_base_styles() {
        $this->styles[] = ".ai-chatbot-container {
            position: fixed;
            bottom: {$this->options['margin']}px;
            right: {$this->options['margin']}px;
            z-index: 9999;
        }";
    }
    
    private function add_color_styles() {
        // Основные цвета и градиент
        $gradient = '';
        if ($this->options['color_scheme'] === 'custom') {
            $gradient = "background: linear-gradient(135deg, {$this->options['primary_color']} 0%, {$this->options['secondary_color']} 100%);";
        } else {
            $colors = array(
                'default' => array('#667eea', '#764ba2'),
                'blue' => array('#2563eb', '#1d4ed8'),
                'green' => array('#059669', '#047857'),
                'purple' => array('#7c3aed', '#5b21b6')
            );
            if (isset($colors[$this->options['color_scheme']])) {
                $gradient = "background: linear-gradient(135deg, {$colors[$this->options['color_scheme']][0]} 0%, {$colors[$this->options['color_scheme']][1]} 100%);";
            }
        }

        $this->styles[] = ".ai-chatbot-toggle, .ai-chatbot-header, .ai-chatbot-send, .ai-chatbot-message.user .ai-chatbot-message-content { 
            {$gradient}
        }";

        // Цвет имени бота
        $this->styles[] = ".ai-chatbot-header h3 {
            color: {$this->options['bot_name_color']};
        }";
    }
    
    private function add_size_styles() {
        $this->styles[] = ".ai-chatbot-toggle {
            width: {$this->options['widget_size']}px;
            height: {$this->options['widget_size']}px;
        }";
        
        $this->styles[] = ".ai-chatbot-avatar {
            width: {$this->options['avatar_size']}px;
            height: {$this->options['avatar_size']}px;
        }";
    }
    
    private function add_font_styles() {
        $font_family = $this->get_font_family();
        $this->styles[] = ".ai-chatbot-container {
            font-family: {$font_family};
            font-size: {$this->options['font_size']}px;
        }";
    }
    
    private function get_font_family() {
        $fonts = [
            'system-default' => '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif',
            'roboto' => 'Roboto, sans-serif',
            'open-sans' => '"Open Sans", sans-serif',
            'lato' => 'Lato, sans-serif'
        ];
        
        return $fonts[$this->options['font_family']] ?? $fonts['system-default'];
    }
    
    public function save() {
        // Принудительно обновляем опции из базы данных перед генерацией
        $this->load_options();
        
        $css = $this->generate();
        $upload_dir = wp_upload_dir();
        $css_dir = $upload_dir['basedir'] . '/ai-chatbot';
        
        // Создаем директорию если её нет
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        // Используем временную метку для предотвращения кэширования
        $timestamp = time();
        $css_file = $css_dir . "/ai-chatbot-{$timestamp}.css";
        file_put_contents($css_file, $css);
        
        // Удаляем старые файлы стилей
        $files = glob($css_dir . '/ai-chatbot-*.css');
        foreach ($files as $file) {
            if ($file !== $css_file && is_file($file)) {
                unlink($file);
            }
        }
        
        // Обновляем версию стилей для принудительного обновления
        $style_version = time();
        update_option('ai_chatbot_style_version', $style_version);
        
        return wp_upload_dir()['baseurl'] . "/ai-chatbot/ai-chatbot-{$timestamp}.css?v={$style_version}";
    }
}
