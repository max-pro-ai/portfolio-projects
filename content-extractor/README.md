Main Content Extractor for Technical Documentation

This Python script uses Selenium to automatically read and save the main content from a technical documentation website. It is designed to collect informative text blocks that can later be used as a knowledge base for artificial intelligence systems or chatbots.

What this program does
Launches a Chrome browser and opens a specified documentation webpage.

Scans the HTML block #fern-docs > main > div > article, which typically contains the core content.

Detects changes when you navigate between pages manually.

Automatically saves each updated content block to a text file (main_content_log.txt).

Keeps running in the background while you browse the site manually.

How to use
Make sure you have the correct version of ChromeDriver for your Chrome browser.

Install the Selenium library:

bash
Копіювати
Редагувати
pip install selenium
Edit the script to set the correct paths to chromedriver.exe and chrome.exe.

Run the script:

bash
Копіювати
Редагувати
python script.py
Use the browser manually — every time the main content changes, it will be saved automatically.

Output
All collected content blocks are written into main_content_log.txt. Each block is separated by a header and delimiter line.

Example:

csharp
Копіювати
Редагувати
==================================================
Block #1
[Text from the first page]

==================================================
Block #2
[Text from the second page]
Use cases
Creating datasets for AI and NLP training.

Scraping technical documentation for offline processing.

Feeding structured data into chatbot knowledge bases.


