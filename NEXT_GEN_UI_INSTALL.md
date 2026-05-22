# Next-Gen UI Installation & Deployment Guide

This guide explains how to install, build, and serve the Next-Generation React-based UI for the AI Brain application.

## Prerequisites

- **Node.js 18.17.0+** (Recommended: Latest LTS)
- **npm** (usually comes with Node.js)
- **Functional PHP Backend**: The Next-Gen UI requires the PHP API to be installed and accessible. See [INSTALL.md](INSTALL.md) for backend setup.

---

## 1. Installation

1.  Navigate to the `web/` directory:
    ```bash
    cd web
    ```
2.  Install dependencies:
    ```bash
    npm install
    ```

## 2. Configuration

The frontend uses environment variables for configuration. You can create a `.env.local` file in the `web/` directory for local development or set them in your deployment environment.

| Variable | Description | Default |
| :--- | :--- | :--- |
| `NEXT_PUBLIC_API_BASE_URL` | The public URL of your PHP API. | `/api` |
| `NEXT_PUBLIC_LEGACY_UI_URL` | The URL of the legacy PHP dashboard. | `/` |
| `API_PROXY_URL` | (Build-time/Dev) The internal URL used by Next.js to proxy `/api` requests. | `http://localhost:8080` |

## 3. Development Mode

To run the Next-Gen UI in development mode with hot-reloading:

```bash
cd web
npm run dev
```

The UI will be available at `http://localhost:3000`.

## 4. Production Build & Serving

### Step A: Build the Application
On your build server or local machine, run:

```bash
cd web
npm run build
```

This generates a optimized production build in the `web/.next` directory.

### Step B: Serve with a Process Manager
It is recommended to use a process manager like **PM2** to ensure the Next.js server remains running.

1.  Install PM2 globally: `npm install -g pm2`
2.  Start the application:
    ```bash
    cd web
    pm2 start npm --name "ai-brain-ui" -- start
    ```

## 5. Web Server Configuration (Nginx)

In production, you should use a web server like Nginx as a reverse proxy to serve both the PHP backend and the Next-Gen UI.

**Example Nginx Configuration:**

```nginx
server {
    listen 443 ssl;
    server_name your-domain.com;

    # 1. Serve Next-Gen UI (React)
    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    # 2. Serve PHP API & Legacy Assets
    # All PHP files and static assets from the legacy UI
    location ~ ^/(api|google|github|telegram|[^/]+\.php|favicon\.svg|[^/]+\.css|[^/]+\.js) {
        root /var/www/ai-brain/src/frontend;
        try_files $uri $uri/ /index.php?$query_string;

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;

            # Pass environment variables
            fastcgi_param DB_HOST localhost;
            # ... other env vars ...
        }
    }
}
```

## 6. Authentication Bridge

The Next-Gen UI uses **JWT (JSON Web Tokens)** for authentication, while the legacy UI uses PHP sessions.

1.  When a user logs in via the Legacy UI (Google/GitHub), a PHP session is established.
2.  The Next-Gen UI calls the `/api/token.php` endpoint.
3.  The backend verifies the active session and issues an Access Token and a Refresh Token.
4.  The Next-Gen UI stores these tokens in `localStorage` and uses them for subsequent API calls.

If the Access Token expires, the frontend will automatically attempt to refresh it using `/api/refresh.php`.
