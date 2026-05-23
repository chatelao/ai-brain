# Agent Skills & Patterns

This document defines 25 standard skills and patterns for the Jules agent. These are designed to be triggered with minimal effort (ideally one-tap or label-based) to enhance productivity, especially for mobile users via Telegram or the Web UI.

## Overview
The goal of these skills is to minimize "typing" and maximize "selection." By using specific GitHub labels, UI buttons, or Telegram inline actions, users can orchestrate complex AI workflows without writing detailed prompts.

---

## 25 Standard Skills

| # | Skill Name | Trigger / Context | Description |
| :--- | :--- | :--- | :--- |
| **1** | **Bug Autopsy** | Label: `bug` | Analyzes the issue description, searches the codebase for symptoms, and proposes a fix + reproduction test case. |
| **2** | **Test Gap Filler** | Button: `Generate Tests` | Analyzes a class or function and generates missing PHPUnit/Jest tests to reach 100% coverage. |
| **3** | **Boilerplate Buster** | Template Selection | Creates a standard CRUD controller, model, and API endpoint based on a single entity name. |
| **4** | **Clean Code Refactor** | Label: `refactor` | Applies SOLID principles and project-specific linting rules to the target file or directory. |
| **5** | **DocBlock Generator** | Button: `Document` | Automatically adds PHPDoc, JSDoc, or TypeDoc to all functions, including context-aware type hinting. |
| **6** | **Security Sentinel** | Automatic on PR | Scans the PR for SQL injection, XSS, or hardcoded secrets before any human reviews it. |
| **7** | **Dependency Auditor** | Label: `audit-deps` | Checks dependency files for vulnerabilities and opens PRs for safe, non-breaking updates. |
| **8** | **SQL Optimizer** | Label: `performance` | Analyzes slow queries from performance logs and suggests missing indexes or query rewrites. |
| **9** | **PR Summary (TL;DR)** | Telegram Action | Generates a 3-bullet point summary of a complex PR for quick approval on mobile. |
| **10** | **Migration Maker** | Button: `Sync DB` | Detects changes in PHP/TS model properties and generates the corresponding SQL migration file. |
| **11** | **OpenAPI Generator** | Button: `Update API` | Scans backend routes and updates `api/openapi.yaml` to keep the frontend and mobile clients in sync. |
| **12** | **Dead Code Reaper** | Label: `cleanup` | Identifies and removes unused private methods, unused imports, and unreachable code blocks. |
| **13** | **Mobile UI Verifier** | Automatic on PR | Runs Playwright scripts to take mobile-sized screenshots of UI changes and posts them as PR comments. |
| **14** | **Error Log Explainer** | Telegram Notification | When a `CRITICAL` log occurs, Jules fetches the stack trace and explains the root cause in plain English. |
| **15** | **Changelog Drafter** | Button: `Draft Release` | Compiles merged PRs since the last tag into a formatted `CHANGELOG.md` or GitHub Release. |
| **16** | **Mock Data Seeder** | Button: `Seed Data` | Generates 50+ rows of realistic mock data for a database table for local testing/dev. |
| **17** | **A11y Auditor** | Label: `accessibility` | Runs accessibility scans (Lighthouse/Axe) on a specific page and opens issues for violations. |
| **18** | **i18n Sync** | Label: `translate` | Detects hardcoded strings, moves them to i18n files, and auto-translates them to supported languages. |
| **19** | **Issue Auto-Labeler** | Automatic on Open | Uses NLP to categorize new issues into `bug`, `feature`, `chore`, or `invalid` to triage the inbox. |
| **20** | **Legacy-to-Next-Gen** | Label: `migrate-ui` | Converts a legacy PHP frontend file (e.g., `src/frontend/*.php`) into a React component in `web/src/app/`. |
| **21** | **Roadmap Sync** | Button: `Sync Roadmap` | Analyzes `TOP_ROADMAP.md` and creates GitHub Issues for "Next Tasks" that aren't yet tracked. |
| **22** | **Environment Doctor** | Button: `Check Health` | Verifies DB connectivity, API Quotas (Jules/GitHub), and Webhook health in one click. |
| **23** | **Git Conflict Resolver** | Label: `fix-conflicts` | Automatically resolves simple merge conflicts by prioritizing logical consistency over line-based diffs. |
| **24** | **Instruction Follower** | Label: `Jules` | Standard behavior: Follows instructions in the issue body + project-wide `AGENTS.md` context. |
| **25** | **Auto-Repeat Iteration** | Label: `autorepeat` | Automatically clones a finished task with a decremented counter for batch processing. |

---

## Mobile-Optimized Execution Patterns

### 1. Telegram Inline Actions
Notifications in Telegram should include contextual buttons:
- **[Fix Bug]** (on Error Log)
- **[Approve Plan]** (on Jules Planning state)
- **[Merge & Close]** (on Ready state)
- **[Retry]** (on Failure state)

### 2. Label-Driven Orchestration
Adding a single label via the GitHub mobile app or Telegram bot triggers a specific skill. For example:
- Adding `refactor` tells Jules to focus on code quality rather than adding features.
- Adding `audit-deps` triggers the security and dependency scanning workflow.

### 3. Template-Based Creation
The Web UI provides "Zero-Typing" templates where the user only selects options (e.g., "Add CRUD for [Entity Name]") and the agent handles the rest.
