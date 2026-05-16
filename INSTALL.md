# Installation Guide

This guide explains how to install the AI Brain application on a web server.

## Prerequisites

- **PHP 8.3+** with the following extensions:
  - `php-fpm`, `php-mysql`, `php-curl`, `php-xml`, `php-mbstring`, `php-zip`, `php-gd`, `php-sqlite3`
- **MySQL** (or MariaDB)
- **Composer** (PHP dependency manager)
- **Web Server** (Nginx recommended, or Apache)
- **SSL Certificate** (Required for Google SSO and GitHub Webhooks)

---

## Obtaining API Keys and Secrets

### 1. Google OAuth (SSO)
1.  Go to the [Google Cloud Console](https://console.cloud.google.com/).
2.  Create a new project.
3.  Navigate to **APIs & Services > OAuth consent screen**.
4.  Configure the consent screen, adding the `email`, `profile`, and `openid` scopes.
5.  Go to **APIs & Services > Credentials**.
6.  Click **Create Credentials > OAuth client ID**.
7.  Select **Web application** as the application type.
8.  Add your `GOOGLE_REDIRECT_URI` (e.g., `https://your-domain.com/callback.php`) to the **Authorized redirect URIs**.
9.  Copy the **Client ID** and **Client Secret**.

### 2. GitHub OAuth App
1.  Go to your GitHub **Settings > Developer settings > OAuth Apps**.
2.  Click **New OAuth App**.
3.  Set the **Homepage URL** to your domain.
4.  Set the **Authorization callback URL** to your `GITHUB_REDIRECT_URI` (e.g., `https://your-domain.com/github-callback.php`).
5.  Click **Register application**.
6.  Copy the **Client ID** and click **Generate a new client secret** to get your secret.

### 3. Google Gemini (AI Agent)
1.  Visit [Google AI Studio](https://aistudio.google.com/).
2.  Click on **Get API key** in the sidebar.
3.  Click **Create API key** (either in a new or existing Google Cloud project).
4.  Copy the generated key for `GOOGLE_JULES_API_KEY`. (Note: Users can also provide their own keys in the application dashboard).

### 4. Telegram Bot
1.  Message [@BotFather](https://t.me/botfather) on Telegram.
2.  Use the `/newbot` command and follow the instructions to get your **Bot Token**.
3.  For `TELEGRAM_WEBHOOK_SECRET`, generate a random, secure string (e.g., using `openssl rand -hex 32`). This is used to verify that requests to your webhook are actually coming from Telegram.

---

## a) Installation with SSH

This is the recommended method if you have terminal access to your server.

1. **Clone the repository:**
   ```bash
   cd /var/www
   git clone https://github.com/chatelao/ai-brain.git
   cd ai-brain
   ```

2. **Install dependencies:**
   ```bash
   composer install --no-dev
   ```

3. **Set up the database:**
   - Create a MySQL database and user.
   - Import the schema:
     ```bash
     mysql -u your_user -p your_db_name < src/sql/schema.sql
     ```

4. **Configure your Web Server:**
   Point your web server's document root to the `src/frontend` directory.

   **Example Nginx Configuration:**
   ```nginx
   server {
       listen 443 ssl;
       server_name your-domain.com;
       root /var/www/ai-brain/src/frontend;

       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;

           # Environment variables
           fastcgi_param DB_HOST localhost;
           fastcgi_param DB_NAME your_db_name;
           fastcgi_param DB_USER your_user;
           fastcgi_param DB_PASS your_password;

           # Enable interactive upgrade page (set to 'true' when needed, then remove or set to 'false')
           # fastcgi_param ENABLE_UPGRADE_PAGE true;

           # ... add other env vars here ...
       }
   }
   ```

5. **Set Permissions:**
   Ensure the web server user (e.g., `www-data`) has write access if necessary (though the current architecture mostly uses DB).

---

## b) Manual Deployment via SSH (rsync)

Use this method if you want to deploy from your local machine via SSH.

1. **Build locally:**
   On your local machine, clone the repository and run:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **Upload files:**
   Use `rsync` to upload the production files to your server:
   ```bash
   rsync -avz --exclude='.git*' --exclude='.github/' --exclude='test/' \
     --exclude='docs/' --exclude='specification/' --exclude='scripts/' \
     --exclude='Vagrantfile' --exclude='*.sqlite' \
     ./ user@your-server:/var/www/ai-brain/
   ```

   *Note: Ensure the destination path matches your server configuration.*

3. **Set up the database:**
   - Use a tool like **phpMyAdmin** provided by your hosting.
   - Create a new database.
   - Use the "Import" tab to upload and execute `src/sql/schema.sql`.

4. **Configure Environment Variables:**
   - If your host allows setting environment variables in a control panel (like cPanel), add them there.
   - Alternatively, you might need to use an `.htaccess` file (for Apache) or contact your host to set `fastcgi_param` (for Nginx).

   **Example `.htaccess` (Apache):**
   ```apache
   #
   # Database Configuration
   #
   SetEnv DB_HOST                 localhost
   SetEnv DB_NAME                 your_db_name
   SetEnv DB_USER                 your_user
   SetEnv DB_PASS                 your_password

   #
   # Google OAuth: https://console.cloud.google.com/auth/clients
   #
   SetEnv GOOGLE_CLIENT_ID        your_google_client_id
   SetEnv GOOGLE_CLIENT_SECRET    your_google_client_secret
   SetEnv GOOGLE_REDIRECT_URI     https://your-domain.com/callback.php

   #
   # GitHub OAuth: https://github.com/settings/developers
   #
   SetEnv GITHUB_CLIENT_ID        your_github_client_id
   SetEnv GITHUB_CLIENT_SECRET    your_github_client_secret
   SetEnv GITHUB_REDIRECT_URI     https://your-domain.com/github-callback.php

   #
   # Google Jules (Optional Global Fallback): https://aistudio.google.com/
   #
   # SetEnv GOOGLE_JULES_API_KEY    your_google_jules_api_key

   #
   # Telegram Bot: https://t.me/botfather
   #
   SetEnv TELEGRAM_BOT_TOKEN      your_telegram_bot_token
   SetEnv TELEGRAM_WEBHOOK_SECRET your_telegram_webhook_secret

   #
   # Administration
   #
   SetEnv UPGRADE_ALLOWED_EMAIL   your_admin_email@example.com
   # SetEnv ENABLE_UPGRADE_PAGE     true
   # SetEnv DB_UPGRADE_SECRET       your_secure_upgrade_secret
   ```

5. **Document Root:**
   Ensure your hosting points the domain to the `src/frontend` folder of the uploaded files.

---

## c) Automated Deployment with GitHub Actions (SSH)

If you have your own fork of this repository, you can use the included GitHub Action to automate the SSH deployment.

1. **Configure Secrets:**
   In your GitHub repository, go to **Settings > Secrets and variables > Actions** and add the following repository secrets:
   - `SSH_SERVER`: Your SSH server hostname or IP address.
   - `SSH_USERNAME`: Your SSH username.
   - `SSH_PASSWORD`: Your SSH password.
   - `SSH_PATH`: The target folder on the remote server.

2. **Trigger Deployment:**
   - Go to the **Actions** tab in your GitHub repository.
   - Select the **Manual SSH Deploy** workflow in the sidebar.
   - Click the **Run workflow** dropdown and then **Run workflow**.

   *Note: For security, this workflow is configured to only be runnable by the repository owner.*

3. **Deployment Details:**
   The workflow will:
   - Checkout your code.
   - Install production dependencies via Composer.
   - Upload the application to your server via SSH (rsync), excluding development and documentation files.

---

## Environment Variables

The application requires several environment variables to be set in your web server configuration (e.g., via `fastcgi_param` in Nginx):

| Variable | Description |
| :--- | :--- |
| `DB_HOST` | Database host (e.g., `localhost`) |
| `DB_NAME` | Database name |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |
| `GOOGLE_CLIENT_ID` | Google OAuth 2.0 Client ID |
| `GOOGLE_CLIENT_SECRET` | Google OAuth 2.0 Client Secret |
| `GOOGLE_REDIRECT_URI` | `https://your-domain.com/callback.php` |
| `GITHUB_CLIENT_ID` | GitHub OAuth App Client ID |
| `GITHUB_CLIENT_SECRET` | GitHub OAuth App Client Secret |
| `GITHUB_REDIRECT_URI` | `https://your-domain.com/github-callback.php` |
| `GOOGLE_JULES_API_KEY` | (Optional/Deprecated) Global fallback API Key for Google Jules/Gemini. Users should now set their own keys in the Dashboard. |
| `TELEGRAM_BOT_TOKEN` | (Optional) Telegram Bot Token |
| `TELEGRAM_WEBHOOK_SECRET` | (Optional) Secret token for Telegram webhooks |
| `UPGRADE_ALLOWED_EMAIL` | (Required for upgrades) Email address of the admin user authorized to trigger database migrations on the Admin Dashboard. |
| `ENABLE_UPGRADE_PAGE` | (Optional) Set to `true` to enable the interactive database upgrade page at `/upgrade.php`. Useful for initial setup or maintenance. **Disable after use for security.** |
| `DB_UPGRADE_SECRET` | (Optional) A secret token that allows automated database upgrades via the "Apply DB Patch" workflow. Should be set in `.htaccess` or server configuration. |

---

## Automated Database Upgrades

If you have configured `DB_UPGRADE_SECRET` in your `.htaccess` file, you can use the **Apply DB Patch** workflow to apply pending SQL patches automatically.

1. **Trigger the Workflow:**
   - Go to the **Actions** tab in your GitHub repository.
   - Select the **Apply DB Patch** workflow.
   - Click **Run workflow**.
2. **Parameters:**
   - **Select patch to apply**: Choose 'all' to run all pending migrations, or select a specific `.sql` file.
   - **Base URL of the application**: Enter the public URL of your application (e.g., `https://your-domain.com`).
3. **Execution:**
   - The workflow will SSH into your server, extract the `DB_UPGRADE_SECRET` from your `.htaccess`, and then send an authorized POST request to `/upgrade.php` to trigger the migration.
