# Roadmap: Next Generation UI (Next Gen UI)

## Phase 1: API Foundation & Authentication
The goal of Phase 1 is to create the necessary backend infrastructure to support modern client applications.

- **Milestone 1.1: OpenAPI Compliance**
    - Refactor existing PHP endpoints to serve JSON exclusively for `/api/*` routes.
    - Validate all API responses against the `openapi.yaml` specification.
- **Milestone 1.2: Modern Auth Implementation**
    - Implement JWT token issuance and validation.
    - Support Refresh Tokens for persistent mobile sessions.
- **Milestone 1.3: Real-time Event Stream**
    - Implement the AG-UI Protocol (SSE) for task status updates.

## Phase 2: Core Web Dashboard (Alpha)
The goal of Phase 2 is to achieve feature parity with the existing "Project Card" view in a new React application.

- **Milestone 2.1: Project/Task Overview**
    - Port the Status Square Grid to React components.
    - Implement TanStack Query for data fetching and synchronization.
- **Milestone 2.2: Task Detail View**
    - Port the log viewer and interactive controls (Retry, Restart, Merge).
- **Milestone 2.3: Integration Testing**
    - Ensure the new React dashboard can coexist with the legacy UI (e.g., via subfolder or subdomain).

## Phase 3: Mobile App & Notification Integration (Beta)
The goal of Phase 3 is to launch the native mobile application and enhance the notification ecosystem.

- **Milestone 3.1: React Native Shell**
    - Initialize the Expo project and implement shared API clients.
    - Build the unified Login flow (Google/GitHub).
- **Milestone 3.2: Native Oversight**
    - Implement the "Running Autorepeat Tasks" and "Project Grid" views for mobile.
- **Milestone 3.3: Push Notifications**
    - Integrate with Firebase or Expo Notifications to deliver native alerts for `FAILED` and `READY` states.

## Phase 4: Full Migration & Legacy Sunset
The final phase involves migrating all remaining features and decommissioning the PHP frontend.

- **Milestone 4.1: Administrative & Advanced Features**
    - Port Settings, User Management (Admin Dashboard), and Roadmap/Template management.
- **Milestone 4.2: User Migration**
    - Default all users to the New UI.
    - Provide a "Switch back to Legacy" toggle for a limited time to gather feedback.
- **Milestone 4.3: Legacy Decommission**
    - Remove PHP frontend templates and Alpine.js dependencies.
    - Complete refactoring of the backend into a pure API server (e.g., transitioning to NestJS).
