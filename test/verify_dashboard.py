from playwright.sync_api import Page, expect, sync_playwright
import os

def test_dashboard_unauthenticated(page: Page):
    # 1. Arrange: Go to the index page.
    page.goto("http://localhost:8080/index.php")

    # 2. Assert: Confirm we see the "Please Login" message and "Login with Google" button.
    expect(page.get_by_text("Please Login")).to_be_visible()
    expect(page.get_by_role("link", name="Login with Google")).to_be_visible()

    # 3. Screenshot: Capture the state for visual verification.
    os.makedirs("build/screenshots", exist_ok=True)
    page.screenshot(path="build/screenshots/dashboard_unauthenticated.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_dashboard_unauthenticated(page)
        finally:
            browser.close()
