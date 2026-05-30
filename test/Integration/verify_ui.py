from playwright.sync_api import sync_playwright
import os
import subprocess
import time

def run_cuj(page):
    # Ensure screenshots directory exists
    screenshot_dir = "test/screenshots"
    os.makedirs(screenshot_dir, exist_ok=True)

    # Helper function to run server
    def start_server(script):
        env = os.environ.copy()
        env["DB_NAME"] = ":memory:"
        return subprocess.Popen(
            ["php", "-S", "localhost:8089", script],
            env=env
        )

    # Test Project UI
    print("Testing Project UI...")
    server_process = start_server("test/Integration/verify_project_ui.php")
    time.sleep(2)
    try:
        # Avoid root and legacy loops by going to a specific page with legacy=1
        page.goto("http://localhost:8089/project.php?id=1&legacy=1")
        page.wait_for_selector("h1", timeout=5000)
        page.screenshot(path=f"{screenshot_dir}/01_project_details.png")
        print(f"Saved: {screenshot_dir}/01_project_details.png")
    finally:
        server_process.terminate()

    # Test Settings UI
    print("Testing Settings UI...")
    server_process = start_server("test/Integration/verify_settings_ui.php")
    time.sleep(2)
    try:
        page.goto("http://localhost:8089/settings.php?legacy=1")
        page.wait_for_selector("h1", timeout=5000)
        page.screenshot(path=f"{screenshot_dir}/04_settings_general.png")
        print(f"Saved: {screenshot_dir}/04_settings_general.png")
    finally:
        server_process.terminate()

    # Test Task UI
    print("Testing Task UI...")
    server_process = start_server("test/Integration/verify_task_ui.php")
    time.sleep(2)
    try:
        page.goto("http://localhost:8089/task.php?id=1&legacy=1")
        page.wait_for_selector("h1", timeout=5000)
        page.screenshot(path=f"{screenshot_dir}/06_task_details.png")
        print(f"Saved: {screenshot_dir}/06_task_details.png")
    finally:
        server_process.terminate()

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()
        try:
            run_cuj(page)
        finally:
            context.close()
            browser.close()
