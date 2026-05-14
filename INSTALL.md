# Installation Guide

This guide explains how to install the AI Brain application on a web server.

## Prerequisites

- **PHP 8.3+** with the following extensions:
  - `php-fpm`, `php-mysql`, `php-curl`, `php-xml`, `php-mbstring`, `php-zip`, `php-gd`, `php-sqlite3`
- **MySQL** (or MariaDB)
- **Composer** (PHP dependency manager)
- **Web Server** (Nginx recommended, or Apache)
- **SSL Certificate** (Required for Google SSO and GitHub Webhooks)

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
| `GOOGLE_JULES_API_KEY` | (Optional) API Key for Google Jules/Gemini |
| `TELEGRAM_BOT_TOKEN` | (Optional) Telegram Bot Token |

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
           # ... add other env vars here ...
       }
   }
   ```

5. **Set Permissions:**
   Ensure the web server user (e.g., `www-data`) has write access if necessary (though the current architecture mostly uses DB).

---

## b) Installation with SFTP

Use this method if you only have SFTP access and cannot run commands on the server.

1. **Build locally:**
   On your local machine, clone the repository and run:
   ```bash
   composer install --no-dev
   ```

2. **Upload files:**
   Using an SFTP client (like FileZilla or WinSCP), upload the following to your server:
   - `src/`
   - `vendor/`
   - `composer.json`
   - `composer.lock`

   *Note: You can skip `test/`, `.github/`, `docs/`, and other non-production files.*

3. **Set up the database:**
   - Use a tool like **phpMyAdmin** provided by your hosting.
   - Create a new database.
   - Use the "Import" tab to upload and execute `src/sql/schema.sql`.

4. **Configure Environment Variables:**
   - If your host allows setting environment variables in a control panel (like cPanel), add them there.
   - Alternatively, you might need to use an `.htaccess` file (for Apache) or contact your host to set `fastcgi_param` (for Nginx).

   **Example `.htaccess` (Apache):**
   ```apache
   SetEnv DB_HOST localhost
   SetEnv DB_NAME your_db_name
   # ... etc
   ```

5. **Document Root:**
   Ensure your hosting points the domain to the `src/frontend` folder of the uploaded files.
