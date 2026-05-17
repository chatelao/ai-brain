# Telegram Bot Integration

Agent Control provides a mobile-first interface via Telegram for monitoring and interacting with your agents.

## Setting Up Your Own Bot (Optional)

By default, the system can be configured to use a shared bot, but you can also use your own for enhanced privacy.

1. Create a new bot via [@BotFather](https://t.me/botfather) on Telegram.
2. In Agent Control, go to **Settings > General**.
3. Enter your **Telegram Bot Token** and a **Webhook Secret** (any random string).
4. Click **"Save"**.
5. Set your bot's webhook URL to:
   `https://your-domain.com/telegram-webhook.php?user_id=YOUR_USER_ID`
   (The exact URL is provided in your Settings page).

## Linking Your Account

To receive notifications, you must link your Telegram user account to your Agent Control profile.

1. Go to the **Settings** page in Agent Control.
2. Look for the **"Telegram Account Linking"** section (usually under Notifications or General if a bot is configured).
3. Follow the instructions to send a specific `/start` command or token to the bot.
4. Once linked, the bot will confirm the connection.

## Features

- **Instant Alerts**: Get notified when an agent starts work, completes an analysis, or if a task fails.
- **Task Interaction**: (Upcoming) Commands to trigger agents or check status directly from chat.
- **Deep Linking**: Notification messages include links that open the specific task or project in your mobile browser.
