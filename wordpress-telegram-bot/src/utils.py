"""
Utility functions for the WordPress Telegram Bot
"""

import os
from typing import List, Optional
from dotenv import load_dotenv

def load_config() -> dict:
    """
    Load configuration from environment variables.
    
    Returns:
        Dictionary containing configuration values
    """
    load_dotenv()
    
    # Required environment variables
    required_vars = [
        'TELEGRAM_BOT_TOKEN',
        'WORDPRESS_URL',
        'WORDPRESS_USERNAME',
        'WORDPRESS_APP_PASSWORD'
    ]
    
    # Check for missing required variables
    missing_vars = [var for var in required_vars if not os.getenv(var)]
    if missing_vars:
        raise ValueError(f"Missing required environment variables: {', '.join(missing_vars)}")
    
    # Parse allowed users
    allowed_users_str = os.getenv('ALLOWED_USERS', '')
    allowed_users = [int(uid.strip()) for uid in allowed_users_str.split(',') if uid.strip()]
    
    return {
        'bot_token': os.getenv('TELEGRAM_BOT_TOKEN'),
        'wp_url': os.getenv('WORDPRESS_URL'),
        'wp_username': os.getenv('WORDPRESS_USERNAME'),
        'wp_password': os.getenv('WORDPRESS_APP_PASSWORD'),
        'allowed_users': allowed_users
    }

def sanitize_slug(text: str) -> str:
    """
    Convert text to URL-friendly slug.
    
    Args:
        text: Input text to convert
        
    Returns:
        URL-friendly slug string
    """
    # Remove special characters and convert spaces to hyphens
    slug = ''.join(c if c.isalnum() or c == ' ' else '' for c in text.lower())
    return '-'.join(slug.split())

def validate_image(file_id: str, file_size: int) -> Optional[str]:
    """
    Validate image file before upload.
    
    Args:
        file_id: Telegram file ID
        file_size: File size in bytes
        
    Returns:
        Error message if validation fails, None if successful
    """
    # Check file size (max 5MB)
    if file_size > 5 * 1024 * 1024:
        return "❌ Image file too large (max 5MB)"
    
    return None

def format_preview(title: str, content: str, url: str, photo_count: int) -> str:
    """
    Format post preview message.
    
    Args:
        title: Post title
        content: Post content
        url: Post URL
        photo_count: Number of attached photos
        
    Returns:
        Formatted preview message
    """
    preview = (
        f"📋 **POST PREVIEW**\n\n"
        f"📝 Title: {title}\n"
        f"🔗 URL: {url}\n"
        f"🖼 Photos: {photo_count}\n\n"
        f"📄 **Content:**\n\n{content}"
    )
    return preview
