# MT Modulis - Gamybos valdymo sistema

## Overview

MT Modulis is a Lithuanian-language manufacturing order management system that allows users to manage orders (užsakymai), products (gaminiai), clients (užsakovai), and construction objects (objektai). The application uses PHP, CSS, JavaScript and HTML with a PostgreSQL database. It features session-based authentication with bcrypt password hashing, CRUD operations, a responsive sidebar navigation, and a dashboard with quality indicators (kokybiniai rodikliai).

## User Preferences

- Preferred communication style: Simple, everyday language
- Language: Lithuanian (lietuvių kalba)
- Technology preference: PHP, CSS, JavaScript, HTML

## System Architecture

### Frontend
- **Languages**: HTML, CSS, JavaScript (vanilla)
- **Styling**: Custom CSS with CSS variables for theming, Inter font via Google Fonts
- **Responsive**: Mobile-friendly with collapsible sidebar navigation
- **UI Pattern**: Server-rendered PHP pages with modal dialogs for create/edit forms

### Backend
- **Language**: PHP 8.3
- **Server**: PHP built-in development server (launched via `server/index.ts` wrapper)
- **Authentication**: PHP sessions with `password_verify()` for bcrypt hashes (`$2y$` format from original PHP system)
- **Database**: PDO with PostgreSQL driver
- **Security**: Prepared statements for all queries, `htmlspecialchars()` for output escaping

### File Structure
```
public/                     # Web root served by PHP
├── includes/
│   ├── config.php          # Database connection, session, helper functions
│   ├── header.php          # Common header with sidebar navigation
│   └── footer.php          # Common footer with JS
├── css/
│   └── style.css           # All application styles
├── js/
│   └── app.js              # Sidebar toggle, modal, delete confirmation
├── login.php               # Login page
├── logout.php              # Session destroy and redirect
├── index.php               # Dashboard - Kokybiniai rodikliai (quality indicators)
├── uzsakymai.php           # Orders - list, view, create, edit, delete
├── gaminiai.php            # Products - list, create, delete
├── uzsakovai.php           # Clients - list, create, edit, delete
├── objektai.php            # Objects - list, create, edit, delete
└── router.php              # URL router for PHP built-in server
```

### Database
- **Database**: PostgreSQL (connected via `DATABASE_URL` environment variable)
- **Driver**: PDO with pgsql extension
- **Key Tables**:
  - `vartotojai` - Users (auth, roles: admin, user, skaitytojas)
  - `uzsakymai` - Orders
  - `gaminiai` - Products/items within orders
  - `uzsakovai` - Clients/customers
  - `objektai` - Construction objects/sites
  - `gaminio_tipai` - Product types (with grupe/group classification)
  - `mt_komponentai` - Components
  - `prietaisai` - Devices/instruments
  - `gaminiu_rusys` - Product categories

### Build & Development
- **Dev**: `npm run dev` runs `tsx server/index.ts` which launches PHP built-in server
- **PHP Server**: `php -S 0.0.0.0:5000 -t public public/router.php`
- **Port**: 5000 (bound to 0.0.0.0)

## External Dependencies

- **PostgreSQL**: Primary database, connected via `DATABASE_URL` environment variable
- **Google Fonts**: Inter font loaded via CDN
- **PHP Extensions**: pgsql, pdo_pgsql, mbstring, session
- **No external auth providers**: Authentication is self-contained with local email/password

## Recent Changes

- 2026-02-09: Rebuilt entire application from TypeScript/React to PHP/CSS/JavaScript/HTML
- 2026-02-09: Added "Kokybiniai rodikliai" (quality indicators) dashboard as main page
- 2026-02-09: All UI in Lithuanian language
