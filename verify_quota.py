from playwright.sync_api import sync_playwright
import os

def verify_quota_display():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to the mock header page
        url = "http://localhost:8080/mock_header.php"
        print(f"Navigating to {url}")
        page.goto(url)

        # Check if the quota is visible
        quota_text = page.locator("span.ml-1.text-gray-500").inner_text()
        print(f"Found quota text: {quota_text}")

        if "(90/100)" in quota_text:
            print("Quota (90/100) found correctly!")
        else:
            print(f"ERROR: Quota (90/100) not found. Found: {quota_text}")

        # Take a screenshot
        os.makedirs("/home/jules/verification", exist_ok=True)
        page.screenshot(path="/home/jules/verification/quota_display.png")
        print("Screenshot saved to /home/jules/verification/quota_display.png")

        browser.close()

if __name__ == "__main__":
    verify_quota_display()
