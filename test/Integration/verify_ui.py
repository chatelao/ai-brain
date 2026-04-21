from playwright.sync_api import sync_playwright
import os
import subprocess
import time

def run_cuj(page):
    # Start the PHP server
    env = os.environ.copy()
    env["DB_NAME"] = ":memory:"
    server_process = subprocess.Popen(
        ["php", "-S", "localhost:8088", "test/Integration/verify_project_ui.php"],
        env=env
    )
    time.sleep(2)  # Wait for server to start

    try:
        page.goto("http://localhost:8088")
        page.wait_for_timeout(1000)

        # Check if we see the project details
        # The title should contain the repo name
        page.wait_for_selector("h1:has-text('owner/repo')")
        page.wait_for_timeout(500)

        # Check if tasks are visible
        page.wait_for_selector("text=Sample Issue #1")
        page.wait_for_timeout(500)

        # Click "Run Agent" on the first task
        # Note: In our mock, triggerAgent returns a simulated response or error
        # Since GOOGLE_JULES_API_KEY is likely missing, it will show an error response
        page.click("button:has-text('Run Agent') >> nth=0")
        page.wait_for_timeout(1000)

        # Take screenshot of the result (including agent response)
        page.screenshot(path="/home/jules/verification/screenshots/project_details.png")
        page.wait_for_timeout(1000)

    finally:
        server_process.terminate()

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()
        try:
            run_cuj(page)
        finally:
            context.close()
            browser.close()
