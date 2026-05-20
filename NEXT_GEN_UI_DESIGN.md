# Design: Next Generation UI (Next Gen UI)

## 1. Technical Architecture
The Next Gen UI follows a strict **Client-Server Separation** model.

### 1.1 Communication Layer
- **RESTful API**: Standard interactions (listing projects, updating settings) use JSON over HTTP, as defined in `api/openapi.yaml`. This is the relevant API between Browser and Server to use and update if necessary.
- **Event Streaming**: Real-time updates for task statuses (moving from `EXECUTING` to `READY`) utilize background synchronization.
- **Client Generation**: API clients for both Web (TypeScript) and Mobile (TypeScript) are automatically generated from the OpenAPI specification to ensure type safety across the stack.

### 1.2 State Management
- **Server State**: Managed via **TanStack Query** (React Query). This handles caching, background synchronization, and optimistic updates for actions like "Trigger Agent".
- **Client State**: Minimal local state (UI toggles, form inputs) managed via React Hooks (`useState`, `useReducer`) or a lightweight store (Zustand) if cross-component coordination is needed.

## 2. Frontend Design System

### 2.1 Component Library
- **Tailwind CSS**: Retained as the foundational styling engine for consistency with the legacy UI.
- **Shadcn/UI**: A set of accessible, high-quality components (Buttons, Dialogs, Cards) that provide a modern "polished" feel while remaining fully customizable via Tailwind.
- **Adaptive Layouts**:
    - **Web**: Multi-column layouts with collapsible sidebars.
    - **Mobile**: Single-column tab-based navigation using **React Navigation** (for React Native).

### 2.2 Visual Identity (Continuity)
The design system enforces the mapping defined in `STATE_EVENTS_CONCEPT.md`:
- **Status Squares**: Re-implemented as reusable React components with built-in tooltips and smooth state transitions.
- **Dark Mode Support**: Native support for dark/light themes following system preferences.

## 3. Authentication & Security

### 3.1 Session to JWT Transition
The Next Gen UI transitions from PHP sessions to **JSON Web Tokens (JWT)**:
1. **SSO Flow**: User logs in via Google/GitHub.
2. **Token Issuance**: The backend issues an Access Token (short-lived) and a Refresh Token (long-lived, HttpOnly cookie).
3. **Authorization**: Clients include the Access Token in the `Authorization: Bearer <token>` header for all API requests.

### 3.2 Secure Mobile Storage
- **Refresh Tokens**: Stored securely on mobile devices using **SecureStore** (Expo) to maintain long-running sessions without re-authentication.

## 4. API & Data Mapping
The frontend consumes the standardized entities from `CONCEPT.md`:

| Entity | Frontend Interface | Backend Source |
| :--- | :--- | :--- |
| **Project** | `IProject` (Mapped to `projects` table) | `GET /projects` |
| **Task** | `ITask` (Mapped to `tasks` table) | `GET /projects/{id}/tasks` |
| **Log** | `ILog` (Mapped to `performance_logs`/`task_logs`) | `GET /tasks/{id}/logs` |

## 5. Mobile-Specific Design Patterns
- **Pull-to-Refresh**: Standard interaction for manual synchronization.
- **Haptic Feedback**: Subtle haptics on successful state transitions (e.g., when a PR becomes `READY`).
- **Push Notifications**: Deep-linking directly from a "Failed Task" notification to the Task Detail view in the app.
