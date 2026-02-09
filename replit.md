# MT Modulis - Order Management System

## Overview

MT Modulis is a Lithuanian-language order management system (manufacturing/production module) that allows users to manage orders (užsakymai), products (gaminiai), clients (užsakovai), and construction objects (objektai). The application was originally a PHP/PostgreSQL system that has been rebuilt as a modern full-stack TypeScript application. It features session-based authentication with bcrypt password hashing, CRUD operations for orders and products, and a responsive UI with sidebar navigation.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend
- **Framework**: React 18 with TypeScript, built with Vite
- **Routing**: Wouter (lightweight router) with Lithuanian route paths (e.g., `/uzsakymai`, `/gaminiai`)
- **State Management**: TanStack React Query for server state, no global client state library
- **UI Components**: shadcn/ui component library (New York style) built on Radix UI primitives
- **Styling**: Tailwind CSS with CSS custom properties for theming, Inter + Playfair Display fonts
- **Forms**: React Hook Form with Zod validation via `@hookform/resolvers`
- **Path aliases**: `@/` maps to `client/src/`, `@shared/` maps to `shared/`

### Backend
- **Framework**: Express.js running on Node.js with TypeScript (via tsx)
- **Authentication**: Passport.js with LocalStrategy, express-session for session management
  - Passwords use bcrypt hashing (handles `$2y$` to `$2a$` prefix conversion from PHP-originated hashes)
  - Session-based auth with `connect-pg-simple` for session storage
  - Login fields use Lithuanian names: `el_pastas` (email), `slaptazodis` (password)
- **API Design**: REST API under `/api/` prefix with typed route definitions in `shared/routes.ts`
- **Dev Server**: Vite dev server with HMR proxied through Express in development

### Shared Layer
- **Schema**: `shared/schema.ts` defines all database tables and Zod insert schemas using Drizzle ORM
- **Routes**: `shared/routes.ts` defines typed API contract with Zod validation for both input and responses
- This shared layer ensures type safety between frontend and backend

### Database
- **Database**: PostgreSQL (required, referenced via `DATABASE_URL` environment variable)
- **ORM**: Drizzle ORM with `drizzle-zod` for schema-to-validation integration
- **Schema Push**: Use `npm run db:push` (drizzle-kit push) to sync schema to database
- **Key Tables**:
  - `vartotojai` - Users (auth, roles)
  - `uzsakymai` - Orders
  - `gaminiai` - Products/items within orders
  - `uzsakovai` - Clients/customers
  - `objektai` - Construction objects/sites
  - `gaminio_tipai` - Product types
  - `mt_komponentai` - Components
  - `gaminiu_rusys` - Product categories

### Build & Development
- **Dev**: `npm run dev` runs tsx with Vite HMR
- **Build**: `npm run build` runs custom `script/build.ts` which builds client with Vite and server with esbuild
- **Production**: `npm start` serves the built app from `dist/`
- **Type checking**: `npm run check` runs TypeScript compiler

### Storage Pattern
- `server/storage.ts` defines an `IStorage` interface and `DatabaseStorage` implementation
- All database operations go through this abstraction layer
- Uses Drizzle query builder with `eq`, `desc` operators

## External Dependencies

- **PostgreSQL**: Primary database, connected via `DATABASE_URL` environment variable using `pg` (node-postgres) pool
- **Google Fonts**: Inter, Playfair Display, DM Sans, Fira Code, Geist Mono loaded via CDN
- **Radix UI**: Full suite of accessible UI primitives (dialog, select, tabs, toast, etc.)
- **Recharts**: Chart library (available but usage not confirmed in visible code)
- **Embla Carousel**: Carousel component
- **Vaul**: Drawer component
- **Lucide React**: Icon library used throughout the UI
- **No external auth providers**: Authentication is self-contained with local username/password strategy