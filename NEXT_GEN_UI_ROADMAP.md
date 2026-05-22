# Roadmap: Next Generation UI (Next Gen UI)

This roadmap outlines the evolution of the Agent Control interface from a PHP-centric monolith into a modern, headless, and multi-platform ecosystem.

## Progress Overview

| Phase | Description | Status |
| :--- | :--- | :---: |
| 1 | API Foundation & Authentication | 🚧 |
| 2 | Core Web Dashboard (Alpha) | ⏳ |
| 3 | Mobile App & Notification Integration (Beta) | ⏳ |
| 4 | Full Migration & Legacy Sunset | ⏳ |

## Frontend Migration Coverage

This section tracks the migration progress of legacy PHP views to the new React-based architecture.

| View | PHP Source | React Status | % |
| :--- | :--- | :---: | :---: |
| Dashboard | `index.php` | ✅ | 100.0% |
| Project View | `project.php` | ✅ | 100.0% |
| Task Detail | `task.php` | ✅ | 100.0% |
| User Settings | `settings.php` | ✅ | 100.0% |
| Webhook Logs | `logs.php` | ✅ | 100.0% |
| Admin Dashboard | `admin/index.php` | ✅ | 100.0% |
| **Total** | | | **100.0%** |

## Goals
- ✅ Achieve 100% OpenAPI compliance for all core backend endpoints.
- ⏳ Launch native mobile applications for Android and iOS.
- ⏳ Complete transition to JWT-based authentication.
- ⏳ Full decommissioning of the PHP-based frontend.

---

## Phase 1: API Foundation & Authentication
The goal of Phase 1 is to create the necessary backend infrastructure to support modern client applications.

- ✅ **Milestone 1.1: OpenAPI Compliance**
    - ✅ Refactor existing PHP endpoints to serve JSON exclusively for `/api/*` routes.
        - ✅ Implemented `/api/projects.php` as a RESTful endpoint.
        - ✅ Implemented `/api/tasks.php` as a RESTful endpoint.
        - ✅ Implemented `/api/task.php` as a RESTful endpoint (GET & POST).
        - ✅ Implemented `/api/task-logs.php` as a RESTful endpoint.
        - ✅ Implemented `/api/webhook-logs.php` as a RESTful endpoint.
        - ✅ Implemented `/api/performance-logs.php` as a RESTful endpoint.
        - ✅ Implemented `/api/user.php` as a RESTful endpoint.
    - ✅ Validate all API responses against the `api/openapi.yaml` specification. This is the relevant API between Browser and Server to use and update if necessary.
- ✅ **Milestone 1.2: Modern Auth Implementation**
    - ✅ Implement JWT token issuance and validation in `App\Auth`.
    - ✅ Implement `/api/token.php` for JWT token issuance.
    - ✅ Support Refresh Tokens for persistent mobile sessions via `/api/refresh.php`.

## Phase 2: Core Web Dashboard (Alpha)
The goal of Phase 2 is to achieve feature parity with the existing "Project Card" view in a new React application.

- 🚧 **Milestone 2.1: Project Setup & Overview**
    - ✅ **Milestone 2.1.1: Project Initialization**
        - ✅ Define standard directory structure (src/components, src/hooks, src/api).
        - ✅ Configure ESLint, Prettier, and TypeScript for project consistency.
        - ✅ Initialize Next.js project with Tailwind CSS.
        - ✅ Implement base API client utility (Axios/Fetch with JWT interceptors).
        - ✅ Setup shared types generated from `api/openapi.yaml`.
        - ✅ Setup TanStack Query for state management and data fetching.
    - ✅ **Milestone 2.1.2: Core Components**
        - ✅ Component: `ProjectCard` - Displays project summary and repository links.
        - ✅ Component: `LinkRepositoryModal` - Replaces the "Link New Repository" form.
        - ✅ Component: `TaskStatusGrid` - Replaces the project status square grid.
        - ✅ Component: `StatusBadge` - Unified status display with emojis and color mapping.
        - ✅ Component: `TaskFilterBar` - Client-side filtering by status/repository.
    - ✅ **Milestone 2.1.3: Data Synchronization**
        - ✅ Implement background polling and cache invalidation using TanStack Query.
- ✅ **Milestone 2.2: Task Detail View**
    - ✅ Component: Task Header (Title, Status, Labels).
    - ✅ Component: Log Viewer (Fetch from `/api/task-logs.php`).
    - ✅ Component: Interaction Panel (Retry, Restart, Merge buttons).
    - ✅ Component: GitHub/Jules Metadata Sidebar.
- ✅ **Milestone 2.3: Project Detail View**
    - ✅ Implement `/api/project.php` (GET/POST).
    - ✅ Implement `useProject` hook.
    - ✅ Implement Project Detail page with task list and roadmap.
- ✅ **Milestone 2.4: Integration Testing**
    - ✅ Ensure the new React dashboard can coexist with the legacy UI (e.g., via subfolder or subdomain).
    - ✅ Implement common `Navbar` for unified navigation across React views.

## Phase 3: Mobile App & Notification Integration (Beta)
The goal of Phase 3 is to launch the native mobile application and enhance the notification ecosystem.

- ⏳ **Milestone 3.1: React Native Shell**
    - ⏳ Initialize the Expo project and implement shared API clients.
    - ⏳ Build the unified Login flow (Google/GitHub).
- ⏳ **Milestone 3.2: Native Oversight**
    - ⏳ Implement the "Running Autorepeat Tasks" and "Project Grid" views for mobile.
- ⏳ **Milestone 3.3: Push Notifications**
    - ⏳ Integrate with Firebase or Expo Notifications to deliver native alerts for `FAILED` and `READY` states.

## Phase 4: Full Migration & Legacy Sunset
The final phase involves migrating all remaining features and decommissioning the PHP frontend.

- ✅ **Milestone 4.1: Administrative & Advanced Features**
    - ✅ Port Settings, User Management (Admin Dashboard).
    - ✅ Roadmap/Template management.
- ⏳ **Milestone 4.2: User Migration**
    - ⏳ Default all users to the New UI.
    - ⏳ Provide a "Switch back to Legacy" toggle for a limited time to gather feedback.
- ⏳ **Milestone 4.3: Legacy Decommission**
    - ⏳ Remove PHP frontend templates and Alpine.js dependencies.
    - ⏳ Complete refactoring of the backend into a pure API server (e.g., transitioning to NestJS).
