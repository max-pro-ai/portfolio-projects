# Web Scraping Tools

## Overview
A collection of specialized web scraping tools built with Python and Selenium for automated data extraction. These tools are designed to efficiently scrape documentation and download images from various web sources.

## Tools Included

### 1. Documentation Scraper
- Automatically extracts and saves documentation content from web pages
- Monitors page changes in real-time
- Saves content in organized text files
- Supports custom content selectors

### 2. Image Downloader
- Downloads images from web pages using XPath selectors
- Supports batch downloading
- Maintains original image quality
- Handles different image formats

## Technologies Used
- Python 3.8+
- Selenium WebDriver
- BeautifulSoup4
- Chrome WebDriver
- Requests library

## Requirements
- Python 3.8 or higher
- Chrome browser
- ChromeDriver matching your Chrome version

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/web-scrapers.git
cd web-scrapers
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

4. Configure ChromeDriver:
- Download ChromeDriver matching your Chrome version
- Add ChromeDriver to your system PATH or specify path in scripts

## Usage

### Documentation Scraper
```bash
python src/documentation_scraper.py
```

### Image Downloader
```bash
python src/image_downloader.py
```

## Configuration
Each scraper can be configured by modifying the following parameters:
- Base URLs
- CSS/XPath selectors
- Output directories
- File naming patterns

## Project Structure
```
web-scrapers/
├── docs/
│   └── selenium_setup.md
├── src/
│   ├── documentation_scraper.py
│   └── image_downloader.py
├── requirements.txt
└── README.md
```

## Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

## License
MIT License - see the [LICENSE](LICENSE) file for details
