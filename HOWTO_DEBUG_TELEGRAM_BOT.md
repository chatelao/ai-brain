# How to Debug the Telegram Bot

This guide provides instructions for configuring, testing, and debugging the Telegram Bot integration in the Agent Control application.

## Architecture Overview

The Telegram Bot integration consists of the following components:

1.  **`src/frontend/telegram-webhook.php`**: The public-facing endpoint that receives webhook updates from Telegram. It performs the following:
    *   Identifies the user based on the `user_id` query parameter.
    *   Verifies the `X-Telegram-Bot-Api-Secret-Token` header against the user's configured webhook secret.
    *   Logs the request (headers, payload, and status code) to the `webhook_logs` table.
    *   Immediately acknowledges the request to Telegram (responding with "OK").
    *   Passes the update to the `TelegramWebhookHandler` for background processing.

2.  **`App\TelegramWebhookHandler` (`src/backend/TelegramWebhookHandler.php`)**: The logic layer that processes incoming updates.
    *   Parses commands (e.g., `/start`, `/start <token>`).
    *   Handles account linking by verifying tokens via the `User` model.
    *   Uses `TelegramService` to send responses.

3.  **`App\TelegramService` (`src/backend/TelegramService.php`)**: A service wrapper for the Telegram Bot API.
    *   Sends messages using the Guzzle HTTP client.
    *   Uses the user's custom Bot Token stored in the database.

4.  **`App\WebhookLogger` (`src/backend/WebhookLogger.php`)**: Utility for logging webhook interactions for debugging purposes.

## Configuration

### 1. Obtain a Bot Token
*   Message [@BotFather](https://t.me/botfather) on Telegram.
*   Use the `/newbot` command to create a bot and get your **API Token**.

### 2. Configure Agent Control
*   Navigate to the **Settings** page in the application.
*   In the **General** tab, find the **Telegram Custom Bot** section.
*   Enter your **Custom Bot Token**.
*   (Recommended) Enter a **Webhook Secret**. This is a string you choose that Telegram will send in the `X-Telegram-Bot-Api-Secret-Token` header to authorize the request.

## Webhook Setup

Telegram requires your webhook endpoint to be accessible via **HTTPS** with a valid SSL certificate.

### 1. Automated Registration
The application attempts to automatically register your webhook whenever you save your Telegram configuration in the **Settings** page.

*   If successful, you will see a success message.
*   If it fails (e.g., due to an invalid token or connectivity issue), a warning will appear with the specific error message from Telegram.

### 2. Webhook URL Format
If you need to verify or manually set your webhook, your specific URL is:
```
https://<your-domain>/telegram-webhook.php?user_id=<your_user_id>
```

### 3. Manual Registration (Fallback)
If automated registration fails, you can manually register your webhook using `curl`:

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
     -d "url=https://<your-domain>/telegram-webhook.php?user_id=<your_user_id>" \
     -d "secret_token=<YOUR_WEBHOOK_SECRET>"
```

Replace `<YOUR_BOT_TOKEN>`, `<your-domain>`, `<your_user_id>`, and `<YOUR_WEBHOOK_SECRET>` with your actual values.

## Account Linking

To receive notifications and control agents, you must link your Telegram account:

1.  The application generates a unique link in the dashboard or settings.
2.  The link typically looks like `https://t.me/<your_bot_username>?start=<token>`.
3.  Clicking this link opens your bot and sends the `/start <token>` command.
4.  The `TelegramWebhookHandler` processes this token to associate your Telegram `chat_id` with your user account in the database.

## Debugging Tools

### 1. Test Broadcast
In the **Settings > Notifications** tab, use the **Test Broadcast** button.
*   This sends a test message to all your enabled channels (including Telegram) without persisting it to the database.
*   It's the fastest way to verify that your Bot Token and Chat ID are working correctly.

### 2. Centralized Logs Page
The **Logs** page (`logs.php`) provides a comprehensive view of all system activity.

*   **API & Performance Logs**: Tracks outgoing calls to the Telegram API.
    *   **Status**: Check for `200` (Success) or `4xx/5xx` errors.
    *   **Duration**: Identifies slow responses (calls > 1.0s are highlighted).
    *   **Error Message**: Captures specific error descriptions returned by Telegram (e.g., "Unauthorized", "chat not found").
    *   **Security**: Bot Tokens are automatically redacted from error messages to prevent logs from leaking credentials.
*   **Webhook Logs**: Shows incoming updates from Telegram.
    *   Displays headers, raw payload, status code, and any processing errors.

### 3. Webhook Logging (Settings Page)
The **Settings > Logging** tab provides a quick view of the last 5 webhook calls.
*   **Status Codes**: `200` (Success), `401` (Secret mismatch), `400` (Invalid payload).
*   **View Payload**: Inspect raw headers and JSON payload to verify Telegram's delivery.

### 4. Manual Testing with `curl`
You can simulate a Telegram webhook post to test your configuration without using the actual Telegram API.

#### Test Unauthorized Access (Missing Secret)
```bash
curl -X POST "https://<your-domain>/telegram-webhook.php?user_id=<your_user_id>" \
     -H "Content-Type: application/json" \
     -d '{"message": {"text": "/start"}}'
```
*Expected result: HTTP 401 Unauthorized*

#### Test Successful Connection (With Secret)
```bash
curl -X POST "https://<your-domain>/telegram-webhook.php?user_id=<your_user_id>" \
     -H "Content-Type: application/json" \
     -H "X-Telegram-Bot-Api-Secret-Token: <YOUR_WEBHOOK_SECRET>" \
     -d '{"message": {"chat": {"id": 12345}, "text": "/start"}}'
```
*Expected result: HTTP 200 OK and a log entry in the Logging tab.*

## Common Issues

| Issue | Potential Cause | Resolution |
| :--- | :--- | :--- |
| **No logs in Logging tab** | Webhook not registered or URL incorrect. | Verify the webhook URL and re-run the `setWebhook` curl command. |
| **HTTP 401 Unauthorized** | Webhook Secret mismatch. | Ensure the secret in your `setWebhook` command matches the one in Settings. |
| **HTTP 400 Invalid Request** | Malformed JSON or missing fields. | Inspect the payload in the Logging tab to ensure it contains `message` and `chat` objects. |
| **Bot doesn't respond** | Invalid Bot Token or Bot permissions. | Check that the Bot Token is correct and that the bot has permission to send messages. |
| **SSL Errors** | Self-signed or expired certificate. | Telegram requires a valid, publicly trusted SSL certificate. Use Let's Encrypt for a free, trusted certificate. |
| **Connection Timed Out** | Firewall or network configuration. | Ensure your server allows incoming HTTPS traffic on port 443 from Telegram's IP ranges. |
