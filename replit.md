# MT Modulis - Gamybos valdymo sistema

## Overview
MT Modulis is a manufacturing order management system designed for Lithuanian users to manage orders, products, clients, and construction objects. It aims to streamline manufacturing processes, track quality indicators, and facilitate data synchronization with an external QMS. The system provides CRUD operations for core entities, robust authentication, and comprehensive tracking of production steps including functional, dielectric, and grounding tests, component management, and defect tracking with PDF document generation. The project's ambition is to provide a complete and integrated solution for manufacturing management, improving efficiency and data accuracy.

## User Preferences
- Preferred communication style: Simple, everyday language
- Language: Lithuanian (lietuvių kalba)
- Technology preference: PHP, CSS, JavaScript, HTML

## Workflow & Server Configuration

### Critical Notes
- **Server**: PHP 8.3 built-in dev server launched via `npm run dev` → `tsx server/index.ts` → clears stale sessions → spawns `php -S 0.0.0.0:5000 -t public public/router.php` with auto-restart.
- **Workflow timeout**: The `restartWorkflow` timeout parameter acts as a process lifetime limit. ALWAYS use `restartWorkflow({timeout: 86400})` to prevent premature process termination. Auto-restarts by the system use a short default (~30s) that kills the process.
- **Health check**: Replit's webview health checker (from 172.31.80.162) requests GET `/` and expects HTTP 200. The login page (`login.php`) MUST NEVER return 302 redirects on GET requests — it always returns 200 OK. If the user is already logged in, it shows a "you are logged in" message with a link instead of redirecting. Only POST requests (form submission) redirect after successful login.
- **Sessions**: PHP sessions stored in `/tmp/sess_*`. The health checker preserves cookies (PHPSESSID, remember_token) across requests. Sessions are cleared on every server startup to prevent stale cookies from causing issues.
- **"Project" workflow**: The `.replit` file contains an immutable "Project" workflow (`runButton = "Project"`) that chains to "Start application". This cannot be removed via the API.

## System Architecture

### Modular Architecture (Gaminių rūšys)
- The system uses a modular architecture where each product type (MT, USN, SI-04, GVX, 10kV) is a separate module.
- Navigation is dynamically generated from the `gaminiu_rusys` database table.
- Each module has its own: quality indicators (`index.php?grupe=XXX`), orders (`uzsakymai.php?grupe=XXX`), and functional test template (`sablonas_funkciniai.php?grupe=XXX`).
- The `?grupe=` URL parameter controls which module is active; defaults to 'MT' for backward compatibility.
- MT-specific features (components, dielectric tests, passport) are only shown in the MT module.
- Templates (`mt_funkciniu_sablonas`) are filtered by `gaminiu_rusis_id` column to support per-group templates.
- All PDF exports (30d, extended, quarterly) accept `?grupe=` parameter.

### Frontend
- **Languages**: HTML, CSS, JavaScript (vanilla)
- **Styling**: Custom CSS with variables for theming, Inter font.
- **Responsiveness**: Mobile-friendly with collapsible sidebar.
- **UI Pattern**: Server-rendered PHP pages utilizing modal dialogs for forms.
- **UI/UX Decisions**: All UI elements are in Lithuanian. Dashboard (Kokybiniai rodikliai) serves as the main page, featuring a tabbed navigation for various statistics (30-day indicators, quarterly comparison, extended statistics). Tiles are used for navigation within product windows (e.g., MT product navigation).
- **Usability & Accessibility**: Styled 404 error page, breadcrumb navigation for module sub-pages, skip-to-content link for keyboard users, ARIA labels on all icon-only buttons and modal close buttons, `role="alert"` on notification messages, Escape key closes modals, Tab key focus trap within modals, focus returns to trigger element after modal close, form validation CSS indicators (red/green borders), required field asterisks.

### Backend
- **Language**: PHP 8.3
- **Server**: PHP built-in development server.
- **Authentication**: Session-based with `password_verify()` for bcrypt hashes. Includes password reset via email and user profile management.
- **Database Interaction**: PDO with PostgreSQL driver, using prepared statements and `htmlspecialchars()` for security.
- **Core Features**: CRUD operations for orders, products, clients, objects, claims, devices, and users.
- **Claims Module (Pretenzijos)**: Full claim lifecycle with PDF export (`pretenzijos_pdf.php`, PR 28/2 form), email sending with delegation (`pretenzijos_siusti.php`), email history with feedback tracking (`pretenzijos_email_history` table), public feedback page (`pretenzijos_atsakymas.php`), and photo compression loading indicator.
- **Manufacturing Process Tracking**:
    - **Functional Tests**: Management of 21 manufacturing requirements, including defect tracking, photo uploads (with lightbox preview and AJAX delete), and PDF generation. Supports editable templates for requirements.
    - **Component Management**: Tracking of mounted components (18 default items).
    - **Dielectric Tests**: Recording of instrument data, voltage tests, and grounding checks, with PDF generation. Includes medium voltage data management.
    - **Fuse Holder Management**: Supports 1x and 2x transformer logic for fuse holders (3.5 and 3.6 types).
    - **Document Generation**: Automated PDF generation for functional tests, dielectric tests, and MT passports using mPDF.
- **Data Synchronization**: Manual trigger for comprehensive data sync (orders, products, tests, components, PDFs) to an external Tomo QMS database. Includes a sync log viewer.
    - **Import optimization**: The `importuotiILocalDB()` method uses batch queries — 3 large SELECT queries to quality_tomas (gaminiai, bandymai, komponentai) instead of per-order queries. Prepared statements are reused across iterations. Import includes `ignore_user_abort(true)` and `set_time_limit(300)` for production reliability.
    - **Import diagnostics**: Result includes `faze2_apdoroti`, `faze2_be_gaminiu`, `faze2_praleisti` counters for Phase 2 visibility.
- **User Management**: Admin-only user creation, editing, and role assignment (admin, user, skaitytojas).
- **Class-based Architecture**: Utilizes PHP classes for database interaction, migrations, email handling, and specific data models (e.g., `Gaminys`, `TomoQMS`).
- **Auto-migration**: Idempotent database migrations on page load (`DBMigracija.php`).
- **Company Settings**: Configurable company details (name, address, phone, fax, email, website, logo) stored in `imones_nustatymai` table. Admin-only settings page at `imones_nustatymai.php`. Helper function `getImonesNustatymai()` in `config.php` provides cached access. All PDF generators and email templates use dynamic company data instead of hardcoded values.

### File Structure (Key Directories/Files)
- `public/`: Web root.
- `public/includes/`: Configuration, header, footer.
- `public/css/`, `public/js/`: Static assets.
- `public/klases/`: Core PHP classes (Database, TomoQMS, Gaminys, etc.).
- `public/login.php`, `public/logout.php`, `public/profilis.php`, `public/slaptazodis_atstatymas.php`, `public/slaptazodis_keitimas.php`: Authentication and user management.
- `public/index.php`: Dashboard with quality indicators.
- `public/uzsakymai.php`: Order management.
- `public/MT/`: Specific MT (Manufacturing Technology) related forms and save handlers (functional tests, components, dielectric tests, fuse holders, PDF generation).
- `public/sinchronizuoti.php`, `public/sync_log.php`: Data synchronization and logging.

### Database
- **Type**: PostgreSQL.
- **Key Tables**: `vartotojai`, `uzsakymai`, `gaminiai`, `uzsakovai`, `objektai`, `komponentai`, `funkciniai_bandymai`, `dielektriniai_bandymai`, `funkciniu_sablonas`, `izeminimo_tikrinimas`, `saugikliu_ideklai`, `paso_teksto_korekcijos`, `pretenzijos`, `pretenzijos_email_history`, `prietaisai`, `gvx_dokumentai`, `imones_nustatymai`.
- **Renamed Tables**: `mt_` prefix removed from 7 tables for universality (migration in `DBMigracija::pervadintiMtLenteles()`).

## External Dependencies

- **Tomo QMS Database**: External PostgreSQL database for data replication.
- **PostgreSQL**: Primary application database.
- **Google Fonts**: Inter font for typography.
- **PHP Extensions**: `pgsql`, `pdo_pgsql`, `mbstring`, `session`.
- **Resend API**: Email service for password reset functionality.
- **mPDF library**: Used for generating PDF documents (e.g., functional tests, dielectric tests, MT passports).