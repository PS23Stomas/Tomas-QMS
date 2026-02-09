==============================================
 MT MODULIO DIEGIMO INSTRUKCIJA
==============================================

Reikalavimai:
  - PHP 8.2 arba naujesnė versija
  - PostgreSQL duomenų bazė
  - PHP plėtiniai: pdo_pgsql, zip, mbstring

1. DUOMENŲ BAZĖS NUSTATYMAS
-------------------------------------------
  a) Sukurkite naują PostgreSQL duomenų bazę:
     CREATE DATABASE mt_modulis;

  b) Importuokite SQL failą:
     psql -U jusu_vartotojas -d mt_modulis -f duomenu_baze.sql

2. KONFIGŪRACIJA
-------------------------------------------
  Redaguokite db.php failą ir nustatykite aplinkos kintamuosius
  arba pakeiskite prisijungimo duomenis tiesiogiai:

  Aplinkos kintamieji:
    PGHOST=localhost
    PGPORT=5432
    PGDATABASE=mt_modulis
    PGUSER=jusu_vartotojas
    PGPASSWORD=jusu_slaptazodis

3. PALEIDIMAS
-------------------------------------------
  Paleiskite PHP serverį:
    php -S 0.0.0.0:5000 -c php.ini

  Naršyklėje atidarykite:
    http://localhost:5000/prisijungimas.php

4. PASTABOS
-------------------------------------------
  - Visi vartotojai ir jų slaptažodžiai eksportuoti iš
    pradinės sistemos duomenų bazės.
  - Vendor katalogas jau įtrauktas, composer install nereikalingas.
  - Eksportuota: 2026-02-09 13:18:35
