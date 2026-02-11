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
├── klases/
│   ├── Database.php        # Singleton DB connection (parses DATABASE_URL)
│   ├── DBMigracija.php     # Auto-migration on page load (idempotent)
│   ├── Emailas.php         # Resend API email sending (password reset)
│   ├── GaminioTipas.php    # Product type queries
│   ├── Gaminys.php         # Product queries and validation
│   └── Gamys1.php          # Extended product CRUD
├── login.php               # Login page with "Pamiršau slaptažodį" link
├── logout.php              # Session destroy and redirect
├── slaptazodis_atstatymas.php  # Password reset request (enter email)
├── slaptazodis_keitimas.php    # Password change via token from email
├── profilis.php            # User profile - email update, password change
├── index.php               # Dashboard - Kokybiniai rodikliai (quality indicators)
├── uzsakymai.php           # Orders - list, view, create, edit, delete
├── pretenzijos.php         # Claims - list, view, create, edit, delete
├── prietaisai.php          # Devices/instruments - calibration tracking, CRUD
├── vartotojai.php          # User management (admin only) - CRUD
├── gaminiu_langai_mt.php   # MT product navigation window (tiles for forms/components/tests)
├── mt_funkciniai_bandymai.php  # MT functional tests form (21 requirements)
├── issaugoti_mt_bandyma.php    # Save handler for functional tests
├── MT/
│   ├── mt_sumontuoti_komponentai.php  # MT mounted components list (18 default items)
│   ├── issaugoti_mt_komponentus.php   # Save handler for components (single row or bulk)
│   ├── mt_dielektriniai.php           # Dielectric tests (instruments, voltage tests, grounding)
│   ├── issaugoti_mt_dielektriniai.php # Save handler for dielectric tests (with transactions)
│   ├── issaugoti_mt_saugiklius.php    # Save handler for fuse holders (transactional)
│   ├── issaugoti_prietaisus.php       # Save handler for test instruments
│   ├── issaugoti_protokolo_nr.php     # Save handler for protocol number
│   └── issaugoti_mt_pasa_teksta.php   # AJAX endpoint for passport text corrections
├── mt_statistika.php       # MT statistics page with filtering
├── grafiko_duomenys.php    # Chart data API endpoint
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
  - `mt_funkciniai_bandymai` - Functional tests (defect tracking)
  - `mt_saugikliu_ideklai` - Fuse holder records
  - `mt_izeminimo_tikrinimas` - Grounding test records
  - `mt_dielektriniai_bandymai` - Dielectric test records
  - `pretenzijos` - Claims/complaints tracking
  - `prietaisai` - Devices/instruments with calibration tracking
  - `gaminiu_rusys` - Product categories
  - `bandymai_prietaisai` - Test instruments/devices for dielectric tests
  - `antriniu_grandiniu_bandymai` - Secondary circuit (medium voltage) test results
  - `gvx_dokumentai` - Generated documents (PDF storage)
  - `mt_paso_teksto_korekcijos` - Passport text corrections (multilingual)

### Build & Development
- **Dev**: `npm run dev` runs `tsx server/index.ts` which launches PHP built-in server
- **PHP Server**: `php -S 0.0.0.0:5000 -t public public/router.php`
- **Port**: 5000 (bound to 0.0.0.0)

## External Dependencies

- **PostgreSQL**: Primary database, connected via `DATABASE_URL` environment variable
- **Google Fonts**: Inter font loaded via CDN
- **PHP Extensions**: pgsql, pdo_pgsql, mbstring, session
- **Resend API**: Email sending for password reset (requires `RESEND_API_KEY` secret)
- **No external auth providers**: Authentication is self-contained with local email/password

## Recent Changes

- 2026-02-11: Added MT passport PDF generation using mPDF library (generuoti_mt_paso_pdf.php, mt_paso_pdf.php)
- 2026-02-11: Added mt_paso_pdf (BYTEA) and mt_paso_failas columns to gaminiai table for PDF storage
- 2026-02-11: Added "Generuoti PDF", "Peržiūrėti PDF", "Atsisiųsti PDF" buttons to MT passport page
- 2026-02-11: Optimized gautiPagalId() to exclude large BYTEA fields from SELECT
- 2026-02-10: Integrated all save handlers: functional tests (with transactions, original user preservation), dielectric tests, components, fuse holders, test instruments, protocol number, passport text corrections
- 2026-02-10: Created gvx_dokumentai table for PDF document storage
- 2026-02-10: Added rezultatas column to antriniu_grandiniu_bandymai table
- 2026-02-10: Embedded MT Gaminių Langas tiles directly into order detail view (uzsakymai.php) with QMS-matching design
- 2026-02-10: Added MT gaminių langas with navigation tiles to functional tests, components, dielectric tests
- 2026-02-10: Added MT functional tests form (21 manufacturing requirements with save)
- 2026-02-10: Added MT components management (18 default components with CRUD)
- 2026-02-10: Added MT dielectric tests (instruments, voltage tests, grounding checks with save)
- 2026-02-10: Added Sesija.php and Komponentas.php helper classes
- 2026-02-10: Added "MT Langas" button to order detail view in uzsakymai.php
- 2026-02-10: Added password reset via email (slaptazodis_atstatymas.php, slaptazodis_keitimas.php)
- 2026-02-10: Added user profile page (profilis.php) with email update and password change
- 2026-02-10: Added Emailas.php class for Resend API email integration
- 2026-02-10: Added "Pamiršau slaptažodį" link to login page and "Profilis" to sidebar
- 2026-02-10: Refactored to class-based architecture (Database, DBMigracija, GaminioTipas, Gaminys, Gamys1)
- 2026-02-09: Added Pretenzijos (claims), Prietaisų patikra (device calibration), Vartotojų valdymas (user management) pages
- 2026-02-09: Updated sidebar navigation with section labels (Gamyba, Administravimas)
- 2026-02-09: Rebuilt entire application from TypeScript/React to PHP/CSS/JavaScript/HTML
- 2026-02-09: Added "Kokybiniai rodikliai" (quality indicators) dashboard as main page
- 2026-02-09: All UI in Lithuanian language
