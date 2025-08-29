jQuery(document).ready(function($) {
    let isOpen = false;
    let messageCount = 0;
    let inactivityTimer = null;
    let lastBotMessageTime = null;
    let INACTIVITY_TIMEOUT = ai_chatbot_options.inactivity_timeout; // Время неактивности из настроек админ-панели
    let SESSION_ID = null; // Идентификатор клиентской сессии для серверного сохранения
    
    // Проверяем валидность таймаута и устанавливаем fallback
    if (!INACTIVITY_TIMEOUT || INACTIVITY_TIMEOUT < 1000) {
        console.warn('AI ChatBot: Некорректный таймаут:', INACTIVITY_TIMEOUT, 'устанавливаем 60000 мс (1 минута)');
        INACTIVITY_TIMEOUT = 60000; // 1 минута по умолчанию
    }
    
    // Отладочная информация
    console.log('AI ChatBot: Инициализация с таймаутом:', INACTIVITY_TIMEOUT, 'мс');
    console.log('AI ChatBot: Тип таймаута:', typeof INACTIVITY_TIMEOUT);
    console.log('AI ChatBot: Таймаут в секундах:', INACTIVITY_TIMEOUT / 1000);
    
    // Инициализация чата
    function initChatBot() {
        const $container = $('.ai-chatbot-container');
        const $toggle = $('.ai-chatbot-toggle');
        const $window = $('.ai-chatbot-window');
        const $input = $('.ai-chatbot-input');
        const $send = $('.ai-chatbot-send');
        const $messages = $('.ai-chatbot-messages');
        const $close = $('.ai-chatbot-close');
        
        // Инициализация идентификатора сессии для серверного сохранения
        SESSION_ID = getOrCreateSessionId();

        // Загружаем историю сообщений
        const hasHistory = loadState();
        
        // Показываем приветственное сообщение только если нет истории
        if (!hasHistory) {
            setTimeout(() => {
                addMessage(ai_chatbot_ajax.welcome_message, 'bot');
            }, 1000);
        }
        
        // Открытие/закрытие чата
        $toggle.on('click', function() {
            toggleChat();
        });
        
        $close.on('click', function() {
            closeChat();
        });
        
        // Отправка сообщения
        $send.on('click', function() {
            sendMessage();
        });
        
        // Отправка по Enter
        $input.on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Авто-размер textarea и контроль пробелов
        $input.on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            
            let value = $(this).val();
            
            // Убираем лишние пробелы в начале
            if (value.match(/^\s+/)) {
                value = value.trimLeft();
            }
            
            // Делаем первую букву заглавной
            if (value.length > 0) {
                value = value.charAt(0).toUpperCase() + value.slice(1);
                
                // Сохраняем позицию курсора
                const cursorPos = this.selectionStart;
                $(this).val(value);
                
                // Восстанавливаем позицию курсора
                this.setSelectionRange(cursorPos, cursorPos);
            }
        });
    }
    
    function toggleChat() {
        const $toggle = $('.ai-chatbot-toggle');
        const $window = $('.ai-chatbot-window');
        
        if (isOpen) {
            closeChat();
        } else {
            openChat();
        }
    }
    
    function openChat() {
        const $toggle = $('.ai-chatbot-toggle');
        const $window = $('.ai-chatbot-window');
        const $notification = $('.ai-chatbot-notification');
        const $container = $('.ai-chatbot-container');
        
        isOpen = true;
        $toggle.addClass('active');
        $window.addClass('active');
        $container.addClass('chat-open');
        $notification.hide();
        
        // Фокус на поле ввода
        setTimeout(() => {
            $('.ai-chatbot-input').focus();
        }, 300);
        
        // Скролл к последнему сообщению
        scrollToBottom();
        
        // Если в чате уже есть сообщения, запускаем таймер неактивности
        if (messageCount > 0) {
            lastBotMessageTime = new Date();
            startInactivityTimer();
        }
    }
    
    function closeChat() {
        const $toggle = $('.ai-chatbot-toggle');
        const $window = $('.ai-chatbot-window');
        const $container = $('.ai-chatbot-container');
        
        isOpen = false;
        $toggle.removeClass('active');
        $window.removeClass('active');
        $container.removeClass('chat-open');
    }
    
    // Отправка сообщения
    function sendMessage() {
        const $input = $('.ai-chatbot-input');
        const message = $input.val().trim();
        
        if (!message) return;
        
        // Добавить сообщение пользователя
        addMessage(message, 'user');
        // Немедленно сохраняем историю на сервере (без немедленной отправки)
        persistChatHistory(false);
        $input.val('');
        $input.css('height', 'auto');
        
        // Сразу после последнего сообщения пользователя: запускаем таймер неактивности
        lastBotMessageTime = new Date();
        startInactivityTimer();

        // Показать индикатор печати
        showTyping();
        
        // Очищаем таймер при отправке нового сообщения
        if (inactivityTimer) {
            clearTimeout(inactivityTimer);
        }
        
        // Отправить запрос к API
        $.ajax({
            url: ai_chatbot_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_chatbot_message',
                message: message,
                nonce: ai_chatbot_ajax.nonce
            },
            success: handleBotResponse,
            error: function() {
                hideTyping();
                addMessage('Не удалось отправить сообщение. Проверьте подключение к интернету.', 'bot', true);
                
                // Запускаем таймер также при сетевых ошибках
                lastBotMessageTime = new Date();
                startInactivityTimer();
            }
        });
    }
    
    function handleBotResponse(response) {
        hideTyping();
        if (response.success) {
            let botMessage = response.data;
            addMessage(botMessage.trim(), 'bot');
        } else {
            addMessage(response.data || 'Произошла ошибка. Попробуйте еще раз.', 'bot', true);
        }
        // После любого ответа сохраняем историю на сервере для надежности
        persistChatHistory(false);

        // Важно: не перезапускаем таймер на ответ бота, таймер считается от последнего сообщения пользователя
    }
    
    // Функция для запуска таймера неактивности
    function startInactivityTimer() {
        // Очищаем предыдущий таймер
        if (inactivityTimer) {
            clearTimeout(inactivityTimer);
            console.log('AI ChatBot: Предыдущий таймер очищен');
        }
        
        // Проверяем что таймаут валидный
        if (!INACTIVITY_TIMEOUT || INACTIVITY_TIMEOUT < 1000) {
            console.error('AI ChatBot: Некорректный таймаут:', INACTIVITY_TIMEOUT);
            return;
        }
        
        // Запускаем новый таймер
        inactivityTimer = setTimeout(() => {
            console.log('AI ChatBot: Таймер неактивности сработал, отправляем историю чата');
            console.log('AI ChatBot: Количество сообщений в истории:', messageCount);
            console.log('AI ChatBot: Время срабатывания:', new Date().toLocaleString());
            sendChatHistory();
            inactivityTimer = null;
        }, INACTIVITY_TIMEOUT);
        
        console.log('AI ChatBot: Таймер неактивности запущен на', INACTIVITY_TIMEOUT, 'мс');
        console.log('AI ChatBot: Время последнего сообщения:', lastBotMessageTime);
        console.log('AI ChatBot: Таймер сработает в:', new Date(Date.now() + INACTIVITY_TIMEOUT).toLocaleString());
        console.log('AI ChatBot: Текущее время:', new Date().toLocaleString());
    }
    
    function addMessage(text, sender, isError = false, animate = true) {
        const $messages = $('.ai-chatbot-messages');
        const timestamp = new Date().toLocaleTimeString('ru-RU', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const messageHtml = `<div class="ai-chatbot-message ${sender}${!animate ? ' no-animation' : ''}"><div class="ai-chatbot-message-content ${isError ? 'error' : ''}">${formatMessage(text)}</div><div class="ai-chatbot-message-time">${timestamp}</div></div>`;
        
        const $message = $(messageHtml);
        $messages.append($message);
        
        if (animate) {
            // Анимация только для новых сообщений
            $message.hide().fadeIn(300);
            scrollToBottom();
            
            // Показать уведомление если чат закрыт
            if (!isOpen && sender === 'bot') {
                showNotification();
            }
        } else {
            // Для загруженных сообщений просто прокручиваем вниз без анимации
            $messages.scrollTop($messages[0].scrollHeight);
        }
        
        messageCount++;
    }
    
    function formatMessage(text) {
        // Простое форматирование текста и удаление лишних переносов
        return text
            .trim()
            .replace(/\s*\n\s*/g, '<br>') // Убираем пробелы вокруг переносов
            .replace(/(<br>){3,}/g, '<br><br>') // Ограничиваем количество переносов двумя
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>');
    }
    
    function showTyping() {
        const $messages = $('.ai-chatbot-messages');
        const typingHtml = `
            <div class="ai-chatbot-message bot typing-indicator">
                <div class="ai-chatbot-typing">
                    <div class="ai-chatbot-typing-dots">
                        <div class="ai-chatbot-typing-dot"></div>
                        <div class="ai-chatbot-typing-dot"></div>
                        <div class="ai-chatbot-typing-dot"></div>
                    </div>
                </div>
            </div>
        `;
        
        $messages.append(typingHtml);
        scrollToBottom();
    }
    
    function hideTyping() {
        $('.typing-indicator').remove();
    }
    
    function scrollToBottom() {
        const $messages = $('.ai-chatbot-messages');
        $messages.animate({
            scrollTop: $messages[0].scrollHeight
        }, 300);
    }
    
    function showNotification() {
        let $notification = $('.ai-chatbot-notification');
        
        if ($notification.length === 0) {
            $notification = $('<div class="ai-chatbot-notification">1</div>');
            $('.ai-chatbot-toggle').append($notification);
        }
        
        $notification.show();
    }
    
    // Анимации при наведении
    function addHoverEffects() {
        $('.ai-chatbot-toggle').hover(
            function() {
                if (!isOpen) {
                    $(this).css('transform', 'scale(1.05)');
                }
            },
            function() {
                if (!isOpen) {
                    $(this).css('transform', 'scale(1)');
                }
            }
        );
    }
    
    // Автоматическое приветствие через некоторое время
    function scheduleWelcome() {
        setTimeout(() => {
            if (!isOpen && messageCount === 0) {
                showNotification();
            }
        }, 30000); // 30 секунд
    }
    
    // Сохранение состояния в локальном хранилище
    function saveState() {
        try {
            if (typeof(Storage) !== "") {
                const messages = getAllMessages();
                
                // Проверяем доступность localStorage
                try {
                    localStorage.setItem('test', 'test');
                    localStorage.removeItem('test');
                } catch (e) {
                    console.warn('localStorage недоступен, пробуем sessionStorage');
                    sessionStorage.setItem('ai_chatbot_messages', JSON.stringify(messages));
                    sessionStorage.setItem('ai_chatbot_last_visit', Date.now());
                    return;
                }
                
                // Если localStorage доступен, используем его
                localStorage.setItem('ai_chatbot_messages', JSON.stringify(messages));
                localStorage.setItem('ai_chatbot_last_visit', Date.now());
                
                // Дублируем в sessionStorage для надежности
                sessionStorage.setItem('ai_chatbot_messages', JSON.stringify(messages));
                sessionStorage.setItem('ai_chatbot_last_visit', Date.now());
            }
        } catch (e) {
            console.error('Ошибка при сохранении истории:', e);
        }
    }
    
    function loadState() {
        try {
            if (typeof(Storage) === "") {
                return false;
            }

            let savedMessages, lastVisit;
            
            // Пробуем загрузить из localStorage
            try {
                savedMessages = localStorage.getItem('ai_chatbot_messages');
                lastVisit = localStorage.getItem('ai_chatbot_last_visit');
            } catch (e) {
                console.warn('localStorage недоступен, пробуем sessionStorage');
                // Если localStorage недоступен, пробуем sessionStorage
                savedMessages = sessionStorage.getItem('ai_chatbot_messages');
                lastVisit = sessionStorage.getItem('ai_chatbot_last_visit');
            }
            
            if (savedMessages && lastVisit) {
                // Проверяем, прошло ли меньше 30 минут
                const thirtyMinutes = 30 * 60 * 1000; // 30 минут в миллисекундах
                const now = Date.now();
                
                if (now - parseInt(lastVisit) <= thirtyMinutes) {
                    try {
                        const messages = JSON.parse(savedMessages);
                        if (Array.isArray(messages) && messages.length > 0) {
                            messages.forEach(msg => {
                                if (msg && msg.text) {
                                    // Очищаем текст от лишних пробелов и переносов перед добавлением
                                    const cleanText = msg.text.replace(/<br\s*\/?>/g, '\n').trim();
                                    addMessage(cleanText, msg.sender, msg.isError, false);
                                }
                            });
                            // Возвращаем true если история была загружена
                            return true;
                        }
                    } catch (e) {
                        console.error('Ошибка при загрузке истории:', e);
                        // Очищаем поврежденные данные
                        clearStorage();
                    }
                } else {
                    // Если прошло больше 30 минут, очищаем историю
                    clearStorage();
                }
            }
        } catch (e) {
            console.error('Ошибка при проверке хранилища:', e);
        }
        
        // Возвращаем false если истории нет или она устарела
        return false;
    }
    
    // Функция для очистки всех хранилищ
    function clearStorage() {
        try {
            localStorage.removeItem('ai_chatbot_messages');
            localStorage.removeItem('ai_chatbot_last_visit');
        } catch (e) {
            console.warn('Ошибка при очистке localStorage:', e);
        }
        
        try {
            sessionStorage.removeItem('ai_chatbot_messages');
            sessionStorage.removeItem('ai_chatbot_last_visit');
        } catch (e) {
            console.warn('Ошибка при очистке sessionStorage:', e);
        }
    }
    
    // Получить все сообщения чата
    function getAllMessages() {
        const messages = [];
        $('.ai-chatbot-message').each(function() {
            const $msg = $(this);
            const text = $msg.find('.ai-chatbot-message-content').text();
            const time = $msg.find('.ai-chatbot-message-time').text();
            const sender = $msg.hasClass('user') ? 'user' : 'bot';
            
            // Добавляем timestamp для корректной работы email-handler
            const now = new Date();
            messages.push({
                sender: sender,
                text: text,
                time: time,
                timestamp: Math.floor(now.getTime() / 1000) // Unix timestamp в секундах
            });
        });
        return messages;
    }

    // Создать/получить UUID для сессии чата
    function getOrCreateSessionId() {
        try {
            let sid = null;
            try {
                sid = localStorage.getItem('ai_chatbot_session_id');
            } catch (e) {
                // ignore
            }
            if (!sid) {
                sid = generateUUIDv4();
                try {
                    localStorage.setItem('ai_chatbot_session_id', sid);
                } catch (e) {
                    try { sessionStorage.setItem('ai_chatbot_session_id', sid); } catch (e2) {}
                }
            }
            return sid;
        } catch (e) {
            return generateUUIDv4();
        }
    }

    function generateUUIDv4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Функция для отправки истории чата
    function sendChatHistory() {
        const chatHistory = getAllMessages();
        if (chatHistory.length > 0 && hasUserMessage(chatHistory)) {
            console.log('AI ChatBot: Отправка истории чата на email...', chatHistory);
            console.log('AI ChatBot: Количество сообщений:', chatHistory.length);
            console.log('AI ChatBot: AJAX URL:', ai_chatbot_ajax.ajax_url);
            console.log('AI ChatBot: Nonce:', ai_chatbot_ajax.nonce);
            
            // Отправляем историю на email
            $.ajax({
                url: ai_chatbot_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_send_email',
                    history: JSON.stringify(chatHistory),
                    nonce: ai_chatbot_ajax.nonce,
                    session_id: SESSION_ID,
                    // Клиент уже выждал таймаут неактивности — просим сервер отправить сразу
                    send_now: 1
                },
                beforeSend: function() {
                    console.log('AI ChatBot: Отправка AJAX запроса...');
                },
                success: function(response) {
                    console.log('AI ChatBot: AJAX ответ получен:', response);
                    if (response.success) {
                        console.log('AI ChatBot: История чата успешно отправлена на email');
                        // Показываем уведомление пользователю
                        showNotification('История чата отправлена на email', 'success');
                    } else {
                        console.error('AI ChatBot: Ошибка при отправке истории чата -', response.data);
                        showNotification('Ошибка отправки истории: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AI ChatBot: Ошибка при отправке истории чата -', error);
                    console.error('AI ChatBot: XHR статус:', xhr.status);
                    console.error('AI ChatBot: XHR ответ:', xhr.responseText);
                    showNotification('Ошибка отправки истории: ' + error, 'error');
                }
            });
        } else {
            console.log('AI ChatBot: История чата пуста или нет сообщений пользователя, отправка не требуется');
        }
    }

    // Фоновое сохранение истории на сервере без немедленной отправки
    function persistChatHistory(sendNow) {
        const chatHistory = getAllMessages();
        if (chatHistory.length === 0 || !hasUserMessage(chatHistory)) return;
        $.ajax({
            url: ai_chatbot_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_chatbot_send_email',
                history: JSON.stringify(chatHistory),
                nonce: ai_chatbot_ajax.nonce,
                session_id: SESSION_ID,
                send_now: sendNow ? 1 : 0
            },
            timeout: 5000
        });
    }

    // Проверка наличия хотя бы одного сообщения пользователя
    function hasUserMessage(messages) {
        if (!Array.isArray(messages)) return false;
        for (let i = 0; i < messages.length; i++) {
            if (messages[i] && messages[i].sender === 'user' && messages[i].text && messages[i].text.trim().length > 0) {
                return true;
            }
        }
        return false;
    }
    
    // Функция для показа уведомлений
    function showNotification(message, type = 'info') {
        const notificationClass = type === 'success' ? 'ai-chatbot-notification-success' : 
                                 type === 'error' ? 'ai-chatbot-notification-error' : 
                                 'ai-chatbot-notification-info';
        
        const $notification = $(`<div class="ai-chatbot-notification ${notificationClass}">${message}</div>`);
        $('body').append($notification);
        
        // Показываем уведомление
        $notification.fadeIn(300);
        
        // Скрываем через 5 секунд
        setTimeout(() => {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Обработка ошибок
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        console.log('AI ChatBot Error: ' + msg);
        return false;
    };
    
    // Обработка анимаций виджета
    function initWidgetAnimations() {
        const animation = ai_chatbot_options.animation;
        const $toggle = $('.ai-chatbot-toggle');
        
        if (animation === 'bounce') {
            setInterval(() => {
                if (!isOpen) {
                    $toggle.addClass('bounce');
                    setTimeout(() => $toggle.removeClass('bounce'), 1000);
                }
            }, 5000);
        } else if (animation === 'pulse') {
            setInterval(() => {
                if (!isOpen) {
                    $toggle.addClass('pulse');
                    setTimeout(() => $toggle.removeClass('pulse'), 1000);
                }
            }, 5000);
        } else if (animation === 'shake') {
            setInterval(() => {
                if (!isOpen) {
                    $toggle.addClass('shake');
                    setTimeout(() => $toggle.removeClass('shake'), 1000);
                }
            }, 5000);
        }
    }
    
    // Применение динамических стилей
    function applyDynamicStyles() {
        const options = ai_chatbot_options;
        const $container = $('.ai-chatbot-container');
        const $toggle = $('.ai-chatbot-toggle');
        const $window = $('.ai-chatbot-window');
        const $avatar = $('.ai-chatbot-avatar');
        const $messages = $('.ai-chatbot-messages');
        const $input = $('.ai-chatbot-input');

        // Размер виджета
        $toggle.css({
            width: options.widget_size + 'px',
            height: options.widget_size + 'px'
        });

        // Размер окна чата
        if (options.window_size === 'small') {
            $window.css({
                width: '300px',
                height: '400px'
            });
        } else if (options.window_size === 'large') {
            $window.css({
                width: '450px',
                height: '600px'
            });
        }

        // Размер аватара
        $avatar.css({
            width: options.avatar_size + 'px',
            height: options.avatar_size + 'px'
        });

        // Цветовая схема и градиент
        const scheme = options.color_scheme || 'default';
        let primaryColor, secondaryColor;
        
        if (scheme === 'custom') {
            primaryColor = options.primary_color;
            secondaryColor = options.secondary_color;
        } else {
            const colors = {
                'default': ['#667eea', '#764ba2'],
                'blue': ['#2563eb', '#1d4ed8'],
                'green': ['#059669', '#047857'],
                'purple': ['#7c3aed', '#5b21b6']
            };
            [primaryColor, secondaryColor] = colors[scheme] || colors['default'];
        }
        
        // Применяем градиент
        const gradient = `linear-gradient(135deg, ${primaryColor} 0%, ${secondaryColor} 100%)`;
        const elements = '.ai-chatbot-toggle, .ai-chatbot-header, .ai-chatbot-send, .ai-chatbot-message.user .ai-chatbot-message-content';
        $(elements).css('background', gradient);
        
        // Делаем иконку отправки белой
        $('.ai-chatbot-send svg').css('fill', '#ffffff');
        
        // Применяем цвет имени бота
        if (options.bot_name_color) {
            $('.ai-chatbot-header h3').css('color', options.bot_name_color);
        }

        // Применяем отступы
        if (options.margin) {
            $container.css({
                'bottom': options.margin + 'px',
                'right': options.margin + 'px'
            });
        }

        // Шрифт
        const fonts = {
            'system-default': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'roboto': 'Roboto, sans-serif',
            'open-sans': '"Open Sans", sans-serif',
            'lato': 'Lato, sans-serif'
        };

        if (options.font_family !== 'system-default') {
            // Загрузка шрифта через Google Fonts если выбран не системный шрифт
            const fontFamily = options.font_family.replace('-', '+');
            $('head').append(`<link href="https://fonts.googleapis.com/css2?family=${fontFamily}:wght@400;500;600&display=swap" rel="stylesheet">`);
        }

        $container.css({
            'font-family': fonts[options.font_family],
            'font-size': options.font_size + 'px'
        });

        // Обновляем placeholder в поле ввода и стили для input
        $input.attr('placeholder', options.text.placeholder)
              .css({
                  'padding': '10px 15px',
                  'margin': '10px',
                  'min-height': '40px',
                  'line-height': '20px',
                  'width': 'calc(100% - 20px)',
                  'background-color': '#f8f9fa',
                  'border': '1px solid #e9ecef',
                  'border-radius': '8px',
                  'resize': 'none',
                  'outline': 'none',
                  'box-shadow': '0 2px 4px rgba(0,0,0,0.05)',
                  'caret-color': primaryColor,
                  'font-size': '14px',
                  'color': '#495057',
                  'overflow-y': 'auto',
                  'white-space': 'pre-wrap',
                  'word-wrap': 'break-word'
              })
              .val('')  // Очищаем значение при инициализации
              .focus(function() {
                  // Убираем лишние пробелы при фокусе
                  const value = $(this).val().trim();
                  $(this).val(value);
              });
    }
    
    // Инициализация после загрузки DOM
    initChatBot();
    addHoverEffects();
    scheduleWelcome();
    initWidgetAnimations();
    applyDynamicStyles();
    
    // Автосохранение каждые 30 секунд
    setInterval(saveState, 30000);
    
    // Сохранение состояния перед закрытием страницы
    $(window).on('beforeunload', function() {
        saveState();
        // Пытаемся отправить историю перед закрытием страницы максимально надежно
        try {
            const chatHistory = getAllMessages();
            if (chatHistory.length > 0 && hasUserMessage(chatHistory) && navigator.sendBeacon) {
                const formData = new FormData();
                formData.append('action', 'ai_chatbot_send_email');
                formData.append('history', JSON.stringify(chatHistory));
                formData.append('nonce', ai_chatbot_ajax.nonce);
                formData.append('session_id', SESSION_ID);
                // Не отправляем немедленно при закрытии вкладки — пусть решает сервер по таймеру
                formData.append('send_now', '0');
                navigator.sendBeacon(ai_chatbot_ajax.ajax_url, formData);
            } else {
                persistChatHistory(false);
            }
        } catch (e) {}
    });
    
    // Очистка истории по кнопке (добавьте кнопку в HTML если нужно)
    $('.ai-chatbot-clear-history').on('click', function() {
        if (confirm('Вы уверены, что хотите очистить историю чата?')) {
            localStorage.removeItem('ai_chatbot_messages');
            localStorage.removeItem('ai_chatbot_last_visit');
            $('.ai-chatbot-messages').empty();
            messageCount = 0;
        }
    });
});

// Дополнительные утилиты
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Плавная анимация появления
function animateIn(element) {
    $(element).css({
        'opacity': '0',
        'transform': 'translateY(20px)'
    }).animate({
        'opacity': '1',
        'transform': 'translateY(0)'
    }, 300);
}
// Функция инициализации статуса "Мы онлайн!"
function initOnlineStatus() {
    // Создаем элемент статуса если его нет
    const container = document.querySelector('.ai-chatbot-container');
    if (!container) return;
    
    // Проверяем, есть ли уже элемент статуса
    let statusElement = container.querySelector('.ai-chatbot-online-status');
    
    if (!statusElement) {
        // Создаем элемент статуса
        statusElement = document.createElement('div');
        statusElement.className = 'ai-chatbot-online-status';
        statusElement.textContent = 'Ми онлайн!';
        container.appendChild(statusElement);
    }
    
    // Показываем статус сразу при загрузке страницы
    setTimeout(() => {
        showOnlineStatus();
    }, 100);
}

// Функция показа статуса "Мы онлайн!"
function showOnlineStatus() {
    const statusElement = document.querySelector('.ai-chatbot-online-status');
    
    if (!statusElement) return;
    
    // Показываем статус
    statusElement.classList.add('show');
    
    // Скрываем через 10 секунд
    setTimeout(() => {
        statusElement.classList.add('hide');
        statusElement.classList.remove('show');
        
        // Полностью убираем классы через время анимации
        setTimeout(() => {
            statusElement.classList.remove('hide');
        }, 300);
    }, 10000);
}

// Инициализируем при загрузке DOM
document.addEventListener('DOMContentLoaded', function() {
    initOnlineStatus();
});

// Для случая, если скрипт загружается после DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOnlineStatus);
} else {
    initOnlineStatus();
}

// Альтернативный вариант с jQuery (если используется jQuery)
$(document).ready(function() {
    // Показываем статус "Мы онлайн!" сразу после загрузки
    setTimeout(() => {
        showOnlineStatusJQuery();
    }, 100);
});

function showOnlineStatusJQuery() {
    const $container = $('.ai-chatbot-container');
    
    // Создаем элемент статуса если его нет
    if ($('.ai-chatbot-online-status').length === 0) {
        $container.append('<div class="ai-chatbot-online-status">Мы онлайн!</div>');
    }
    
    const $status = $('.ai-chatbot-online-status');
    
    // Показываем статус
    $status.addClass('show');
    
    // Скрываем через 10 секунд
    setTimeout(() => {
        $status.addClass('hide').removeClass('show');
        setTimeout(() => {
            $status.removeClass('hide');
        }, 300);
    }, 10000);
}