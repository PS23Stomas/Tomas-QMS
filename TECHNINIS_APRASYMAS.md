# Tomas-QMS — Techninis aprašymas

> Gamybos kokybės valdymo sistema, UAB „ELGA". Dokumentas aprašo, kaip sistema
> veikia techniškai: architektūra, prisijungimas prie DB, autentifikacija, sauga,
> failų saugojimas ir paleidimas. Atnaujinta: 2026-06-23.

---

## 1. Bendras vaizdas

**Tomas-QMS** — žiniatinklio sistema modulinių transformatorių (MT) ir kitų gaminių
gamybos kokybės kontrolei: užsakymai, gaminiai, funkciniai/dielektriniai bandymai,
komponentai, gaminių pasai (PDF), matavimo prietaisų kalibravimas, klientų pretenzijos.

- **Kalba:** kodas, komentarai, sąsaja ir DB stulpeliai — **lietuvių**.
- **Apimtis:** ~26 000 eilučių, ~90 PHP failų.
- **Produkcija:** https://nkokybe.elga.tech (veikia Replit debesyje).

## 2. Technologijų rinkinys

| Sluoksnis | Technologija |
|---|---|
| Kalba | **PHP 8.3** (be karkaso / framework) |
| Duomenų bazė | **PostgreSQL 16** (hostinama **Supabase**) per **PDO** |
| PDF generavimas | **mPDF** (`mpdf/mpdf` — vienintelė reali priklausomybė) |
| El. paštas | **Resend API** (`Emailas` klasė) |
| Serveris | PHP integruotas serveris (`php -S`), maršrutizatorius `public/router.php` |
| Front-end | Gryna JS (`public/js/app.js`) + inline CSS/JS puslapiuose |

Konfigūracija — per **aplinkos kintamuosius**: `DATABASE_URL`, `RESEND_API_KEY`,
`BASE_URL`, (nebūtinas) `APP_DEBUG`.

## 3. Užklausos kelias (request flow)

```
Naršyklė → php -S → public/router.php → konkretus puslapis (pvz. uzsakymai.php)
                                          └→ require includes/config.php
                                          └→ requireLogin()
                                          └→ (POST atveju) requireWrite()
                                          └→ SQL per $pdo → HTML išvestis
```

1. **`public/router.php`** — visų užklausų įėjimo taškas. Aptarnauja statinius failus
   (CSS/JS/paveikslėliai su `Cache-Control`), `/` nukreipia į `/login.php`, kitas
   užklausas perduoda atitinkamam `.php` failui.
2. **`public/includes/config.php`** — kiekvieno puslapio „branduolys" (žr. 4 skyrių).
3. Puslapis pats yra **ir valdiklis, ir modelis, ir vaizdas** (procedūrinis stilius —
   HTML + SQL + logika viename faile). Karkaso/MVC sluoksnio nėra.

## 4. `config.php` — paleidimo seka

Įtraukiamas kiekvieno apsaugoto puslapio pradžioje (`require_once includes/config.php`).
Atlieka:

0. **Klaidų hardening** — `display_errors=Off` (produkcijoje), `log_errors=On`.
   Derinimui: nustatyti `APP_DEBUG=1` (tada klaidos rodomos).
1. Įkelia klases iš `public/klases/` (`Database`, `Sesija`, `DBMigracija`, `Gaminys`,
   `Komponentas`, `Emailas`, `TomoQMS`).
2. `Sesija::pradzia()` — paleidžia/atnaujina sesiją.
3. `$pdo = Database::getConnection()` — vienas DB ryšys visai užklausai.
4. Apibrėžia globalias funkcijas: `h()`, `requireLogin()`, `currentUser()`,
   `getBaseUrl()`, `getImonesNustatymai()`, `csrfToken()`, `csrfVerify()`, `requireWrite()`.

## 5. Prisijungimas prie duomenų bazės

### `public/klases/Database.php` (Singleton)
- Vienas `PDO` objektas visai užklausai (`Database::getConnection()`).
- Prisijungimo eilutė imama iš **`DATABASE_URL`** aplinkos kintamojo, išparsinama
  per `parse_url()` (host, port, dbname, user, pass) ir sudaromas DSN:
  ```php
  $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
  PDO opcijos: ERRMODE_EXCEPTION, DEFAULT_FETCH_MODE = FETCH_ASSOC
  ```
- Jei `DATABASE_URL` nenustatytas → `die('DATABASE_URL not set')`.

### Supabase (PostgreSQL)
- DB hostinama **Supabase**, prisijungiama per **Session pooler** (IPv4):
  ```
  postgresql://postgres.<projektas>:<slaptazodis>@aws-1-eu-north-1.pooler.supabase.com:5432/postgres
  ```
- Naudojamas Session pooler (portas **5432**), **ne** Transaction pooler (6543) —
  nes programa naudoja sekas ir `lastInsertId()`.
- `login.php` sąmoningai **nenaudoja** `config.php` ir DB ryšį kuria pats (inline) —
  bet ta pati `DATABASE_URL` eilutė.

### Svarbu
- **Visos užklausos — paruoštos (prepared statements)** su `?` arba įvardytais
  parametrais. (Vienintelė istorinė SQL injekcija `pareto.php` ištaisyta — datos validuojamos.)
- Migracija Replit/Neon → Supabase atlikta 2026-06-23 (PHP/PDO, BYTEA per base64).

### Ryšių vientisumas (FK) ir trynimas
- DB turi **26 FK apribojimus** (žr. `supabase_fk.sql`). `ON DELETE` logika:
  - **RESTRICT** — pagrindiniams ryšiams: negalima ištrinti `gaminiai` / `uzsakymai` /
    `uzsakovai` / `objektai` / `gaminio_tipai` / `gaminiu_rusys`, kol jie turi „vaikų".
  - **SET NULL** — autorystės nuorodoms į `vartotojai` (galima šalinti vartotoją, įrašai lieka).
  - **CASCADE** — `remember_tokens` ir pretenzijų vaikams (`pretenzijos_email_history`,
    `pretenzijos_failai`, `pretenzijos_nuotraukos`).
- **Trynimo teisės:** įrašų/objektų trynimas — **tik administratorius** (tikrinama kode).
  `vartotojas` gali tik redaguoti; `skaitytojas` — tik skaityti.
- **Kontroliuojamas kaskadinis trynimas:** `gaminiai` ir `uzsakymai` trynimo valdikliai
  pašalina VISUS susijusius vaikus FK-saugia tvarka **transakcijoje**, tada patį įrašą.
  Katalogo įrašai (`objektai`, `uzsakovai`) nekaskaduoja — jei naudojami, rodomas aiškus
  pranešimas (gaudoma `SQLSTATE 23503`).

## 6. Autentifikacija ir sesijos

### `public/klases/Sesija.php`
- Sesija galioja **30 min** nuo paskutinės veiklos (`SESIJOS_GALIOJIMAS = 1800`).
- Slapuko parametrai: `secure=true`, `httponly=true`, **`samesite=Lax`**.
- `pradzia()` — paleidžia sesiją, tikrina neveiklumą, atnaujina `paskutine_veikla`.
- `tikrintiPrisijungima()` / `requireLogin()` — jei neprisijungęs: AJAX → JSON klaida,
  kitaip → nukreipimas į `/login.php`.

### `public/login.php`
- Slaptažodžiai — **bcrypt** (`password_hash` / `password_verify`).
- Po sėkmingo prisijungimo — `session_regenerate_id(true)`.
- Galima jungtis **vardu arba el. paštu**.
- „Prisiminti mane" — `remember_tokens` lentelė: slapuke laikomas atsitiktinis žetonas,
  DB saugomas tik jo **SHA-256 maišos** kodas; galioja 30 d.
- Rolių normalizavimas: `admin`→`administratorius`, `user`→`vartotojas` (`normalizuotiRole`).

### Rolės
- `administratorius` — viskas.
- `vartotojas` — kūrimas/redagavimas (be kai kurių admin veiksmų).
- `skaitytojas` — **tik skaitymas** (rašymas blokuojamas serveryje, žr. 7 skyrių).

## 7. Sauga

| Priemonė | Kaip veikia |
|---|---|
| **XSS** | Visa išvestis per `h()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. |
| **SQL injekcija** | PDO prepared statements visur. |
| **CSRF** | `csrfToken()` įdeda žetoną į `<meta>` ir formas; `csrfVerify()` tikrina `_csrf` (POST) arba `X-CSRF-Token` (antraštę). `app.js` automatiškai prideda žetoną į **visas** formas IR į visas `fetch`/XHR AJAX užklausas. Papildomai `SameSite=Lax`. |
| **Rašymo apsauga** | `requireWrite()` (config.php) = `csrfVerify()` + `Sesija::blokuotiSkaitytojaVeiksma()`. Iškviečiama POST veiksmų pradžioje. `skaitytojas` rašyti negali (net per tiesioginį POST). |
| **IDOR** | Vieši pretenzijų PDF/nuotraukų endpoint'ai (`pretenzijos_pdf.php`, `pretenzija_defekto_pdf.php`, `pretenzijos_nuotrauka.php`) leidžia prieigą tik prisijungus **arba** su galiojančiu `perziuros_token`, sutampančiu su tos pretenzijos žetonu. |
| **Klaidų slėpimas** | `display_errors=Off` produkcijoje — klaidos į žurnalą, ne vartotojui. |
| **Slaptažodžiai** | bcrypt; jokių slaptažodžių kode (viskas iš env). |
| **Failų įkėlimas** | `MT/ikelti_pdf.php` — pavyzdinis: tikrina MIME (finfo) + plėtinį + dydį. |

> Žinomi likę punktai (žemos rizikos): keli admin/parašų valdikliai dar be `requireWrite`;
> `pretenzijos_atsakymas.php` naudoja eilinį `id`; slaptažodžio atstatymo žetono enumeracija.

## 8. Failų ir dvejetainių duomenų saugojimas

- PDF, nuotraukos, parašai, logotipas saugomi **tiesiai DB kaip `BYTEA`** (ne failų sistemoje):
  - `gaminiai.mt_paso_pdf`, `mt_dielektriniu_pdf`, `mt_funkciniu_pdf`
  - `gaminiu_pdf_failai.turinys`, `gvx_dokumentai.turinys_lob`
  - `pretenzijos.defekto_pdf_turinys`, `pretenzijos_nuotraukos.turinys`, `pretenzijos_failai.turinys`
  - `funkciniai_bandymai.defekto_nuotrauka`, `vartotojai.parasas`, `imones_nustatymai.logotipas`
  - `prietaisai.sertifikato_pdf`
- ELGA parašo paveikslėlis laikomas **už web šaknies**: `private/img/parasas_elga.jpg`.
- PDF generuojami su **mPDF** (`MT/generuoti_*`, `*_pdf.php`, `pretenzijos_pdf_gen.php`).

## 9. DB migracijos

- **`public/klases/DBMigracija.php`** — idempotentinė automigracija (tikrina
  `information_schema` prieš keisdama). **Nepaleidžiama automatiškai** užklausos metu.
- Paleidimas: CLI `php migracija.php` arba `/migracija_admin.php` (tik admin).
- Schemos failai (Supabase): `supabase_schema.sql` + `supabase_schema_patch.sql`;
  vartotojų sėjimas — `insert_vartotojai.sql`.

## 10. Katalogų struktūra

```
public/              web šaknis — PHP puslapiai (valdiklis+modelis+vaizdas kartu)
public/includes/     config.php (paleidimas, pagalbinės f-jos), header.php, footer.php
public/klases/       Database, Sesija, DBMigracija, Gaminys, Komponentas, Emailas,
                     MTPasasKomponentai, TomoQMS
public/MT/           MT modulio puslapiai ir PDF generatoriai
public/api/          maži AJAX endpoint'ai (quick_add, parašai, nuotraukų trynimas)
public/css|js/       style.css, app.js
private/img/         parasas_elga.jpg (už web šaknies)
vendor/              mPDF biblioteka (Composer)
docs/                dokumentacija (nepasiekiama iš interneto)
*.sql                schemos ir migracijų failai
start.bat / start.sh paleidimo skriptai (Windows / Unix)
```

## 11. Kaip paleisti

### Lokaliai (Windows, XAMPP)
```bat
start.bat   :: nustato DATABASE_URL ir paleidžia php -S localhost:8000
```
- PHP: `C:\xampp\php\php.exe` su įjungtu `pdo_pgsql`/`pgsql` plėtiniu.
- Naršyklė: http://localhost:8000
- Pastaba: portas 5000 šiame Windows rezervuotas → naudoti 8000+.
- Sesijos slapukas `Secure` → autentifikacija veikia tik per HTTPS arba `localhost` naršyklėje
  (`curl` per gryną HTTP sesijos neišlaiko).

### Produkcija (Replit)
- `start.sh` → `php -S 0.0.0.0:5000 -t public public/router.php`.
- `DATABASE_URL` (Supabase pooler) nustatomas per Replit **Secrets**.
- Po JS pakeitimų — kietas atnaujinimas (Ctrl+F5), nes `app.js` kešuojamas (`max-age=3600`).

## 12. Aplinkos kintamieji

| Kintamasis | Paskirtis |
|---|---|
| `DATABASE_URL` | PostgreSQL/Supabase prisijungimas (privalomas) |
| `RESEND_API_KEY` | El. pašto siuntimas per Resend |
| `BASE_URL` | Bazinis URL nuorodoms el. laiškuose |
| `APP_DEBUG` | `1` → rodyti klaidas (tik derinimui) |
