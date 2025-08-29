jQuery(document).ready(function($) {
    // Обработчик всех изменений в форме настроек
    $('.ai-chatbot-settings-form input, .ai-chatbot-settings-form select').on('change input', function() {
        var $input = $(this);
        var setting = $input.attr('name').replace('ai_chatbot_', '');
        var value = $input.val();
        
        // Собираем все текущие настройки
        var settings = {};
        $('.ai-chatbot-settings-form').serializeArray().forEach(function(item) {
            settings[item.name.replace('ai_chatbot_', '')] = item.value;
        });
        
        // Применяем изменения сразу к виджету и предпросмотру
        applySettingsToWidget(settings);
        
        // Сохраняем настройки через AJAX
        saveSettings(settings);
    });

    function applySettingsToWidget(settings) {
        // Применяем к предпросмотру и живому виджету
        var $targets = $('.ai-chatbot-preview, .ai-chatbot-container');
        
        $targets.each(function() {
            var $container = $(this);
            
            // Применяем отступы
            if (settings.margin) {
                $container.css({
                    'bottom': settings.margin + 'px',
                    'right': settings.margin + 'px'
                });
            }
            
            // Применяем цвета
            var gradient = '';
            if (settings.color_scheme === 'custom') {
                gradient = `linear-gradient(135deg, ${settings.primary_color} 0%, ${settings.secondary_color} 100%)`;
            }
            
            if (gradient) {
                $container.find('.ai-chatbot-toggle, .ai-chatbot-header, .ai-chatbot-send, .ai-chatbot-message.user .ai-chatbot-message-content')
                    .css('background', gradient);
            }
            
            // Цвет имени бота
            if (settings.bot_name_color) {
                $container.find('.ai-chatbot-header h3').css('color', settings.bot_name_color);
            }
            
            // Размер виджета
            if (settings.widget_size) {
                $container.find('.ai-chatbot-toggle').css({
                    'width': settings.widget_size + 'px',
                    'height': settings.widget_size + 'px'
                });
            }
            
            // Размер окна
            if (settings.window_size) {
                var windowSize = {
                    'small': { width: '300px', height: '400px' },
                    'default': { width: '380px', height: '500px' },
                    'large': { width: '450px', height: '600px' }
                };
                if (windowSize[settings.window_size]) {
                    $container.find('.ai-chatbot-window').css(windowSize[settings.window_size]);
                }
            }
            
            // Размер аватара
            if (settings.avatar_size) {
                $container.find('.ai-chatbot-avatar').css({
                    'width': settings.avatar_size + 'px',
                    'height': settings.avatar_size + 'px'
                });
            }
            
            // Шрифт
            if (settings.font_family && settings.font_size) {
                const fonts = {
                    'system-default': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    'roboto': 'Roboto, sans-serif',
                    'open-sans': '"Open Sans", sans-serif',
                    'lato': 'Lato, sans-serif'
                };
                
                $container.css({
                    'font-family': fonts[settings.font_family] || fonts['system-default'],
                    'font-size': settings.font_size + 'px'
                });
            }
        });
    }

    // Функция для сохранения настроек через AJAX
    function saveSettings(settings) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_save_realtime_settings',
                nonce: ai_chatbot_admin.nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    // Принудительно обновляем все виджеты на странице
                    $('.ai-chatbot-container').each(function() {
                        applySettingsToWidget(settings);
                    });
                }
            }
        });
    }
});
