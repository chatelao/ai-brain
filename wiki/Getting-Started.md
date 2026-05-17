# Getting Started with Agent Control

Welcome to Agent Control! This guide will walk you through the initial steps to get your environment up and running.

## 1. Initial Login

Agent Control uses **Google SSO** for secure authentication.

1. Navigate to the application URL.
2. Click the **"Login with Google"** button.
3. Authenticate with your preferred Google account.
4. Upon successful login, you will be redirected to the main Dashboard.

## 2. Linking your GitHub Account

To manage repositories and issues, you must link at least one GitHub account.

1. Go to **Settings** (link in the top navigation bar).
2. Under the **"Linked GitHub Accounts"** section, click **"Link GitHub Account"**.
3. You will be redirected to GitHub to authorize the application.
4. Once authorized, you will see your GitHub username listed in the Settings.

> **Note:** You can link multiple GitHub accounts if you manage projects across different organizations or personal accounts.

## 3. Creating your First Project

Now that your GitHub account is linked, you can create a project by linking a repository.

1. Go back to the **Dashboard** (Dashboard link or clicking "Agent Control" logo).
2. Locate the **"Link New Repository"** card.
3. Select the appropriate **GitHub Account** from the dropdown.
4. Enter the **Repository** in `owner/repo` format (e.g., `my-org/my-project`).
5. Click **"Link New Repository"**.

The application will attempt to automatically set up a webhook in your GitHub repository to track issue events.

---
Next: [Managing Projects](Managing-Projects.md)
