# Concept: Unified User Experience (UX)

## Overview
The Agent Control application aims to provide a highly efficient, "frictionless" experience for managing AI agents across Desktop, Mobile (Web), and Telegram. The UX strategy focuses on **Glanceability**, **Actionability**, and **Continuity**, ensuring that users can monitor and control tasks with minimal cognitive load regardless of their device or location.

## Business Cases
| Case | Description |
| :--- | :--- |
| **Operational Speed** | Minimizing the time between receiving a notification and taking corrective action (e.g., retrying a failed session). |
| **Reduced Cognitive Load** | Using a unified visual language (colors, emojis) so users don't have to re-learn statuses across different platforms. |
| **Improved Responsiveness** | Enabling full task control via Telegram for users who are on the move. |
| **Error Reduction** | Prioritizing "Selection over Writing" to prevent input errors and speed up interactions. |

## Interaction Principles

### 1. Glanceability (Information Density)
Users should be able to understand the health of all projects in seconds.
- **Desktop**: High-density grid with "Status Squares" providing a bird's-eye view of dozens of tasks simultaneously.
- **Mobile/Telegram**: Prioritized lists and status badges that emphasize the "most important" or "most recent" activities.

### 2. Actionability (Contextual Controls)
The most common actions (Run Agent, Merge & Close, Retry) should be accessible within 1-2 interactions.
- Controls are context-aware: only relevant buttons are shown for the current task state (e.g., "Merge & Close" only appears when a PR is ready).

### 3. Continuity (Unified Language)
A consistent set of visual cues is used across all channels:
- **Colors**: Green (Ready/Success), Yellow/Blue (In Progress), Red (Failure), Purple (Closed).
- **Emojis**: ✅ (Success), 🚧 (Working), ❌ (Error), 🔍 (Checking).
- **Terminology**: Standardized task states (CREATED, PROCESSING, READY, FINISHED, FAILED).

### 4. Selection over Writing
Typing is a "heavy" interaction, especially on mobile. The system prioritizes:
- **Inline Buttons** in Telegram.
- **Template-based Issue Creation** on Web.
- **One-tap operations** for state transitions.

## Use Cases
| ID | Use Case | Description |
| :--- | :--- | :--- |
| <a name="UC-UX1"></a>**UC-UX1** | **Desktop Overlook** | A developer starts their day by opening the Dashboard. The high-density "Status Square" grid allows them to immediately identify a single Red square (Failed Task) among dozens of Green ones. They hover to see the issue title and click to jump directly to the logs. |
| <a name="UC-UX2"></a>**UC-UX2** | **Mobile Monitoring** | A user checks the dashboard on their phone while commuting. The responsive UI collapses the dense grid into a scrollable list of projects, with the "Running Autorepeat Tasks" prominently displayed at the top for quick oversight. |
| <a name="UC-UX3"></a>**UC-UX3** | **Telegram Reaction** | A user receives a Telegram notification that a Jules session has failed. Instead of opening a browser, they tap the "Retry" button directly within the Telegram chat. The bot confirms the action, and the user continues their day. |
| <a name="UC-UX4"></a>**UC-UX4** | **Template-Driven Workflow** | To start a recurring task, a user selects a pre-defined "Feature Request" template on the project page. They fill in 1-2 parameters (e.g., Feature Name) and tap "Create". The system handles GitHub issue creation and agent triggering automatically. |

## UX/Formatting

### 1. Multi-Line Notifications
To provide clear context and maintain continuity across channels, all notifications follow a standardized three-line structure:
1.  **Line 1: Title & Emoji**: A brief, emoji-prefixed summary of the event (e.g., `✅ Task Completed: #123`).
2.  **Line 2: Context (Repository)**: The name of the GitHub repository where the event occurred (italicized or secondary text).
3.  **Line 3: Detail Message**: A descriptive sentence explaining the specific change or action required.

A blank line is inserted between the Context and the Detail Message in Telegram and long-form views to improve readability.
