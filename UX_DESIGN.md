# Design: Unified User Experience (UX)

## Overview
The UX design translates the principles of **Glanceability**, **Actionability**, and **Continuity** into specific interface patterns and components across the web dashboard and Telegram.

## 1. Visual Language & Continuity

A shared set of visual indicators is used across all platforms to represent the **Unified Task State**.

### 1.1 Color & Emoji Mapping
| Unified State | Color (Web) | Hex Code | Emoji (Telegram/Tooltip) | Meaning |
| :--- | :--- | :--- | :--- | :--- |
| **`CREATED`** | Grey | `#8b949e` | ⏳ | Waiting for agent |
| **`PROCESSING`** | Blue / Yellow | `#0366d6` / `#d29922` | 🚧 | Agent is working |
| **`CHECKING`** | Orange | `#f0883e` | 🔍 | Awaiting CI results |
| **`READY`** | Green | `#238636` | ✅ | Validated and ready to merge |
| **`FINISHED`** | Purple / Green | `#8957e5` / `#238636` | ✅ | Task completed and closed |
| **`FAILED`** | Red | `#f85149` | ❌ | Human intervention required |

### 1.2 Layout Consistency
- **Issue Identification**: Issues are always referenced as `[#IssueNumber] - Title`.
- **Breadcrumbs**: Standardized navigation path: `Dashboard > Project > Task`.

## 2. Platform-Specific Design

### 2.1 Desktop Web (Information Density)
The Desktop UI maximizes screen real estate for oversight.
- **Main Dashboard**: Uses a "Project Card" layout with a **Status Square Grid**.
    - Each square (24x24px) represents a task.
    - Hovering reveals a tooltip with detailed status and title.
    - Status squares act as direct links to the Task Detail page.
- **Project Page**: Splits the view into a 1/4 sidebar (Project Overview & Roadmap) and 3/4 main content (Task Table).
- **Task Table**: Optimized for scanning with clear status badges and action buttons.

### 2.2 Mobile Web (Responsive Reflow)
The interface uses Tailwind CSS to adapt to smaller screens.
- **Navigation**: Navbar elements (Settings, Logout) remain accessible but compact.
- **Grid to List**: The Status Square Grid in project cards reflows to ensure squares remain touch-friendly.
- **Prioritization**: "Running Autorepeat Tasks" is promoted to the top of the viewport to highlight active automation.

### 2.3 Telegram (Interactive Micro-UX)
Telegram serves as an "Actionable Notification" channel.
- **Notification Structure**:
    - **Header**: Status Emoji + Project Name.
    - **Body**: Task Title and a brief summary of the event.
    - **Footer**: Deep link to the GitHub Issue/PR.
- **Inline Keyboards**: Notifications include context-aware buttons:
    - *Failed Session*: `[Retry]`, `[Restart]`.
    - *Ready PR*: `[Merge & Close]`.
    - *Generic*: `[Acknowledge]`.
- **Feedback Loop**: Tapping a button triggers an immediate `answerCallbackQuery` (e.g., "Retrying...") and updates the message to remove the buttons once the action is confirmed.

## 3. Interaction Flows

### 3.1 Task Recovery (The "Red Square" Flow)
1. **Detection**: User sees a Red Status Square on Desktop or receives a ❌ notification on Telegram.
2. **Analysis**: User clicks the square/link to view logs on the Task Detail page.
3. **Action**: User taps "Retry" or "Restart".
4. **Verification**: The Status Square turns Yellow (Executing) or Blue (Analyzing), confirming the recovery started.

### 3.2 Efficiency via Templates
To reduce the friction of starting new tasks:
- **Parameter Injection**: Templates use `%1`, `%2` placeholders.
- **Live Preview**: The project page displays fields for each placeholder detected in the selected template.
- **One-Tap Creation**: Users fill the fields and check "Add Jules Label" to start the agent immediately upon issue creation.

## 4. Accessibility & Feedback
- **Active States**: Buttons show loading indicators or change text during API calls.
- **Success/Error Toast**: Short-lived banners (e.g., `?success=synced`) provide confirmation of backend operations.
- **Tooltip Pre-fetch**: Tooltips on status squares provide immediate context without requiring a page load.
