# Main Screen Display Audit

This document describes the rules for how issues, pull requests, and agent sessions are displayed on the main dashboard (`src/frontend/index.php`).

## 1. Running Autorepeat Tasks Section

Located at the top of the dashboard, this section highlights active tasks that are configured to automatically repeat.

- **Data Source**: `App\Task::getRunningAutorepeatTasks($userId)`
- **Visibility Criteria**:
    - The task's GitHub issue state must be `open`.
    - The task must have a label named exactly `autorepeat` in its GitHub metadata.
- **Display**:
    - Shows the Project Name, Issue Number, and Title.
    - Status is always displayed as a `🚧 Active` badge.

## 2. Project Grid

The main grid displays all projects owned by the user, each containing a collection of "status squares" representing individual tasks.

### Task Filtering Rules
Tasks displayed within a project card are filtered by `App\Task::findActiveByUserProjects($userId)` with the following rules:
- **Open Tasks**: All tasks where `github_state = 'open'` are shown.
- **Closed Tasks**: Only the **3 most recent** tasks where `github_state = 'closed'` AND `status = 'completed'` are shown **across all projects** for the user.
- **Orphan Prevention**: Tasks with `issue_number = 0` or an empty title are **never** shown.
- **Sorting**: Tasks are sorted by `created_at` in descending order.

### Status Squares (Visual Indicators)
Each task is represented by a 24x24px square.

#### Color Logic (`App\Task::getStatusColor`)
| Color | Condition |
| :--- | :--- |
| **Purple** | `github_state` is `closed` |
| **Red** | `status` is `failed` or starts with `failed_` (e.g., `failed_jules`, `failed_pr`) |
| **Yellow** | `status` is `in_progress`, `implemented`, `coding`, or `testing` |
| **Blue** | `status` is `analyzed`, `researching`, `planning`, `awaiting-plan-approval`, or `awaiting-user-feedback` |
| **Green** | `status` is `completed` |
| **Grey** | Any other status (usually `pending`) |

#### Border Logic
- **Pink Border**: Applied if the task has a GitHub label named `autorepeat` or `auto-repeat` (case-insensitive).

#### Tooltip Logic
Hovering over a square displays a tooltip in the format: `#<Issue Number>: <Emoji> <Label> - <Title>`

| Emoji | Label | Condition |
| :--- | :--- | :--- |
| ✅ | `closed` | `github_state` is `closed` |
| ✅ | `completed` | `status` is `completed` (and issue is open) |
| ❌ | `Jules` | `status` is `failed` or `failed_jules` |
| ❌ | `PR` | `status` is `failed_pr` |
| 🚧 | `<status>` | `status` is `pending`, `analyzed`, `researching`, `planning`, `in_progress`, `coding`, `testing`, or `implemented` |
| ⏳ | `<status>` | Default fallback |

## 3. Navigation Rules
- Clicking a **Project Name** opens the GitHub repository.
- Clicking **"View Project Details"** goes to the internal project page (`project.php`).
- Clicking a **Status Square** goes to the internal task detail page (`task.php`).

## 4. Identified Inconsistencies & Technical Details
- **Autorepeat Label Inconsistency**: The "Running Autorepeat Tasks" section only recognizes the label `autorepeat`, whereas the status square border logic recognizes both `autorepeat` and `auto-repeat`.
- **Closed Task Visibility**: Tasks that are closed on GitHub but did not reach the `completed` status (e.g., `failed_jules`) are not displayed in the project grid once closed, as the "3 most recent" filter specifically looks for `status = 'completed'`.
- **Pending Status**: In the tooltip, `pending` tasks show a `🚧` (Work in Progress) emoji, but the status square is `grey` (Neutral/Inactive).
