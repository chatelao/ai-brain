# Concept: Jules Session Status Updates via Webhooks

## Overview
Currently, the application triggers Google Jules agents synchronously. The frontend waits for the Gemini API to respond before updating the task status and displaying the result. This can lead to timeouts and a poor user experience for long-running agent tasks. This document outlines the transition to an asynchronous model using webhooks.

## Current Synchronous Flow
1. **Trigger**: User clicks "Run Agent" in `project.php`.
2. **Request**: `project.php` calls `JulesService::triggerAgent()`.
3. **Wait**: The PHP process stays active, waiting for the Gemini API response (up to 30 seconds).
4. **Update**: Once received, the task status is updated to `completed` (or `failed`) and the response is stored.
5. **Display**: The page reloads and shows the response.

## Proposed Asynchronous Webhook Flow
To support longer sessions and better responsiveness, we will implement a webhook-based update system.

1. **Trigger**: User clicks "Run Agent".
2. **Token Generation**: The system generates a unique, cryptographically secure `jules_token` for the specific task session.
3. **Initiation**: The system sends the request to the agent, including the `jules_token` and the `webhook_url` (e.g., `https://your-domain.com/jules-webhook.php`).
4. **Immediate Acknowledgement**: The agent service acknowledges receipt of the task. The application updates the task status to `in_progress` and returns control to the user immediately.
5. **Asynchronous Processing**: The agent works on the task in the background.
6. **Webhook Callback**: Upon completion or failure, the agent service sends a POST request to the `webhook_url`.
7. **Verification & Update**: The application verifies the `jules_token`, updates the task with the agent's response, and sets the status to `completed` or `failed`.
8. **User Notification**: (Optional) The application can then notify the user via Telegram or GitHub comments.

## Security
- **jules_token**: Each agent session is assigned a unique UUID-based token. This token acts as a secret key that must be presented by the webhook caller to authorize updates to a specific task.
- **HTTPS**: All webhook communications must occur over HTTPS.

## API Specification for Jules Webhook

**Endpoint**: `POST /jules-webhook.php`

**Payload (JSON)**:
```json
{
  "jules_token": "unique-session-token-uuid",
  "status": "completed",
  "response": "The agent analysis result...",
  "error": null
}
```

**Responses**:
- `200 OK`: Webhook processed successfully.
- `400 Bad Request`: Missing required fields.
- `401 Unauthorized`: Invalid or missing `jules_token`.
- `404 Not Found`: No task found matching the provided token.
- `500 Internal Server Error`: Processing failed.
