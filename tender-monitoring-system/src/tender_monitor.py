#!/usr/bin/env python3
"""
Tender Monitoring System
Monitors multiple tender platforms for specific keywords and sends notifications via Telegram.
"""

import os
import json
import asyncio
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from difflib import SequenceMatcher

from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException
from webdriver_manager.chrome import ChromeDriverManager
from bs4 import BeautifulSoup
from telegram import Bot
from dotenv import load_dotenv

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class TenderMonitor:
    def __init__(self):
        """Initialize the tender monitoring system."""
        self._load_config()
        self._setup_driver()
        self._setup_telegram()
        self.last_messages = {}

    def _load_config(self) -> None:
        """Load configuration from files and environment."""
        load_dotenv()
        
        # Load environment variables
        self.telegram_token = os.getenv('TELEGRAM_BOT_TOKEN')
        self.telegram_chat_id = os.getenv('TELEGRAM_CHAT_ID')
        
        if not self.telegram_token or not self.telegram_chat_id:
            raise ValueError("Telegram configuration missing")
        
        # Load keywords
        with open('config/keywords.json', 'r', encoding='utf-8') as f:
            self.keywords = []
            keywords_data = json.load(f)
            for category in keywords_data.values():
                self.keywords.extend(category)
        
        # Load platform configurations
        with open('config/platforms.json', 'r', encoding='utf-8') as f:
            config = json.load(f)
            self.platforms = config['platforms']
            self.settings = config['settings']

    def _setup_driver(self) -> None:
        """Configure and initialize Selenium WebDriver."""
        options = Options()
        options.add_argument('--headless')
        options.add_argument('--no-sandbox')
        options.add_argument('--disable-dev-shm-usage')
        
        service = Service(ChromeDriverManager().install())
        self.driver = webdriver.Chrome(service=service, options=options)
        self.wait = WebDriverWait(self.driver, 10)

    def _setup_telegram(self) -> None:
        """Initialize Telegram bot."""
        self.bot = Bot(token=self.telegram_token)

    async def _extract_content(self, selector: str) -> Optional[str]:
        """
        Extract content from page using selector.
        
        Args:
            selector: CSS selector for content
            
        Returns:
            Extracted content or None if not found
        """
        try:
            element = self.wait.until(
                EC.presence_of_element_located((By.CSS_SELECTOR, selector))
            )
            return element.text
        except TimeoutException:
            logger.warning(f"Timeout waiting for selector: {selector}")
            return None
        except Exception as e:
            logger.error(f"Error extracting content: {str(e)}")
            return None

    def _similar(self, a: str, b: str) -> float:
        """
        Calculate similarity ratio between two strings.
        
        Args:
            a: First string
            b: Second string
            
        Returns:
            Similarity ratio between 0 and 1
        """
        return SequenceMatcher(None, a, b).ratio()

    def _should_send_message(
        self,
        keyword: str,
        content: str,
        current_time: datetime
    ) -> bool:
        """
        Check if message should be sent based on duplicates and timing.
        
        Args:
            keyword: Matched keyword
            content: Message content
            current_time: Current timestamp
            
        Returns:
            True if message should be sent, False otherwise
        """
        if keyword in self.last_messages:
            last_msg = self.last_messages[keyword]
            time_diff = current_time - last_msg['time']
            
            if (time_diff < timedelta(seconds=self.settings['message_expiration']) and 
                self._similar(last_msg['message'], content) > self.settings['duplicate_threshold']):
                return False
        return True

    def _update_last_message(
        self,
        keyword: str,
        content: str,
        current_time: datetime
    ) -> None:
        """
        Update last message tracking.
        
        Args:
            keyword: Matched keyword
            content: Message content
            current_time: Current timestamp
        """
        self.last_messages[keyword] = {
            'message': content,
            'time': current_time
        }

    async def _send_telegram_message(self, message: str) -> None:
        """
        Send message via Telegram.
        
        Args:
            message: Message to send
        """
        try:
            await self.bot.send_message(
                chat_id=self.telegram_chat_id,
                text=message,
                parse_mode='HTML'
            )
        except Exception as e:
            logger.error(f"Error sending Telegram message: {str(e)}")

    async def check_platform(self, platform: Dict[str, str]) -> None:
        """
        Check a single platform for tender matches.
        
        Args:
            platform: Platform configuration dictionary
        """
        try:
            self.driver.get(platform['url'])
            content = await self._extract_content(platform['selector'])
            
            if not content:
                return
            
            for keyword in self.keywords:
                if keyword.lower() in content.lower():
                    current_time = datetime.now()
                    
                    if self._should_send_message(keyword, content, current_time):
                        title = await self._extract_content(platform['title_selector'])
                        description = await self._extract_content(platform['description_selector'])
                        
                        message = (
                            f"🔍 <b>New Tender Found!</b>\n\n"
                            f"📍 Platform: {platform['name']}\n"
                            f"🏷 Keyword: {keyword}\n"
                            f"📋 Title: {title if title else 'N/A'}\n\n"
                            f"📝 Description: {description if description else 'N/A'}\n\n"
                            f"🔗 Link: {platform['url']}"
                        )
                        
                        await self._send_telegram_message(message)
                        self._update_last_message(keyword, content, current_time)
                        
        except Exception as e:
            logger.error(f"Error checking platform {platform['name']}: {str(e)}")

    async def monitor(self) -> None:
        """Main monitoring loop."""
        try:
            while True:
                logger.info("Starting platform checks...")
                
                for platform in self.platforms:
                    await self.check_platform(platform)
                    await asyncio.sleep(1)  # Prevent overwhelming platforms
                
                logger.info("Platform checks completed")
                await asyncio.sleep(self.settings['check_interval'])
                
        except KeyboardInterrupt:
            logger.info("Monitoring stopped by user")
        except Exception as e:
            logger.error(f"Critical error in monitoring: {str(e)}")
        finally:
            self.cleanup()

    def cleanup(self) -> None:
        """Clean up resources."""
        try:
            self.driver.quit()
        except Exception:
            pass
        logger.info("Monitoring system shutdown complete")

def main():
    """Main function to run the monitoring system."""
    try:
        monitor = TenderMonitor()
        asyncio.run(monitor.monitor())
    except Exception as e:
        logger.error(f"Program error: {str(e)}")

if __name__ == "__main__":
    main()
