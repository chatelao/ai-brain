# Auto-Repeat Tasks

Auto-Repeat allows a task to be automatically merged, closed, and duplicated a specified number of times. This is useful for recurring tasks or tasks that require multiple iterations.

## How it Works

1.  **Set Auto-Repeat Count**: On the Task Detail page, you can specify the number of repetitions in the "Auto-Repeat" input field within the Actions panel.
2.  **Automatic Processing**:
    *   When a task reaches the **READY** status (i.e., Jules has finished, and all GitHub PR checks have passed), the system checks if `autorepeat_remaining` is greater than 0.
    *   If active, the system automatically:
        1.  Adds the `autorepeat` label to the GitHub issue (to trigger duplication logic).
        2.  Merges the Pull Request.
        3.  Closes the GitHub Issue as "completed".
3.  **Duplication**:
    *   Upon closing the issue, the `WebhookHandler` detects the `autorepeat` label.
    *   It creates a new issue with the same title and body.
    *   The `autorepeat_remaining` count is decremented by 1 and assigned to the new task.
    *   The `Jules` label is added to the new issue to start the agent processing automatically.

## Manual Trigger

You can still manually trigger a "Merge, Close & Duplicate" action. If you do this, the `autorepeat` label is added, and the issue is duplicated once. If you had a count set in "Auto-Repeat", it will be carried over (decremented) to the new task.

## UI Indicators

*   Tasks with the `autorepeat` or `auto-repeat` label are highlighted with a pink border in the task grid.
*   The "Running Autorepeat Tasks" section on the dashboard shows all active tasks with this label.
*   The Task Detail page displays the remaining repetition count.
