# Concept: Next Generation UI (Next Gen UI)

## Overview
The "Next Gen UI" initiative aims to evolve the Agent Control application from a PHP-centric monolith into a modern, headless, and multi-platform ecosystem. By decoupling the frontend from the backend and standardizing on an OpenAPI-driven contract, we will enable a highly responsive web experience and native mobile applications (Android & iOS) with high code reuse.

## 1. Vision & Strategy
The core strategy is **"API-First, Platform-Agnostic"**.
- **Headless Architecture**: The server transitions into a pure API provider, responsible only for business logic, data persistence, and external service orchestration (GitHub, Jules, Telegram).
- **Contract-Driven Development**: The `openapi.yaml` (Agent Control API) and `api/openapi.yaml` (AG-UI Protocol) serve as the single source of truth for all client-server interactions.
- **Native Experience**: Delivering native mobile apps to provide better notification handling and on-the-go agent control beyond the Telegram bot.

## 2. Proposed Tech Stack

### 2.1 Web Frontend: React (Next.js)
- **Why**: Next.js provides a robust framework for building fast, SEO-friendly (where needed), and highly interactive SPAs. Its component-based architecture aligns with our unified state/event model.
- **Benefits**: Improved developer experience, rich ecosystem of UI libraries, and seamless integration with modern state management.

### 2.2 Mobile Frontend: React Native (Expo)
- **Why**: React Native allows us to use the same logic, state management, and even many UI components from the web version for Android and iOS. Expo simplifies deployment and access to native device features (push notifications, haptics).
- **Benefits**: Cross-platform development with a single codebase, native performance, and consistent UX with the web dashboard.

### 2.3 Server-Side API: Node.js (NestJS)
- **Why**: NestJS is a TypeScript-first framework that excels at building scalable, maintainable APIs. It has first-class support for OpenAPI (Swagger) generation and validation.
- **Benefits**: Strong typing (via TypeScript), modular architecture, and easy integration with existing MySQL/SQLite databases.
- *Alternative*: Modern PHP (e.g., Laravel) could also serve as the API layer if we wish to leverage existing PHP expertise, but Node.js offers better synergy with the React ecosystem (shared types).

## 3. Migration Strategy
Transitioning from the current PHP/Alpine.js stack will be performed in four distinct phases to ensure continuous availability.

### Phase 1: API Foundation (BFF Layer)
- Refactor existing PHP logic into a RESTful API following the `openapi.yaml` specification.
- Implement JWT-based authentication alongside the existing session-based auth.
- Result: The existing UI remains functional, but now consumes an internal JSON API.

### Phase 2: Hybrid Web Dashboard
- Start building the new React-based Dashboard.
- Use a "Strangler Fig" pattern: Port specific pages (e.g., Task Detail, Settings) to React while keeping the main navigation in PHP.
- Result: Users experience a mix of old and new, with core high-value pages being modern first.

### Phase 3: Pure SPA (Full Web Migration)
- Complete the migration of all web pages to the Next.js application.
- Sunset the PHP-based frontend templates.
- Result: A fully decoupled, modern web application.

### Phase 4: Mobile Application Rollout
- Leverage the API and shared React logic to build the Mobile App.
- Implement native push notifications as a secondary channel to Telegram.
- Result: Full multi-platform availability (Web, Mobile, Telegram).

## 4. Continuity of UX Principles
The Next Gen UI will strictly adhere to the established principles in `UX_CONCEPT.md`:
- **Glanceability**: Enhanced high-density grids using specialized React components.
- **Actionability**: Optimizing interaction loops (Merge & Close, Retry) with real-time feedback via WebSockets or Server-Sent Events (SSE) as defined in the AG-UI Protocol.
- **Continuity**: Maintaining the standardized color palette and emoji mapping across all platforms.
