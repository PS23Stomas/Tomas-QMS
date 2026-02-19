# MT Modulis - Gamybos valdymo sistema

## Overview
MT Modulis is a manufacturing order management system designed for Lithuanian users to manage orders, products, clients, and construction objects. It aims to streamline manufacturing processes, track quality indicators, and facilitate data synchronization with an external QMS. The system provides CRUD operations for core entities, robust authentication, and comprehensive tracking of production steps including functional, dielectric, and grounding tests, component management, and defect tracking with PDF document generation. The project's ambition is to provide a complete and integrated solution for manufacturing management, improving efficiency and data accuracy.

## User Preferences
- Preferred communication style: Simple, everyday language
- Language: Lithuanian (lietuvių kalba)
- Technology preference: PHP, CSS, JavaScript, HTML

## System Architecture

### Frontend
- **Languages**: HTML, CSS, JavaScript (vanilla)
- **Styling**: Custom CSS with variables for theming, Inter font.
- **Responsiveness**: Mobile-friendly with collapsible sidebar.
- **UI Pattern**: Server-rendered PHP pages utilizing modal dialogs for forms.
- **UI/UX Decisions**: All UI elements are in Lithuanian. Dashboard (Kokybiniai rodikliai) serves as the main page, featuring a tabbed navigation for various statistics (30-day indicators, quarterly comparison, extended statistics). Tiles are used for navigation within product windows (e.g., MT product navigation).

### Backend
- **Language**: PHP 8.3
- **Server**: PHP built-in development server.
- **Authentication**: Session-based with `password_verify()` for bcrypt hashes. Includes password reset via email and user profile management.
- **Database Interaction**: PDO with PostgreSQL driver, using prepared statements and `htmlspecialchars()` for security.
- **Core Features**: CRUD operations for orders, products, clients, objects, claims, devices, and users.
- **Manufacturing Process Tracking**:
    - **Functional Tests**: Management of 21 manufacturing requirements, including defect tracking, photo uploads, and PDF generation. Supports editable templates for requirements.
    - **Component Management**: Tracking of mounted components (18 default items).
    - **Dielectric Tests**: Recording of instrument data, voltage tests, and grounding checks, with PDF generation. Includes medium voltage data management.
    - **Fuse Holder Management**: Supports 1x and 2x transformer logic for fuse holders (3.5 and 3.6 types).
    - **Document Generation**: Automated PDF generation for functional tests, dielectric tests, and MT passports using mPDF.
- **Data Synchronization**: Manual trigger for comprehensive data sync (orders, products, tests, components, PDFs) to an external Tomo QMS database. Includes a sync log viewer.
- **User Management**: Admin-only user creation, editing, and role assignment (admin, user, skaitytojas).
- **Class-based Architecture**: Utilizes PHP classes for database interaction, migrations, email handling, and specific data models (e.g., `Gaminys`, `TomoQMS`).
- **Auto-migration**: Idempotent database migrations on page load (`DBMigracija.php`).

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
- **Key Tables**: `vartotojai`, `uzsakymai`, `gaminiai`, `uzsakovai`, `objektai`, `mt_komponentai`, `mt_funkciniai_bandymai`, `mt_dielektriniai_bandymai`, `pretenzijos`, `prietaisai`, `gvx_dokumentai`.

## External Dependencies

- **Tomo QMS Database**: External PostgreSQL database for data replication.
- **PostgreSQL**: Primary application database.
- **Google Fonts**: Inter font for typography.
- **PHP Extensions**: `pgsql`, `pdo_pgsql`, `mbstring`, `session`.
- **Resend API**: Email service for password reset functionality.
- **mPDF library**: Used for generating PDF documents (e.g., functional tests, dielectric tests, MT passports).