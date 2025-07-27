# Selenium Setup Guide

## Prerequisites
1. Python 3.8 or higher
2. Google Chrome browser
3. ChromeDriver compatible with your Chrome version

## Installation Steps

### 1. Install Chrome Browser
If you don't have Chrome installed:
- Download from: https://www.google.com/chrome/
- Install following your OS instructions

### 2. Install Python Dependencies
```bash
pip install selenium webdriver-manager
```

### 3. ChromeDriver Setup
The script uses webdriver-manager which will automatically:
- Download the correct ChromeDriver version
- Manage ChromeDriver updates
- Handle path configuration

## Troubleshooting

### Common Issues

1. **ChromeDriver Version Mismatch**
   ```
   Solution: Update Chrome to latest version or specify version:
   ChromeDriverManager(version="specific_version").install()
   ```

2. **Permission Issues**
   ```
   Solution: Run with sudo or adjust file permissions:
   chmod +x /path/to/chromedriver
   ```

3. **Path Issues**
   ```
   Solution: Add ChromeDriver to system PATH or use absolute path
   ```

### Error Messages and Solutions

1. **"ChromeDriver executable needs to be in PATH"**
   - Use webdriver-manager (recommended)
   - Or add ChromeDriver to system PATH manually

2. **"Connection refused"**
   - Check if Chrome is installed
   - Verify firewall settings
   - Try running Chrome in non-headless mode for debugging

3. **"Session not created"**
   - Update Chrome and ChromeDriver to matching versions
   - Clear Chrome user data directory

## Best Practices

1. **Error Handling**
   - Always implement try-except blocks
   - Log errors appropriately
   - Clean up resources in finally blocks

2. **Resource Management**
   - Use context managers when possible
   - Always close browser sessions
   - Implement proper timeout handling

3. **Performance**
   - Use headless mode for production
   - Implement proper waits
   - Clean up temporary files

## Additional Resources
- [Selenium Documentation](https://www.selenium.dev/documentation/)
- [ChromeDriver Downloads](https://sites.google.com/chromium.org/driver/)
- [webdriver-manager PyPI](https://pypi.org/project/webdriver-manager/)
