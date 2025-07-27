# WordPress Telegram Бот

## Огляд
Автоматизований Telegram бот, який дозволяє публікувати контент безпосередньо на WordPress сайти. Бот надає зручний інтерфейс для створення постів з текстом та зображеннями, керування контентом та публікації безпосередньо з Telegram.

## Можливості
- Створення WordPress постів через Telegram
- Завантаження та прикріплення зображень до постів
- Генерація власних URL-слагів
- Підтримка Markdown для контенту
- Попередній перегляд постів перед публікацією
- Безпечна інтеграція з WordPress API
- Підтримка галерей зображень
- Обробка помилок та валідація
- Система авторизації користувачів

## Використані технології
- Python 3.8+
- python-telegram-bot
- WordPress REST API
- aiohttp для асинхронних HTTP запитів
- Base64 кодування для медіа
- JWT аутентифікація

## Передумови
- WordPress сайт з увімкненим REST API
- WordPress пароль додатку
- Telegram Bot Token
- Python 3.8 або вище

## Встановлення

1. Клонуйте репозиторій:
```bash
git clone https://github.com/yourusername/wordpress-telegram-bot.git
cd wordpress-telegram-bot
```

2. Створіть та активуйте віртуальне середовище:
```bash
python -m venv venv
source venv/bin/activate  # На Windows: venv\Scripts\activate
```

3. Встановіть залежності:
```bash
pip install -r requirements.txt
```

4. Налаштуйте змінні середовища:
Створіть файл `.env` з наступним вмістом:
```env
TELEGRAM_BOT_TOKEN=your_bot_token
WORDPRESS_URL=your_wordpress_url
WORDPRESS_USERNAME=your_username
WORDPRESS_APP_PASSWORD=your_app_password
ALLOWED_USERS=user_id1,user_id2
```

## Використання

1. Запустіть бота:
```bash
python src/post_bot.py
```

2. В Telegram:
- Почніть розмову з `/start`
- Слідуйте підказкам для створення поста:
  1. Введіть заголовок поста
  2. Напишіть текст поста
  3. Додайте зображення (опціонально)
  4. Встановіть URL-слаг
  5. Перегляньте та опублікуйте

## Команди бота
- `/start` - Почати створення нового поста
- `/cancel` - Скасувати поточну операцію
- `/done` - Завершити додавання зображень

## Функції безпеки
- Система білого списку користувачів
- Безпечне зберігання облікових даних
- WordPress паролі додатків
- Обов'язкове HTTPS
- Валідація типів файлів
- Перевірка розміру файлів

## Структура проекту
```
wordpress-telegram-bot/
├── src/
│   ├── post_bot.py
│   ├── wp_handler.py
│   └── utils.py
├── requirements.txt
├── .env.example
└── README.md
```

## Обробка помилок
Бот включає комплексну обробку помилок для:
- Мережевих проблем
- Помилок WordPress API
- Проблем завантаження файлів
- Валідації користувацького вводу
- Помилок аутентифікації

## Участь у розробці
Ми вітаємо ваш внесок! Будь ласка, не соромтеся надсилати Pull Request.

## Ліцензія
MIT License - дивіться файл [LICENSE](LICENSE) для деталей
