# WordPress Telegram Bot

## Overview
An automated Telegram bot that enables posting content directly to WordPress websites. The bot provides a conversational interface for creating posts with text and images, managing content, and publishing directly from Telegram.

## Features
- Create WordPress posts via Telegram
- Upload and attach images to posts
- Custom URL slug generation
- Markdown support for post content
- Preview posts before publishing
- Secure WordPress API integration
- Image gallery support
- Error handling and validation
- User authorization system

## Technologies Used
- Python 3.8+
- python-telegram-bot
- WordPress REST API
- aiohttp for async HTTP requests
- Base64 encoding for media
- JWT authentication

## Prerequisites
- WordPress website with REST API enabled
- WordPress application password
- Telegram Bot Token
- Python 3.8 or higher

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/wordpress-telegram-bot.git
cd wordpress-telegram-bot
```

2. Create and activate virtual environment:
```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

3. Install dependencies:
```bash
pip install -r requirements.txt
```

4. Configure environment variables:
Create a `.env` file with the following:
```env
TELEGRAM_BOT_TOKEN=your_bot_token
WORDPRESS_URL=your_wordpress_url
WORDPRESS_USERNAME=your_username
WORDPRESS_APP_PASSWORD=your_app_password
ALLOWED_USERS=user_id1,user_id2
```

## Usage

1. Start the bot:
```bash
python src/post_bot.py
```

2. In Telegram:
- Start a conversation with `/start`
- Follow the prompts to create a post:
  1. Enter post title
  2. Write post content
  3. Add images (optional)
  4. Set URL slug
  5. Preview and publish

## Bot Commands
- `/start` - Begin creating a new post
- `/cancel` - Cancel current operation
- `/done` - Finish adding images

## Security Features
- User whitelist system
- Secure credential storage
- WordPress application passwords
- HTTPS enforcement
- File type validation
- Size limit checks

## Project Structure
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

## Error Handling
The bot includes comprehensive error handling for:
- Network issues
- WordPress API errors
- File upload problems
- User input validation
- Authentication failures

## Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

## License
MIT License - see the [LICENSE](LICENSE) file for details
