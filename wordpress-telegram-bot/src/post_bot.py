"""
WordPress Telegram Bot
A bot for creating and publishing WordPress posts via Telegram.
"""

import asyncio
from datetime import datetime
from typing import Dict, Any

from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import (
    Application,
    CommandHandler,
    MessageHandler,
    CallbackQueryHandler,
    ConversationHandler,
    filters
)

from wp_handler import WordPressHandler
from utils import load_config, sanitize_slug, validate_image, format_preview

# Conversation states
TITLE, CONTENT, PHOTOS, URL_SLUG, CONFIRM = range(5)

class WordPressTelegramBot:
    def __init__(self):
        """Initialize the bot with configuration."""
        self.config = load_config()
        self.wp_handler = WordPressHandler(
            self.config['wp_url'],
            self.config['wp_username'],
            self.config['wp_password']
        )
        self.user_posts: Dict[int, Dict[str, Any]] = {}

    async def start(self, update: Update, context: Any) -> int:
        """
        Start the post creation process.
        
        Args:
            update: Telegram update
            context: Callback context
            
        Returns:
            Next conversation state
        """
        user_id = update.effective_user.id
        if user_id not in self.config['allowed_users']:
            await update.message.reply_text("❌ You don't have access to this bot")
            return ConversationHandler.END

        self.user_posts[user_id] = {
            'title': '',
            'content': '',
            'photo_ids': [],
            'url_slug': '',
            'user_id': user_id
        }
        
        await update.message.reply_text(
            "🚀 Let's create a new post for your website.\n\n"
            "📝 Please enter the post title:"
        )
        return TITLE

    async def get_title(self, update: Update, context: Any) -> int:
        """Handle post title input."""
        user_id = update.effective_user.id
        self.user_posts[user_id]['title'] = update.message.text.strip()
        
        await update.message.reply_text(
            f"✅ Title saved: {self.user_posts[user_id]['title']}\n\n"
            "📄 Now write the main post content:"
        )
        return CONTENT

    async def get_content(self, update: Update, context: Any) -> int:
        """Handle post content input."""
        user_id = update.effective_user.id
        self.user_posts[user_id]['content'] = update.message.text
        
        keyboard = [
            [
                InlineKeyboardButton("📷 Add photos", callback_data="add_photos"),
                InlineKeyboardButton("➡️ Skip photos", callback_data="skip_photos")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        await update.message.reply_text(
            "✅ Content saved!\n\n"
            "🖼 Would you like to add photos to the post?",
            reply_markup=reply_markup
        )
        return PHOTOS

    async def handle_photo_choice(self, update: Update, context: Any) -> int:
        """Handle photo addition choice."""
        query = update.callback_query
        await query.answer()
        
        if query.data == "add_photos":
            await query.edit_message_text(
                "📷 Send your photos one by one.\n"
                "When finished, use /done"
            )
            return PHOTOS
        elif query.data == "skip_photos":
            await self.ask_for_url(query, context)
            return URL_SLUG

    async def get_photos(self, update: Update, context: Any) -> int:
        """Handle photo uploads."""
        user_id = update.effective_user.id
        
        if update.message.text and update.message.text.lower() == "/done":
            await self.ask_for_url_text(update, context)
            return URL_SLUG
            
        if update.message.photo:
            photo = update.message.photo[-1]
            
            # Validate photo
            error = validate_image(photo.file_id, photo.file_size)
            if error:
                await update.message.reply_text(error)
                return PHOTOS
                
            self.user_posts[user_id]['photo_ids'].append(photo.file_id)
            await update.message.reply_text(
                f"✅ Photo #{len(self.user_posts[user_id]['photo_ids'])} added!\n"
                "Send more photos or use /done to continue."
            )
            return PHOTOS
            
        await update.message.reply_text(
            "❌ Please send a photo or use /done"
        )
        return PHOTOS

    async def ask_for_url(self, query: Any, context: Any) -> None:
        """Prompt for URL slug."""
        await query.edit_message_text(
            "🔗 Now create a URL for the post (in English, no spaces):\n"
            "Example: my-awesome-post"
        )

    async def ask_for_url_text(self, update: Any, context: Any) -> None:
        """Prompt for URL slug (text version)."""
        await update.message.reply_text(
            "🔗 Now create a URL for the post (in English, no spaces):\n"
            "Example: my-awesome-post"
        )

    async def get_url_slug(self, update: Update, context: Any) -> int:
        """Handle URL slug input."""
        user_id = update.effective_user.id
        post_data = self.user_posts[user_id]
        post_data['url_slug'] = sanitize_slug(update.message.text.strip())

        preview_text = format_preview(
            post_data['title'],
            post_data['content'],
            f"{self.config['wp_url']}/{post_data['url_slug']}",
            len(post_data['photo_ids'])
        )
        
        await update.message.reply_text(preview_text, parse_mode='Markdown')

        keyboard = [
            [
                InlineKeyboardButton("✅ Publish", callback_data="publish"),
                InlineKeyboardButton("❌ Cancel", callback_data="cancel")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        await update.message.reply_text(
            "Review your post and choose:",
            reply_markup=reply_markup
        )
        return CONFIRM

    async def handle_confirmation(self, update: Update, context: Any) -> int:
        """Handle final confirmation and post creation."""
        query = update.callback_query
        await query.answer()
        user_id = query.from_user.id

        if query.data == "publish":
            await query.edit_message_text("⏳ Publishing post to website...")
            try:
                post_data = self.user_posts[user_id]
                
                # Upload images
                media_info = []
                for file_id in post_data['photo_ids']:
                    file = await context.bot.get_file(file_id)
                    async with context.bot.get_file_download(file.file_id) as file_content:
                        image_data = await file_content.read()
                        upload_result = await self.wp_handler.upload_image(
                            image_data,
                            f"post-image-{datetime.now().timestamp()}.jpg"
                        )
                        if upload_result:
                            media_info.append(upload_result)

                # Create post
                result = await self.wp_handler.create_post(
                    post_data['title'],
                    post_data['content'],
                    post_data['url_slug'],
                    media_info
                )

                if result:
                    await query.edit_message_text(
                        f"🎉 Post published successfully!\n\n"
                        f"🔗 Link: {result['link']}\n"
                        f"📊 Post ID: {result['id']}"
                    )
                else:
                    await query.edit_message_text(
                        "❌ Error while publishing post"
                    )
            except Exception as e:
                await query.edit_message_text(f"❌ Error: {str(e)}")
        elif query.data == "cancel":
            await query.edit_message_text("❌ Post creation cancelled")

        if user_id in self.user_posts:
            del self.user_posts[user_id]
        return ConversationHandler.END

    async def cancel(self, update: Update, context: Any) -> int:
        """Cancel post creation."""
        user_id = update.effective_user.id
        if user_id in self.user_posts:
            del self.user_posts[user_id]
        await update.message.reply_text("❌ Post creation cancelled")
        return ConversationHandler.END

    def run(self):
        """Start the bot."""
        application = Application.builder().token(self.config['bot_token']).build()

        # Create conversation handler
        conv_handler = ConversationHandler(
            entry_points=[CommandHandler('start', self.start)],
            states={
                TITLE: [
                    MessageHandler(
                        filters.TEXT & ~filters.COMMAND,
                        self.get_title
                    )
                ],
                CONTENT: [
                    MessageHandler(
                        filters.TEXT & ~filters.COMMAND,
                        self.get_content
                    )
                ],
                PHOTOS: [
                    CallbackQueryHandler(self.handle_photo_choice),
                    MessageHandler(filters.PHOTO, self.get_photos),
                    MessageHandler(
                        filters.TEXT & filters.Regex("(?i)^/done$"),
                        self.get_photos
                    )
                ],
                URL_SLUG: [
                    MessageHandler(
                        filters.TEXT & ~filters.COMMAND,
                        self.get_url_slug
                    )
                ],
                CONFIRM: [
                    CallbackQueryHandler(self.handle_confirmation)
                ]
            },
            fallbacks=[CommandHandler('cancel', self.cancel)],
            per_user=True,
            per_chat=True
        )

        application.add_handler(conv_handler)
        
        print("✅ Bot started! Press Ctrl+C to stop.")
        application.run_polling()

if __name__ == '__main__':
    bot = WordPressTelegramBot()
    bot.run()
