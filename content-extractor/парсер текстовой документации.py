from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time
import json
import os

# Настройка Chrome и ChromeDriver
service = Service(executable_path="D:\\chromedriver-win64\\chromedriver-win64\\chromedriver.exe")
chrome_binary_path = "D:\\chromedriver-win64\\chrome-win64\\chrome-win64\\chrome.exe"

options = webdriver.ChromeOptions()
options.binary_location = chrome_binary_path
options.add_argument('--start-maximized')

driver = webdriver.Chrome(service=service, options=options)
wait = WebDriverWait(driver, 10)

def extract_main_content():
    try:
        main_content = wait.until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "#fern-docs > main > div > article"))
        )
        return main_content.text
    except Exception as e:
        print(f"Ошибка при получении содержимого: {str(e)}")
        return None

try:
    base_url = "https://docs.paradex.trade/ws/web-socket-channels/"
    driver.get(base_url)
    
    print("Откройте нужную страницу и управляйте ею вручную.")
    print("Программа автоматически сохранит новый текст из #fern-docs > main > div > article при каждом изменении.")
    print("Для завершения просто закройте окно браузера.")

    collected_data = []
    last_content = None

    try:
        while True:
            try:
                content = extract_main_content()
                if content and content != last_content:
                    collected_data.append(content)
                    print("Обнаружено новое содержимое, скопировано.")
                    last_content = content
                    # Сохраняем сразу после каждого изменения
                    try:
                        script_dir = os.path.dirname(os.path.abspath(__file__))
                        file_path = os.path.join(script_dir, "main_content_log.txt")
                        with open(file_path, "w", encoding="utf-8") as f:
                            for idx, text in enumerate(collected_data, 1):
                                f.write(f"\n{'='*50}\n")
                                f.write(f"Блок #{idx}\n")
                                f.write(f"{text}\n")
                        print(f"Содержимое успешно сохранено в файл: {file_path}")
                    except Exception as save_err:
                        print(f"Ошибка при сохранении файла: {save_err}")
                time.sleep(1)
            except Exception as e:
                print(f"Ошибка при работе с браузером: {e}")
                time.sleep(2)
    except KeyboardInterrupt:
        print("Завершение работы по запросу пользователя (Ctrl+C).")

except Exception as e:
    print(f"Критическая ошибка: {str(e)}")
finally:
    try:
        driver.quit()
    except Exception:
        pass
    print("Парсинг завершен")