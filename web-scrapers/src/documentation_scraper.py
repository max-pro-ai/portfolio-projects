#!/usr/bin/env python3
"""
Documentation Scraper
A tool for automated extraction of documentation content from web pages.
"""

import os
import json
import time
from datetime import datetime
from typing import Optional, Dict, Any

from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, WebDriverException
from webdriver_manager.chrome import ChromeDriverManager

class DocumentationScraper:
    """Main scraper class for extracting documentation content."""
    
    def __init__(self, config: Dict[str, Any] = None):
        """
        Initialize the scraper with configuration.
        
        Args:
            config: Dictionary containing configuration parameters
        """
        self.config = config or {
            'selector': "#fern-docs > main > div > article",
            'wait_time': 10,
            'check_interval': 1
        }
        self.driver = self._setup_driver()
        self.wait = WebDriverWait(self.driver, self.config['wait_time'])
        self._setup_output_directory()

    def _setup_driver(self) -> webdriver.Chrome:
        """
        Set up and configure Chrome WebDriver.
        
        Returns:
            Configured Chrome WebDriver instance
        """
        options = Options()
        options.add_argument('--start-maximized')
        options.add_argument('--headless')  # Run in headless mode for production
        
        service = Service(ChromeDriverManager().install())
        return webdriver.Chrome(service=service, options=options)

    def _setup_output_directory(self) -> None:
        """Create output directory if it doesn't exist."""
        script_dir = os.path.dirname(os.path.abspath(__file__))
        self.output_dir = os.path.join(script_dir, "output")
        os.makedirs(self.output_dir, exist_ok=True)

    def extract_content(self) -> Optional[str]:
        """
        Extract content from the page using configured selector.
        
        Returns:
            Extracted content or None if extraction failed
        """
        try:
            element = self.wait.until(
                EC.presence_of_element_located((By.CSS_SELECTOR, self.config['selector']))
            )
            return element.text
        except TimeoutException:
            print(f"❌ Timeout waiting for content at selector: {self.config['selector']}")
            return None
        except WebDriverException as e:
            print(f"❌ Error extracting content: {str(e)}")
            return None

    def save_content(self, content: str, index: int) -> None:
        """
        Save extracted content to a file.
        
        Args:
            content: Content to save
            index: Block index for the content
        """
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"content_block_{index}_{timestamp}.txt"
        filepath = os.path.join(self.output_dir, filename)
        
        try:
            with open(filepath, "w", encoding="utf-8") as f:
                f.write(f"{'='*50}\n")
                f.write(f"Block #{index} - {datetime.now()}\n")
                f.write(f"{'='*50}\n\n")
                f.write(content)
            print(f"✅ Content saved to: {filepath}")
        except IOError as e:
            print(f"❌ Error saving content: {str(e)}")

    def monitor_page(self, url: str) -> None:
        """
        Monitor a page for content changes.
        
        Args:
            url: URL of the page to monitor
        """
        try:
            print(f"🔄 Starting page monitoring: {url}")
            self.driver.get(url)
            
            content_blocks = []
            last_content = None
            block_index = 1

            while True:
                try:
                    current_content = self.extract_content()
                    if current_content and current_content != last_content:
                        print(f"📝 New content detected (Block #{block_index})")
                        content_blocks.append(current_content)
                        self.save_content(current_content, block_index)
                        last_content = current_content
                        block_index += 1
                    time.sleep(self.config['check_interval'])
                except KeyboardInterrupt:
                    print("\n🛑 Monitoring stopped by user")
                    break
                except Exception as e:
                    print(f"⚠️ Error during monitoring: {str(e)}")
                    time.sleep(self.config['check_interval'])

        except Exception as e:
            print(f"❌ Critical error: {str(e)}")
        finally:
            self.cleanup()

    def cleanup(self) -> None:
        """Clean up resources."""
        try:
            self.driver.quit()
        except Exception:
            pass
        print("👋 Scraper shutdown complete")

def main():
    """Main function to run the scraper."""
    # Configuration
    config = {
        'selector': "#fern-docs > main > div > article",
        'wait_time': 10,
        'check_interval': 1
    }

    # Target URL
    url = "https://docs.paradex.trade/ws/web-socket-channels/"

    try:
        scraper = DocumentationScraper(config)
        scraper.monitor_page(url)
    except KeyboardInterrupt:
        print("\n👋 Program terminated by user")
    except Exception as e:
        print(f"❌ Program error: {str(e)}")

if __name__ == "__main__":
    main()
