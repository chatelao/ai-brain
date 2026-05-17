# Integrations & Configuration

To unlock the full potential of Agent Control, you need to configure your API keys and notification preferences.

## Google Jules API (Gemini)

The AI features are powered by Google's Gemini models. You must provide your own API key.

1. Obtain an API key from [Google AI Studio](https://aistudio.google.com/).
2. In Agent Control, go to **Settings > General**.
3. Paste your key into the **"Jules API Key"** field.
4. Click **"Save"**.

> **Privacy Note**: Your API key is stored securely and is only used for requests initiated by your account.

## Notification Preferences

You can choose how you want to receive updates about agent activity and task status.

1. Navigate to **Settings > Notifications**.
2. Toggle the following channels:
    - **In-App Inbox**: Notifications appear under the bell icon in the navigation bar.
    - **Telegram**: Notifications are sent to your personal Telegram bot (requires setup).
3. Click **"Save Preferences"**.

## Webhook Logging

For troubleshooting purposes, the application keeps a log of recent webhook events.

1. Navigate to **Settings > Logging**.
2. You can view the last 5 calls for each endpoint, including status codes, headers, and payloads.
3. This is useful for verifying that GitHub or Telegram webhooks are correctly reaching your application.

---
Next: [Telegram Bot](Telegram-Bot.md)
