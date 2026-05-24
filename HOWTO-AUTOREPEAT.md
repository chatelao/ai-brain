# Auto-Repeat Tasks

Auto-Repeat allows a task to be automatically merged, closed, and duplicated a specified number of times. This is useful for recurring tasks or tasks that require multiple iterations.

## How it Works

1.  **Enable Auto-Repeat**: On the Task Detail page, use the **Auto-Repeat toggle** in the Actions panel to enable the feature.
2.  **Set Count**: Once enabled, you can adjust the "Remaining Cycles" count. The default is 5.
3.  **Automatic Processing**:
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

## Stopping Auto-Repeat

You can stop the auto-repeat cycle at any time by:
*   Clicking the **Stop** button in the Auto-Repeat panel.
*   Toggling the Auto-Repeat switch to **Disabled**.
*   Setting the "Remaining Cycles" to 0.

When stopped, the system automatically removes any `autorepeat` labels from the GitHub issue to prevent further duplications.

## Manual Trigger

You can still manually trigger a "Merge, Close & Duplicate" action. If you do this, the `autorepeat` label is added, and the issue is duplicated once. If you had a count set in "Auto-Repeat", it will be carried over (decremented) to the new task.

## UI Indicators

*   Tasks with the `autorepeat` or `auto-repeat` label are highlighted with a pink border in the task grid.
*   The "Running Autorepeat Tasks" section on the dashboard shows all active tasks with this label.
*   The Task Detail page displays the remaining repetition count.
