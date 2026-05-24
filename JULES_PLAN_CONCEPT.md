# Concept: Jules Session Plan Integration

## Overview
The "Jules Session Plan Integration" aims to bring the core decision-making phase of an agent session directly into the Task Detail view. By extracting and prominently displaying the suggested plan of action, users can review, adjust, and approve agent activities without leaving the dashboard or navigating through GitHub comments.

## Business Cases
| Case | Description |
| :--- | :--- |
| **Improved Oversight** | Provides a dedicated space to review what Jules intends to do before it starts coding. |
| **Reduced Context Switching** | Eliminates the need to open GitHub to find and read the plan in the comment stream. |
| **Faster Approvals** | Enables one-tap approval of plans, accelerating the task lifecycle. |
| **Trust & Transparency** | Increases user confidence by making the agent's internal reasoning visible and reviewable. |

## Design Principles

### 1. Automatic Detection
The system automatically identifies "Plan" messages from Jules' comment history using keyword-based heuristics.
- **Keywords**: "Suggested plan", "Plan of action", "Action plan", "I propose the following", "Steps to complete".
- **Scope**: The most recent message from `google-labs-jules[bot]` or `jules` that contains these keywords.

### 2. Prominent Display
When a task is in the `PLANNING` state, the extracted plan is displayed in a high-priority "Session Plan" card at the top of the Task page, above the general issue description.

### 3. Integrated Actionability
Approval is a first-class action. The Interaction Panel dynamically presents an "Approve Plan" button when a plan is detected or when the task state is `PLANNING`.

## Workflow

### 1. Extraction Logic
The backend (`src/frontend/api/task.php`) will scan `jules_messages` for the plan.
- If a plan is found, it is returned in a new `session_plan` field in the Task API response.
- The extraction uses regex to find the section of the message starting with plan keywords and ending at the next major section or end of message.

### 2. Frontend Rendering
The `TaskDetailView.tsx` will include a new `SessionPlanCard` component.
- **Rendering**: Uses `ReactMarkdown` with GitHub Flavored Markdown (GFM) to maintain formatting, checkboxes, and code snippets.
- **Visuals**: Styled with a distinctive border (e.g., `border-blue-200`) and a "Plan" icon to differentiate it from regular logs or descriptions.

### 3. Approval Execution
When the user clicks "Approve Plan":
1. The frontend calls the API (`POST /api/task.php?id={id}`) with `{ "action": "approve_plan" }`.
2. The backend uses the `GitHubService` to post a comment "approve plan" on the linked GitHub issue.
3. Jules (on GitHub) detects the comment and transitions from `PLANNING` to `EXECUTING`.
4. The local task status is updated to `EXECUTING` on the next sync.

## Interaction Mockup (Mobile/Web)

**[ 🚧 PLANNING ] #123 Fix Login Bug**

---
### 📝 Jules' Proposed Plan
I have analyzed the issue and propose the following steps:
1. Update `Auth.php` to handle null tokens.
2. Add a unit test for empty session cases.
3. Verify fix in the local environment.

**[ Approve Plan ]**  [ Edit on GitHub ]
---

## Technical Considerations
- **Sync Latency**: Since approval happens via GitHub comments, there might be a short delay (seconds) before the status reflects the transition to `EXECUTING`.
- **Fallback**: If no plan is detected but the state is `PLANNING`, the UI should provide a link to the Jules Session URL for manual review.
- **History**: Only the latest plan is shown. If Jules updates the plan, the card refreshes to show the new version.
