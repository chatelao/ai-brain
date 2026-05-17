# Automation Concept: Task Operations

This document outlines the planned automation and manual operations for tasks within the Agent Control application.

## 1. Pull Request Operations

### 1.1 Merge & Close
When a task has an associated Pull Request (PR), it may be eligible for a "Merge & Close" operation directly from the project issue list.

**Conditions for presenting the "Merge & Close" option:**
- The issue has an associated Pull Request.
- The Pull Request is reported as **mergeable** by GitHub.
- The Pull Request is reported to be **ready** (not a draft).
- All status checks (more than 1) are in a **passed** or **skipped** state.

**Action:**
- Merges the Pull Request via the GitHub API.
- Closes the associated GitHub issue.

## 2. Failed Jules Session Operations

When a task has a status indicating a failed Jules session (`failed_jules`), the following options are presented on the issue list:

### 2.1 Retry
**Action:**
- Sends a command to the existing Jules Session: "retry to finish the task".
- This aims to resume the current session and attempt to complete the remaining work.

### 2.2 Restart
**Action:**
- Aborts or deletes the current Jules session.
- Removes the "Jules" label from the associated GitHub issue.
- Re-adds the "Jules" label to the GitHub issue to trigger a fresh agent session.

## 3. Issue Lifecycle Automation

### 3.1 Auto-Repeat Duplication
To support recurring tasks, the system implements an "Auto-Repeat" mechanism.

**Trigger:**
- A Pull Request associated with an issue carrying the **"Auto-Repeat"** label is closed (typically after a merge).

**Action:**
- The system automatically duplicates the issue.
- The new issue **includes** the "Jules" label to trigger the agent.
- The new issue **excludes** the "Auto-Repeat" label (it is not a cascading repeat unless re-added).
- The original issue's title and body are used for the new issue.
