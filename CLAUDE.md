# CLAUDE.md — Context for AI assistants

> This file is auto-loaded by Claude Code. It captures project context and the
> findings of a full code analysis (2026-06-22) so future sessions start informed.
> The human-facing project doc is `README.md` (Lithuanian, includes full DB schema).

## What this is

**Tomas-QMS** — a web-based **Quality Management System** for **UAB "ELGA"** (Šiauliai,
Lithuania). Tracks manufacturing quality for modular transformers (MT) and related
products: orders, products, functional & dielectric tests, components, passport PDFs,
measurement-device calibration, and customer claims (pretenzijos).

- **Author:** Tomas Viržintas · **Production:** https://nkokybe.elga.tech
- **Language:** code, comments, UI, and DB columns are all in **Lithuanian**.
- ~26K lines, 91 PHP files.

## Stack & how it runs

- **PHP 8.3** (no framework) + **PostgreSQL 16** via **PDO**
- **mPDF** for PDF generation · **Resend API** for email
- Served by PHP's built-in server (`start.sh` → `php -S`, dispatched via `public/router.php`)
- Web root: **`public/`**
- Config via environment vars: `DATABASE_URL`, `RESEND_API_KEY`, `BASE_URL`
- The only real dependency is `mpdf/mpdf` (see `composer.json`).

### Database hosting (migrated 2026-06-23)
- DB **migrated off Replit/Neon onto Supabase** (PostgreSQL). App connects via the
  **Session pooler** (`...pooler.supabase.com:5432`, IPv4) using `DATABASE_URL`.
- Migration was done with PHP/PDO (BYTEA copied via base64), not pg_dump — all 24 tables
  matched on row counts. Schema files: `supabase_schema.sql` (base) + `supabase_schema_patch.sql`
  (adds `gaminiu_pdf_failai`, `gvx_dokumentai`, `sync_log` tables + `funkciniai_bandymai.defekto_sunkumas`
  column that existed live but were missing from the old `database_schema.sql` dump).
- `insert_vartotojai.sql` seeds the 16 users (incl. the `vartotojai.aktyvus` column, also
  absent from the old dump). App code (PHP) still runs on Replit; only the DB moved.

### Referential integrity (added 2026-06-23)
- Originally only 4 FKs existed (pretenzijos_* → pretenzijos). Now **26 FKs** — see
  `supabase_fk.sql`. ON DELETE policy: **RESTRICT** for ownership/catalog links
  (gaminiai/uzsakymai/uzsakovai/objektai/gaminio_tipai/gaminiu_rusys as parents),
  **SET NULL** for author links to `vartotojai`, **CASCADE** for `remember_tokens` and
  the existing pretenzijos_* children.
- Before adding FKs, 42 orphans were cleaned: 39 deleted (`bandymai_prietaisai` 33,
  `komponentai` 3, `funkciniai_bandymai` 3 — parent gaminys long gone), 3 fixed
  (`uzsakymai.vartotojas_id`→NULL ×2, `gaminiu_rusis_id`→valid ×1).
- **Delete permissions:** all entity deletes are admin-only (in code). Delete handlers in
  `gaminiai.php` and `uzsakymai.php` do a full controlled cascade (delete ALL children in
  FK-safe order, in a transaction) before the parent; `objektai`/`uzsakovai` catch SQLSTATE
  23503 and show a friendly "in use" message instead of a blank page.

### ⚠️ Toolchain note
The repo also contains a Node/React/Express `package.json` (+346KB lock), a Python
`pyproject.toml`/`uv.lock`, a stub `main.py`, and `public_export.zip`. **None of these
are used** — they are Replit scaffolding leftovers. `.replit` wrongly sets
`run = "npm run dev"`. The app is pure PHP. Ignore/clean these.

## Layout

```
public/             web root — PHP pages (controller+model+view all mixed)
public/includes/    config.php (bootstrap, helpers: h(), requireLogin(),
                    currentUser(), csrfToken(), csrfVerify()), header.php, footer.php
public/klases/      classes: Database (PDO singleton), Sesija (auth/session),
                    DBMigracija (auto-migration), Gaminys, Komponentas, Emailas,
                    MTPasasKomponentai, TomoQMS (1832-line sync god-class)
public/MT/          MT module pages + PDF generators
public/api/         small AJAX endpoints (quick_add, signatures, photo delete)
public/css|js/      style.css, app.js (the one well-factored frontend asset)
docs/               documentation (not web-accessible)
database_schema.sql DB schema; migracija.php = CLI migration runner
```

## Key files (by size / importance)

- `public/includes/config.php` — bootstrap + global helpers. **Read this first.**
- `public/klases/Database.php` — PDO singleton from `DATABASE_URL`.
- `public/klases/Sesija.php` — session/auth; 30-min idle timeout.
- `public/klases/DBMigracija.php` (760L) — idempotent auto-migration, run manually.
- `public/login.php` — auth (deliberately bypasses config.php; re-implements DB+CSRF inline).
- `public/pretenzijos.php` (2073L), `public/uzsakymai.php` (1630L),
  `public/MT/mt_pasas.php` (1217L), `public/index.php` (1055L) — largest pages.

## Conventions

- **Every protected page:** `require_once includes/config.php;` then `requireLogin();`
- **XSS:** always wrap output in `h(...)` (= `htmlspecialchars`).
- **SQL:** PDO prepared statements with `?` or named params — keep it that way.
- **CSRF:** `csrfToken()` emits the token; `csrfVerify()` checks `_csrf` POST / `X-CSRF-Token`
  header. `app.js` auto-injects `_csrf` into POST forms.
- **Roles:** `administratorius` / `vartotojas` / `skaitytojas` (read-only). Normalized from
  legacy `admin`/`user` at login (`normalizuotiRole`).
- **Binary data** (PDFs, photos, logo, signatures) stored as **BYTEA** in PostgreSQL.

## Security status (updated 2026-06-23)

Original analysis (2026-06-22) found 4 critical/high issues. These are now **FIXED**:

1. ✅ **SQL injection** in `public/pareto.php` — `$_GET['nuo']`/`['iki']` now validated
   against `^\d{4}-\d{2}-\d{2}$` before interpolation.
2. ✅ **Read-only role not enforced** — added `requireWrite()` helper in `config.php`
   (`csrfVerify()` + `Sesija::blokuotiSkaitytojaVeiksma()`). `blokuotiSkaitytojaVeiksma()`
   is now AJAX-aware (JSON 403). Applied to ~25 write handlers (CRUD pages, `MT/issaugoti_*`,
   `MT/istrinti_*`, `issaugoti_*`, `api/quick_add`, `api/delete_defekto_nuotrauka`,
   `pretenzijos_siusti`, `siusti_defekta`, `pretenzijos_failai_api` trinti branch, `mt_pasas`).
3. ✅ **CSRF** — `requireWrite()` on form pages; `app.js` now auto-adds `X-CSRF-Token`
   header to ALL same-origin `fetch`/XHR POST/PUT/PATCH/DELETE (so AJAX is covered too).
4. ✅ **IDOR** — `pretenzijos_pdf.php`, `pretenzija_defekto_pdf.php`,
   `pretenzijos_nuotrauka.php` now require login OR a valid `perziuros_token` that matches
   the owning claim; `pretenzija_perziura.php` passes `&token=` to those links. Verified:
   valid id without token → HTTP 403.

Also hardened: `display_errors=Off` in prod (config.php, `APP_DEBUG=1` to re-enable);
session cookie `SameSite=None` → **`Lax`** (Sesija.php, login.php, logout.php,
slaptazodis_keitimas.php, sesijos_atnaujinimas.php).

### Still open (lower risk) — NOT yet done
- `requireWrite`/role block NOT yet on: `moduliai.php`, `perkelti_pdf_is_qt.php`,
  `gaminiu_langai_mt.php`, `sablonas_funkciniai.php`, `api/elga_parasas.php`,
  `api/vartotojo_parasas.php` (mostly admin-only or signature save).
- `pretenzijos_atsakymas.php` still keyed by sequential `pretenzijos_email_history.id`
  (public answer page) — should move to a per-record token.
- Password-reset token enumeration oracle (M3); `remember_token` no rotation/logout-revoke (M4).

### Architectural debt (lower urgency)
- No router/controller/model layer; logic lives in 1000–2000-line procedural pages mixing
  HTML+SQL+business logic.
- DB-connection logic hand-copied in 5+ files instead of `Database::getConnection()`.
- mPDF setup block duplicated byte-for-byte across ≥6 generators (hardcoded `/tmp/mpdf`,
  POSIX-only).
- 22 empty `catch` blocks swallow errors; raw `$e->getMessage()` leaked to UI in places.
- `DBMigracija` is idempotent but not versioned (no migrations table/rollback); embeds
  destructive `DROP TABLE` and one-off magic constants.

## Good practices already in place (keep)
bcrypt password hashing · `session_regenerate_id()` on login · prepared statements ·
consistent `h()` escaping · no hardcoded secrets (all from env) ·
`MT/ikelti_pdf.php` is an exemplary upload handler (finfo MIME + extension + size checks).

## Environment notes
- Windows host; primary shell PowerShell, Bash also available.
- Not a git repository (as of this writing).
- **Local run:** `start.bat` (repo root) sets `DATABASE_URL` (Supabase pooler) and runs
  `C:\xampp\php\php.exe -S localhost:8000 -t public public/router.php`. PHP is XAMPP 8.2;
  `pdo_pgsql`/`pgsql` were enabled in `C:\xampp\php\php.ini`. Note: port 5000 is reserved on
  this Windows box (use 8000+). Session cookie is `Secure`, so authenticated flows only work
  over HTTPS or `localhost` in a browser (curl over plain HTTP won't keep the session).
- `app.js` is served with `Cache-Control max-age=3600` — hard-refresh (Ctrl+F5) after JS edits.
