from playwright.sync_api import sync_playwright
import os
import subprocess
import time
import sys

def run_test(page):
    screenshot_dir = "test/screenshots"
    os.makedirs(screenshot_dir, exist_ok=True)

    env = os.environ.copy()
    env["DB_NAME"] = ":memory:"
    server_process = subprocess.Popen(
        ["php", "-S", "localhost:8089", "test/Integration/verify_notifications_ui.php"],
        env=env
    )
    time.sleep(2)

    try:
        page.goto("http://localhost:8089")
        page.wait_for_timeout(1000)

        # 1. Open notifications
        bell = page.locator("button:has(svg path[d*='M15 17h5'])")
        bell.click()
        page.wait_for_selector("text=Notifications", state="visible")
        page.screenshot(path=f"{screenshot_dir}/notif_01_open.png")
        print("Dropdown opened")

        # 2. Click bell again to close (Expected to FAIL currently)
        bell.click()
        page.wait_for_timeout(500)
        is_visible = page.locator("text=Notifications").is_visible()
        page.screenshot(path=f"{screenshot_dir}/notif_02_click_bell_again.png")
        if is_visible:
            print("Dropdown still visible after clicking bell again (Current behavior)")
        else:
            print("Dropdown closed after clicking bell again")

        # 3. Ensure it's open for next tests
        if not is_visible:
            bell.click()
            page.wait_for_selector("text=Notifications", state="visible")

        # 4. Click outside to close
        page.click("#outside")
        page.wait_for_timeout(500)
        is_visible = page.locator("text=Notifications").is_visible()
        page.screenshot(path=f"{screenshot_dir}/notif_03_click_outside.png")
        if is_visible:
            print("Dropdown still visible after clicking outside")
        else:
            print("Dropdown closed after clicking outside")

        # 5. Ensure it's open for Escape test
        if not is_visible:
            bell.click()
            page.wait_for_selector("text=Notifications", state="visible")

        # 6. Press Escape to close (Expected to FAIL currently)
        page.keyboard.press("Escape")
        page.wait_for_timeout(500)
        is_visible = page.locator("text=Notifications").is_visible()
        page.screenshot(path=f"{screenshot_dir}/notif_04_escape.png")
        if is_visible:
            print("Dropdown still visible after pressing Escape")
        else:
            print("Dropdown closed after pressing Escape")

    finally:
        server_process.terminate()

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()
        try:
            run_test(page)
        finally:
            context.close()
            browser.close()
