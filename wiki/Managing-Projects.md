# Managing Projects

Projects in Agent Control are individual GitHub repositories linked to your account. This page explains how to configure and manage them.

## Project Settings

Each project has its own settings page where you can update its configuration or handle maintenance tasks. To access it, click the **Settings icon (gear)** on the project card in the Dashboard.

### General Settings
- **GitHub Account**: Change which linked GitHub account is used to interact with this repository.
- **Repository**: Update the repository path (`owner/repo`).

### Webhook Configuration
Webhooks allow GitHub to send real-time updates to Agent Control whenever an issue is opened, edited, or closed.

- **Status Check**: The settings page shows the current status of the webhook (Active, Missing, Inactive).
- **Automated Setup**: If the webhook is missing, click **"Setup Webhook Automatically"**. The application will attempt to create it using your GitHub token.
- **Manual Setup**: If automated setup fails, you can manually create the webhook in GitHub:
    - **Payload URL**: Copy the URL provided in the settings.
    - **Content type**: `application/json`
    - **Secret**: Copy the secret provided in the settings.
    - **Events**: Select "Let me select individual events" and check **Issues**.

## Deleting a Project

If you no longer need to manage a repository through Agent Control:
1. Go to the **Project Settings**.
2. Scroll down to the **"Danger Zone"**.
3. Click **"Delete Project"** and confirm the action.

> **Warning:** This action only removes the project from Agent Control. It does **not** delete the repository or issues on GitHub.

---
Next: [Task Management](Task-Management.md)
