from playwright.sync_api import sync_playwright
import os
import subprocess
import time

def run_cuj(page):
    # Ensure screenshots directory exists
    screenshot_dir = "test/screenshots"
    os.makedirs(screenshot_dir, exist_ok=True)

    # Start the PHP server
    env = os.environ.copy()
    env["DB_NAME"] = ":memory:"
    server_process = subprocess.Popen(
        ["php", "-S", "localhost:8088", "test/Integration/verify_project_ui.php"],
        env=env
    )
    time.sleep(2)  # Wait for server to start

    try:
        # Step 1: Navigate to Project Details
        page.goto("http://localhost:8088")
        page.wait_for_timeout(1000)

        # Check if we see the project details
        page.wait_for_selector("h1:has-text('owner/repo')")
        page.screenshot(path=f"{screenshot_dir}/01_project_details.png")
        print(f"Saved screenshot: {screenshot_dir}/01_project_details.png")

        # Step 2: Verify tasks are visible
        page.wait_for_selector("text=Sample Issue #1")
        page.screenshot(path=f"{screenshot_dir}/02_tasks_list.png")
        print(f"Saved screenshot: {screenshot_dir}/02_tasks_list.png")

        # Step 3: Click on the first task to go to details
        page.click("text=#101")
        page.wait_for_timeout(1000)

        # Step 4: Click "Run Agent" on the task details page
        page.click("button:has-text('Run Agent')")
        page.wait_for_timeout(2000) # Wait for agent simulation

        # Take screenshot of the result (including agent response)
        page.screenshot(path=f"{screenshot_dir}/03_agent_triggered.png")
        print(f"Saved screenshot: {screenshot_dir}/03_agent_triggered.png")

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
