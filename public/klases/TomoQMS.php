<?php
/**
 * Tomo QMS sinchronizacijos klasė
 *
 * Tomo QMS — tai išorinė duomenų bazė, naudojama gamybos valdymui.
 * Ši klasė atsakinga už duomenų perkėlimą iš mūsų sistemos į tą išorinę bazę
 * ir atgal — tai vadinama sinchronizacija (sutrumpintai: "sinch").
 *
 * Pavyzdys: kai sukuriamas naujas užsakymas mūsų sistemoje,
 * sinchronizacija jį nukopijuoja ir į Tomo QMS, kad abu sistemoje būtų vienodi duomenys.
 *
 * Ši klasė taip pat valdo sinchronizacijos žurnalą (sync_log) —
 * kiekvienas sinchronizacijos veiksmas užregistruojamas, kad galima būtų
 * patikrinti ar viskas vyko sėkmingai.
 *
 * Naudojama: sinchronizuoti.php, sync_log.php
 */
class TomoQMS {
    private static ?PDO $conn = null;
    private static bool $available = true;
    private static bool $logTableChecked = false;

    /**
     * Prisijungia prie išorinės Tomo QMS duomenų bazės.
     * Jei TOMO_QMS_DATABASE_URL aplinkos kintamasis nenustatytas —
     * grąžina null (sinchronizacija neveiks, bet programa nesuges).
     * Prisijungimas sukuriamas tik kartą ir išsaugomas atmintyje.
     */
    public static function getConnection(): ?PDO {
        if (!self::$available) return null;
        if (self::$conn !== null) return self::$conn;

        $url = getenv('TOMO_QMS_DATABASE_URL');
        if (!$url) {
            self::$available = false;
            return null;
        }

        try {
            $parts = parse_url($url);
            if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'], $parts['path'])) {
                self::$available = false;
                return null;
            }
            $dsn = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432) . ';dbname=' . ltrim($parts['path'], '/');
            if (strpos($url, 'sslmode=require') !== false) {
                $dsn .= ';sslmode=require';
            }
            self::$conn = new PDO($dsn, $parts['user'], $parts['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::uztikrinitiLogLentele();
            return self::$conn;
        } catch (Exception $e) {
            error_log('TomoQMS prisijungimo klaida: ' . $e->getMessage());
            self::$available = false;
            return null;
        }
    }

    /**
     * Patikrina ar sync_log lentelė egzistuoja Tomo QMS duomenų bazėje,
     * ir jei ne — ją sukuria. Ši lentelė saugo sinchronizacijos žurnalą.
     * Kviečiama automatiškai pirmą kartą prisijungus.
     */
    private static function uztikrinitiLogLentele(): void {
        if (self::$logTableChecked || !self::$conn) return;
        try {
            self::$conn->exec("
                CREATE TABLE IF NOT EXISTS sync_log (
                    id SERIAL PRIMARY KEY,
                    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    veiksmas VARCHAR(100) NOT NULL,
                    lentele VARCHAR(100),
                    uzsakymo_numeris VARCHAR(100),
                    irasu_kiekis INTEGER DEFAULT 0,
                    statusas VARCHAR(20) DEFAULT 'ok',
                    klaida TEXT,
                    vartotojas VARCHAR(100)
                )
            ");
            self::$logTableChecked = true;
        } catch (Exception $e) {
            error_log('TomoQMS sync_log lentelės klaida: ' . $e->getMessage());
        }
    }

    /**
     * Įrašo vieną eilutę į sinchronizacijos žurnalą.
     * Kiekvieną kartą kai sinchronizuojami duomenys — užregistruojamas veiksmas:
     * kas sinchronizuota, kiek įrašų, ar pavyko, ir kas tai padarė.
     *
     * @param string $veiksmas        Trumpas veiksmo aprašymas (pvz. "Sukurtas užsakymas")
     * @param string|null $lentele   Lentelės pavadinimas (pvz. "uzsakymai")
     * @param string|null $uzsakymo_numeris Susijęs užsakymo numeris
     * @param int $irasu_kiekis       Kiek įrašų buvo apdorota
     * @param string $statusas        "ok" arba "klaida"
     * @param string|null $klaida     Klaidos pranešimas jei nepavyko
     */
    public static function irasytLog(string $veiksmas, ?string $lentele = null, ?string $uzsakymo_numeris = null, int $irasu_kiekis = 0, string $statusas = 'ok', ?string $klaida = null): void {
        $conn = self::getConnection();
        if (!$conn) return;
        try {
            $vartotojas = null;
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['vardas'])) {
                $vartotojas = $_SESSION['vardas'];
            }
            $stmt = $conn->prepare("INSERT INTO sync_log (veiksmas, lentele, uzsakymo_numeris, irasu_kiekis, statusas, klaida, vartotojas) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$veiksmas, $lentele, $uzsakymo_numeris, $irasu_kiekis, $statusas, $klaida, $vartotojas]);
        } catch (Exception $e) {
            error_log('TomoQMS sync_log rašymo klaida: ' . $e->getMessage());
        }
    }

    /**
     * Grąžina sinchronizacijos žurnalo įrašus, surikiuotus nuo naujausio.
     * Naudojama sync_log.php puslapyje rodyti sinchronizacijos istoriją.
     *
     * @param int $limit  Kiek įrašų grąžinti (numatyta: 100)
     * @param int $offset Nuo kurio įrašo pradėti (puslapiavimui)
     */
    public static function gautiSyncLog(int $limit = 100, int $offset = 0): array {
        $conn = self::getConnection();
        if (!$conn) return [];
        try {
            $stmt = $conn->prepare("SELECT * FROM sync_log ORDER BY data DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Grąžina bendrą sinchronizacijos žurnalo įrašų skaičių.
     * Naudojama puslapiavimui — kad žinotume kiek iš viso yra puslapių.
     */
    public static function gautiSyncLogKieki(): int {
        $conn = self::getConnection();
        if (!$conn) return 0;
        try {
            return (int)$conn->query("SELECT COUNT(*) FROM sync_log")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Ieško užsakovo pagal pavadinimą Tomo QMS duomenų bazėje.
     * Jei rastas — grąžina jo ID. Jei nerastas — sukuria naują ir grąžina naują ID.
     * Taip išvengiama užsakovų dublikavimo sinchronizacijos metu.
     *
     * @param string $uzsakovas_pav Užsakovo pavadinimas (pvz. "UAB Statybos darbai")
     * @return int|null             Užsakovo ID arba null jei klaida
     */
    public static function gautiArbaKurtiUzsakova(string $uzsakovas_pav): ?int {
        $conn = self::getConnection();
        if (!$conn || trim($uzsakovas_pav) === '') return null;
        try {
            $stmt = $conn->prepare("SELECT id FROM uzsakovai WHERE TRIM(uzsakovas) = TRIM(:pav)");
            $stmt->execute([':pav' => $uzsakovas_pav]);
            $id = $stmt->fetchColumn();
            if ($id) return (int)$id;
            $stmt = $conn->prepare("INSERT INTO uzsakovai (uzsakovas) VALUES (:pav) RETURNING id");
            $stmt->execute([':pav' => $uzsakovas_pav]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('TomoQMS uzsakovas klaida: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ieško objekto (statybos vietos) pagal pavadinimą Tomo QMS duomenų bazėje.
     * Jei rastas — grąžina jo ID. Jei nerastas — sukuria naują ir grąžina naują ID.
     *
     * @param string $objektas_pav Objekto pavadinimas (pvz. "Šiaulių elektrinė")
     * @return int|null            Objekto ID arba null jei klaida
     */
    public static function gautiArbaKurtiObjekta(string $objektas_pav): ?int {
        $conn = self::getConnection();
        if (!$conn || trim($objektas_pav) === '') return null;
        try {
            $stmt = $conn->prepare("SELECT id FROM objektai WHERE TRIM(pavadinimas) = TRIM(:pav)");
            $stmt->execute([':pav' => $objektas_pav]);
            $id = $stmt->fetchColumn();
            if ($id) return (int)$id;
            $stmt = $conn->prepare("INSERT INTO objektai (pavadinimas) VALUES (:pav) RETURNING id");
            $stmt->execute([':pav' => $objektas_pav]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('TomoQMS objektas klaida: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sinchronizuoja užsakymą į Tomo QMS duomenų bazę.
     * Jei užsakymas ten jau egzistuoja (pagal numerį) — atnaujina.
     * Jei neegzistuoja — sukuria naują. Taip pat sukuria/atnaujina
     * susijusį užsakovą ir objektą.
     *
     * @return int|null Užsakymo ID Tomo QMS bazėje arba null jei klaida
     */
    public static function sinchronizuotiUzsakyma(string $uzsakymo_numeris, ?string $uzsakovas_pav, ?string $objektas_pav, int $kiekis = 1, int $vartotojas_id = 1, ?int $gaminiu_rusis_id = null, ?string $sukurtas = null): ?int {
        $conn = self::getConnection();
        if (!$conn || trim($uzsakymo_numeris) === '') return null;
        try {
            $stmt = $conn->prepare("SELECT id FROM uzsakymai WHERE TRIM(uzsakymo_numeris) = TRIM(:nr)");
            $stmt->execute([':nr' => $uzsakymo_numeris]);
            $uzs_id = $stmt->fetchColumn();

            $uzsakovas_id = $uzsakovas_pav ? self::gautiArbaKurtiUzsakova($uzsakovas_pav) : null;
            $objektas_id = $objektas_pav ? self::gautiArbaKurtiObjekta($objektas_pav) : null;

            if ($uzs_id) {
                $sql = "UPDATE uzsakymai SET kiekis = :kiekis, uzsakovas_id = :uzs_id, objektas_id = :obj_id, gaminiu_rusis_id = :rusis";
                $params = [':kiekis' => $kiekis, ':uzs_id' => $uzsakovas_id, ':obj_id' => $objektas_id, ':rusis' => $gaminiu_rusis_id, ':id' => $uzs_id];
                if ($sukurtas) {
                    $sql .= ", sukurtas = :sukurtas";
                    $params[':sukurtas'] = $sukurtas;
                }
                $sql .= " WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                self::irasytLog('Atnaujintas užsakymas', 'uzsakymai', $uzsakymo_numeris, 1);
                return (int)$uzs_id;
            } else {
                $cols = "uzsakymo_numeris, kiekis, uzsakovas_id, objektas_id, vartotojas_id, gaminiu_rusis_id";
                $vals = ":nr, :kiekis, :uzs_id, :obj_id, :vart_id, :rusis";
                $params = [':nr' => $uzsakymo_numeris, ':kiekis' => $kiekis, ':uzs_id' => $uzsakovas_id, ':obj_id' => $objektas_id, ':vart_id' => $vartotojas_id, ':rusis' => $gaminiu_rusis_id];
                if ($sukurtas) {
                    $cols .= ", sukurtas";
                    $vals .= ", :sukurtas";
                    $params[':sukurtas'] = $sukurtas;
                }
                $stmt = $conn->prepare("INSERT INTO uzsakymai ($cols) VALUES ($vals) RETURNING id");
                $stmt->execute($params);
                self::irasytLog('Sukurtas užsakymas', 'uzsakymai', $uzsakymo_numeris, 1);
                return (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            self::irasytLog('Užsakymo sinch. klaida', 'uzsakymai', $uzsakymo_numeris, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS uzsakymas klaida: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sinchronizuoja gaminio tipą į Tomo QMS duomenų bazę.
     * Jei gaminio tipas ten jau egzistuoja (pagal ID) — nieko nedaro.
     * Jei neegzistuoja — nukopijuoja iš mūsų lokalios bazės.
     * Kviečiama prieš sinchronizuojant gaminį — kad tipas jau būtų ten.
     *
     * @param PDO $localConn  Ryšys su mūsų lokalia duomenų baze
     * @param int|null $tipas_id Gaminio tipo ID
     */
    public static function sinchGaminioTipa(PDO $localConn, ?int $tipas_id): void {
        if (!$tipas_id) return;
        $conn = self::getConnection();
        if (!$conn) return;
        try {
            $exists = $conn->prepare("SELECT id FROM gaminio_tipai WHERE id = ?");
            $exists->execute([$tipas_id]);
            if ($exists->fetchColumn()) return;
            $stmt = $localConn->prepare("SELECT id, gaminio_tipas, grupe, atitikmuo_kodas FROM gaminio_tipai WHERE id = ?");
            $stmt->execute([$tipas_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $conn->prepare("INSERT INTO gaminio_tipai (id, gaminio_tipas, grupe, atitikmuo_kodas) VALUES (?, ?, ?, ?)")->execute([$row['id'], $row['gaminio_tipas'], $row['grupe'], $row['atitikmuo_kodas']]);
            }
        } catch (Exception $e) {
            error_log('TomoQMS sinchGaminioTipa klaida: ' . $e->getMessage());
        }
    }

    /**
     * Ieško gaminio Tomo QMS duomenų bazėje pagal užsakymo ID ir gaminio numerį.
     * Jei rastas — grąžina jo ID. Jei nerastas — sukuria naują gaminį ir grąžina naują ID.
     * Kviečiama sinchronizuojant kiekvieną gaminį iš užsakymo.
     *
     * @param int $uzsakymo_id_tomo  Užsakymo ID Tomo QMS bazėje
     * @param string|null $gaminio_numeris Gaminio serijos numeris
     * @param int|null $gaminio_tipas_id   Gaminio tipo ID
     * @param string|null $protokolo_nr    Protokolo numeris
     * @return int|null                    Gaminio ID Tomo QMS bazėje arba null jei klaida
     */
    public static function gautiArbaKurtiGamini(int $uzsakymo_id_tomo, ?string $gaminio_numeris = null, ?int $gaminio_tipas_id = null, ?string $protokolo_nr = null): ?int {
        $conn = self::getConnection();
        if (!$conn) return null;
        try {
            $stmt = $conn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id = :uid ORDER BY id ASC LIMIT 1");
            $stmt->execute([':uid' => $uzsakymo_id_tomo]);
            $gid = $stmt->fetchColumn();

            if ($gid) {
                $sets = [];
                $params = [':id' => $gid];
                if ($gaminio_numeris !== null) { $sets[] = "gaminio_numeris = :gn"; $params[':gn'] = $gaminio_numeris; }
                if ($gaminio_tipas_id !== null) { $sets[] = "gaminio_tipas_id = :gti"; $params[':gti'] = $gaminio_tipas_id; }
                if ($protokolo_nr !== null) { $sets[] = "protokolo_nr = :pnr"; $params[':pnr'] = $protokolo_nr; }
                if (!empty($sets)) {
                    $conn->prepare("UPDATE gaminiai SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
                }
                return (int)$gid;
            } else {
                $stmt = $conn->prepare("INSERT INTO gaminiai (uzsakymo_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr) VALUES (:uid, :gn, :gti, :pnr) RETURNING id");
                $stmt->execute([':uid' => $uzsakymo_id_tomo, ':gn' => $gaminio_numeris, ':gti' => $gaminio_tipas_id, ':pnr' => $protokolo_nr]);
                return (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log('TomoQMS gaminys klaida: ' . $e->getMessage());
            return null;
        }
    }

    public static function gautiTomoGaminioId(PDO $localConn, int $local_gaminio_id): ?int {
        try {
            $stmt = $localConn->prepare("
                SELECT u.uzsakymo_numeris, g.gaminio_numeris, g.gaminio_tipas_id, g.protokolo_nr,
                       uz.uzsakovas, o.pavadinimas as objektas, u.kiekis, u.gaminiu_rusis_id, u.sukurtas
                FROM gaminiai g
                JOIN uzsakymai u ON u.id = g.uzsakymo_id
                LEFT JOIN uzsakovai uz ON uz.id = u.uzsakovas_id
                LEFT JOIN objektai o ON o.id = u.objektas_id
                WHERE g.id = :gid
            ");
            $stmt->execute([':gid' => $local_gaminio_id]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$info || !$info['uzsakymo_numeris']) return null;

            if ($info['gaminio_tipas_id']) {
                self::sinchGaminioTipa($localConn, (int)$info['gaminio_tipas_id']);
            }

            $tomo_uzs_id = self::sinchronizuotiUzsakyma(
                $info['uzsakymo_numeris'],
                $info['uzsakovas'] ?? null,
                $info['objektas'] ?? null,
                (int)($info['kiekis'] ?? 1),
                1,
                $info['gaminiu_rusis_id'] ? (int)$info['gaminiu_rusis_id'] : null,
                $info['sukurtas'] ?? null
            );
            if (!$tomo_uzs_id) return null;

            return self::gautiArbaKurtiGamini(
                $tomo_uzs_id,
                $info['gaminio_numeris'],
                $info['gaminio_tipas_id'] ? (int)$info['gaminio_tipas_id'] : null,
                $info['protokolo_nr']
            );
        } catch (Exception $e) {
            error_log('TomoQMS gautiTomoGaminioId klaida: ' . $e->getMessage());
            return null;
        }
    }

    private static function gautiUzsakymoNr(PDO $localConn, int $local_gaminio_id): ?string {
        try {
            $stmt = $localConn->prepare("SELECT u.uzsakymo_numeris FROM gaminiai g JOIN uzsakymai u ON u.id = g.uzsakymo_id WHERE g.id = ?");
            $stmt->execute([$local_gaminio_id]);
            return $stmt->fetchColumn() ?: null;
        } catch (Exception $e) { return null; }
    }

    public static function sinchFunkciniai(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $stmt = $localConn->prepare("SELECT eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas, defekto_nuotrauka, defekto_nuotraukos_pavadinimas FROM funkciniai_bandymai WHERE gaminio_id = ? ORDER BY eil_nr");
            $stmt->execute([$local_gaminio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->beginTransaction();
            $conn->prepare("DELETE FROM funkciniai_bandymai WHERE gaminio_id = ?")->execute([$tomo_gid]);
            $ins = $conn->prepare("INSERT INTO funkciniai_bandymai (gaminio_id, eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas, defekto_nuotrauka, defekto_nuotraukos_pavadinimas) VALUES (:gid, :enr, :reik, :isv, :def, :da, :iv, :foto, :fpav)");
            foreach ($rows as $r) {
                $ins->bindValue(':gid', $tomo_gid);
                $ins->bindValue(':enr', $r['eil_nr']);
                $ins->bindValue(':reik', $r['reikalavimas']);
                $ins->bindValue(':isv', $r['isvada']);
                $ins->bindValue(':def', $r['defektas']);
                $ins->bindValue(':da', $r['darba_atliko']);
                $ins->bindValue(':iv', $r['irase_vartotojas']);
                if ($r['defekto_nuotrauka'] !== null) {
                    $ins->bindValue(':foto', $r['defekto_nuotrauka'], PDO::PARAM_LOB);
                } else {
                    $ins->bindValue(':foto', null, PDO::PARAM_NULL);
                }
                $ins->bindValue(':fpav', $r['defekto_nuotraukos_pavadinimas']);
                $ins->execute();
            }
            $conn->commit();
            self::irasytLog('Funkciniai bandymai', 'funkciniai_bandymai', $uzs_nr, count($rows));
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Funkcinių band. klaida', 'funkciniai_bandymai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchFunkciniai klaida: ' . $e->getMessage());
        }
    }

    public static function sinchKomponentai(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $stmt = $localConn->prepare("SELECT eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui FROM komponentai WHERE gaminio_id = ?");
            $stmt->execute([$local_gaminio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->beginTransaction();
            $conn->prepare("DELETE FROM komponentai WHERE gaminio_id = ?")->execute([$tomo_gid]);
            $ins = $conn->prepare("INSERT INTO komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($rows as $r) {
                $ins->execute([$tomo_gid, $r['eiles_numeris'], $r['gamintojo_kodas'], $r['kiekis'], $r['aprasymas'], $r['gamintojas'], $r['parinkta_projektui']]);
            }
            $conn->commit();
            self::irasytLog('Komponentai', 'komponentai', $uzs_nr, count($rows));
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Komponentų klaida', 'komponentai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchKomponentai klaida: ' . $e->getMessage());
        }
    }

    public static function sinchDielektriniai(PDO $localConn, int $local_gaminys_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminys_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminys_id);
        if (!$tomo_gid) return;
        try {
            $conn->beginTransaction();

            $stmt = $localConn->prepare("SELECT eiles_nr, aprasymas, itampa, schema1, schema2, schema3, schema4, schema5, schema6, isvada, tipas, grandines_pavadinimas, grandines_itampa, bandymo_schema, bandymo_itampa_kv, bandymo_trukme FROM dielektriniai_bandymai WHERE gaminys_id = ?");
            $stmt->execute([$local_gaminys_id]);
            $all_diel_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->prepare("DELETE FROM antriniu_grandiniu_bandymai WHERE gaminys_id = ?")->execute([$tomo_gid]);
            $ins1 = $conn->prepare("INSERT INTO antriniu_grandiniu_bandymai (gaminys_id, eiles_nr, grandines_pavadinimas, grandines_itampa, bandymo_schema, bandymo_itampa_kv, bandymo_trukme, isvada) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $conn->prepare("DELETE FROM dielektriniai_bandymai WHERE gaminys_id = ?")->execute([$tomo_gid]);
            $ins2 = $conn->prepare("INSERT INTO dielektriniai_bandymai (gaminys_id, eiles_nr, aprasymas, itampa, schema1, schema2, schema3, schema4, schema5, schema6, isvada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($all_diel_rows as $r) {
                if (($r['tipas'] ?? '') === 'vidutines_itampos') {
                    $ins1->execute([$tomo_gid, $r['eiles_nr'], $r['grandines_pavadinimas'], $r['grandines_itampa'], $r['bandymo_schema'], $r['bandymo_itampa_kv'], $r['bandymo_trukme'], $r['isvada']]);
                } else {
                    $ins2->execute([$tomo_gid, $r['eiles_nr'], $r['aprasymas'], $r['itampa'], $r['schema1'], $r['schema2'], $r['schema3'], $r['schema4'], $r['schema5'], $r['schema6'], $r['isvada']]);
                }
            }

            $stmt = $localConn->prepare("SELECT eil_nr, tasko_pavadinimas, matavimo_tasku_skaicius, varza_ohm, budas, bukle FROM izeminimo_tikrinimas WHERE gaminys_id = ?");
            $stmt->execute([$local_gaminys_id]);
            $iz_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $conn->prepare("DELETE FROM izeminimo_tikrinimas WHERE gaminys_id = ?")->execute([$tomo_gid]);
            $ins3 = $conn->prepare("INSERT INTO izeminimo_tikrinimas (gaminys_id, eil_nr, tasko_pavadinimas, matavimo_tasku_skaicius, varza_ohm, budas, bukle) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($iz_rows as $r) {
                $ins3->execute([$tomo_gid, $r['eil_nr'], $r['tasko_pavadinimas'], $r['matavimo_tasku_skaicius'], $r['varza_ohm'], $r['budas'], $r['bukle']]);
            }

            $conn->commit();
            $total = count($vid_rows) + count($maz_rows) + count($iz_rows);
            self::irasytLog('Dielektriniai bandymai', 'dielektriniai_bandymai', $uzs_nr, $total);
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Dielektrinių klaida', 'dielektriniai_bandymai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchDielektriniai klaida: ' . $e->getMessage());
        }
    }

    public static function sinchSaugiklius(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $stmt = $localConn->prepare("SELECT sekcija, pozicija, gabaritas, nominalas, pozicijos_numeris FROM saugikliu_ideklai WHERE gaminio_id = ?");
            $stmt->execute([$local_gaminio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->beginTransaction();
            $conn->prepare("DELETE FROM saugikliu_ideklai WHERE gaminio_id = ?")->execute([$tomo_gid]);
            $ins = $conn->prepare("INSERT INTO saugikliu_ideklai (gaminio_id, sekcija, pozicija, gabaritas, nominalas, pozicijos_numeris) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($rows as $r) {
                $ins->execute([$tomo_gid, $r['sekcija'], $r['pozicija'], $r['gabaritas'], $r['nominalas'], $r['pozicijos_numeris']]);
            }
            $conn->commit();
            self::irasytLog('Saugikliai', 'saugikliu_ideklai', $uzs_nr, count($rows));
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Saugiklių klaida', 'saugikliu_ideklai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchSaugiklius klaida: ' . $e->getMessage());
        }
    }

    public static function sinchPrietaisus(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $stmt = $localConn->prepare("SELECT prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr FROM bandymai_prietaisai WHERE gaminys_id = ?");
            $stmt->execute([$local_gaminio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->beginTransaction();
            $conn->prepare("DELETE FROM bandymai_prietaisai WHERE gaminys_id = ?")->execute([$tomo_gid]);
            $ins = $conn->prepare("INSERT INTO bandymai_prietaisai (gaminys_id, prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($rows as $r) {
                $ins->execute([$tomo_gid, $r['prietaiso_tipas'], $r['prietaiso_nr'], $r['patikra_data'], $r['galioja_iki'], $r['sertifikato_nr']]);
            }
            $conn->commit();
            self::irasytLog('Bandymų prietaisai', 'bandymai_prietaisai', $uzs_nr, count($rows));
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Prietaisų klaida', 'bandymai_prietaisai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchPrietaisus klaida: ' . $e->getMessage());
        }
    }

    public static function sinchProtokoloNr(PDO $localConn, int $local_gaminio_id, string $protokolo_nr): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $conn->prepare("UPDATE gaminiai SET protokolo_nr = ? WHERE id = ?")->execute([$protokolo_nr, $tomo_gid]);
            self::irasytLog('Protokolo Nr.', 'gaminiai', $uzs_nr, 1);
        } catch (Exception $e) {
            self::irasytLog('Protokolo Nr. klaida', 'gaminiai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchProtokoloNr klaida: ' . $e->getMessage());
        }
    }

    public static function sinchPasoTeksta(PDO $localConn, int $local_gaminio_id, string $field_key, string $lang, string $tekstas): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $sql = "INSERT INTO paso_teksto_korekcijos (gaminio_id, field_key, lang, tekstas, updated_at)
                    VALUES (:gid, :fk, :lang, :txt, CURRENT_TIMESTAMP)
                    ON CONFLICT (gaminio_id, field_key, lang) 
                    DO UPDATE SET tekstas = EXCLUDED.tekstas, updated_at = CURRENT_TIMESTAMP";
            $conn->prepare($sql)->execute([':gid' => $tomo_gid, ':fk' => $field_key, ':lang' => $lang, ':txt' => $tekstas]);
            self::irasytLog('Paso tekstas', 'paso_teksto_korekcijos', $uzs_nr, 1);
        } catch (Exception $e) {
            self::irasytLog('Paso teksto klaida', 'paso_teksto_korekcijos', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchPasoTeksta klaida: ' . $e->getMessage());
        }
    }

    public static function getQualityTomasConnection(): ?PDO {
        static $qtConn = null;
        $url = getenv('QUALITY_TOMAS_DATABASE_URL');
        if (!$url) return null;
        if ($qtConn !== null) return $qtConn;
        $qtConn = self::createFreshQTConnection();
        return $qtConn;
    }

    private static function createFreshQTConnection(): ?PDO {
        $url = getenv('QUALITY_TOMAS_DATABASE_URL');
        if (!$url) return null;
        try {
            $parts = parse_url($url);
            if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'], $parts['path'])) return null;
            $dsn = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432) . ';dbname=' . ltrim($parts['path'], '/');
            if (strpos($url, 'sslmode=require') !== false) $dsn .= ';sslmode=require';
            $conn = new PDO($dsn, $parts['user'], $parts['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $conn;
        } catch (Exception $e) {
            error_log('QualityTomas prisijungimo klaida: ' . $e->getMessage());
            return null;
        }
    }

    public static function importuotiILocalDB(PDO $localConn, ?callable $progressCallback = null): array {
        $qt = self::getQualityTomasConnection();
        if (!$qt) {
            error_log('importuotiILocalDB: nepavyko prisijungti prie quality_tomas (QUALITY_TOMAS_DATABASE_URL=' . (getenv('QUALITY_TOMAS_DATABASE_URL') ? 'set' : 'NOT SET') . ')');
            return ['klaida' => 'Nepavyko prisijungti prie quality_tomas duomenų bazės'];
        }

        $rezultatas = ['nauji' => 0, 'atnaujinti' => 0, 'gaminiai' => 0, 'bandymai' => 0, 'komponentai' => 0, 'pretenzijos' => 0, 'pretenzijos_nuotraukos' => 0, 'pretenzijos_email' => 0, 'klaidos' => [], 'qt_gaminiu' => 0, 'praleisti_gaminiai' => 0, 'faze2_apdoroti' => 0, 'faze2_be_gaminiu' => 0, 'faze2_praleisti' => 0];

        try {
            $qt_cols_check = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'uzsakymai' AND table_schema = 'public'")->fetchAll(PDO::FETCH_COLUMN);
            $has_rusis = in_array('gaminiu_rusis_id', $qt_cols_check);

            $rusis_filter = $has_rusis ? "WHERE u.gaminiu_rusis_id = 2" : "";

            $stmt = $qt->query("
                SELECT u.id as qt_id, u.uzsakymo_numeris, u.sukurtas, u.kiekis,
                       " . ($has_rusis ? "u.gaminiu_rusis_id," : "2 as gaminiu_rusis_id,") . "
                       u.vartotojas_id,
                       uz.uzsakovas, o.pavadinimas as objektas
                FROM uzsakymai u
                LEFT JOIN uzsakovai uz ON uz.id = u.uzsakovas_id
                LEFT JOIN objektai o ON o.id = u.objektas_id
                $rusis_filter
                ORDER BY u.id
            ");
            $mt_uzsakymai = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $viso_uzsakymu = count($mt_uzsakymai);

            if ($progressCallback) $progressCallback(0, $viso_uzsakymu, 'Jungiamasi prie duomenų bazės...');

            $existing_local = [];
            $st = $localConn->query("SELECT id, uzsakymo_numeris FROM uzsakymai");
            foreach ($st as $r) $existing_local[trim($r['uzsakymo_numeris'])] = (int)$r['id'];

            $uzsakovai_cache = [];
            $objektai_cache = [];

            $chk_uzsakovas = $localConn->prepare("SELECT id FROM uzsakovai WHERE TRIM(uzsakovas) = TRIM(?)");
            $ins_uzsakovas = $localConn->prepare("INSERT INTO uzsakovai (uzsakovas) VALUES (?) RETURNING id");
            $chk_objektas = $localConn->prepare("SELECT id FROM objektai WHERE TRIM(pavadinimas) = TRIM(?)");
            $ins_objektas = $localConn->prepare("INSERT INTO objektai (pavadinimas) VALUES (?) RETURNING id");
            $upd_uzs = $localConn->prepare("UPDATE uzsakymai SET kiekis=?,uzsakovas_id=?,objektas_id=?,gaminiu_rusis_id=?,sukurtas=? WHERE id=?");
            $ins_uzs = $localConn->prepare("INSERT INTO uzsakymai (uzsakymo_numeris,kiekis,uzsakovas_id,objektas_id,vartotojas_id,gaminiu_rusis_id,sukurtas) VALUES (?,?,?,?,?,?,?) RETURNING id");
            $chk_vart = $localConn->prepare("SELECT id FROM vartotojai WHERE id = ?");
            $chk_gam_exists = $localConn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id = ?");
            $ins_gam_empty = $localConn->prepare("INSERT INTO gaminiai (uzsakymo_id) VALUES (?)");

            foreach ($mt_uzsakymai as $idx => $uzs) {
                if ($progressCallback && $idx % 10 === 0) {
                    $proc = (int)(($idx / max($viso_uzsakymu, 1)) * 50);
                    $progressCallback($proc, $viso_uzsakymu, 'Užsakymai: ' . ($idx + 1) . ' / ' . $viso_uzsakymu);
                }
                $nr = trim($uzs['uzsakymo_numeris'] ?? '');
                if ($nr === '') continue;

                $uzs_id_val = null;
                if (!empty($uzs['uzsakovas'])) {
                    if (!isset($uzsakovai_cache[$uzs['uzsakovas']])) {
                        $chk_uzsakovas->execute([$uzs['uzsakovas']]);
                        $uid = $chk_uzsakovas->fetchColumn();
                        if (!$uid) {
                            $ins_uzsakovas->execute([$uzs['uzsakovas']]);
                            $uid = $ins_uzsakovas->fetchColumn();
                        }
                        $uzsakovai_cache[$uzs['uzsakovas']] = (int)$uid;
                    }
                    $uzs_id_val = $uzsakovai_cache[$uzs['uzsakovas']];
                }

                $obj_id_val = null;
                if (!empty($uzs['objektas'])) {
                    if (!isset($objektai_cache[$uzs['objektas']])) {
                        $chk_objektas->execute([$uzs['objektas']]);
                        $oid = $chk_objektas->fetchColumn();
                        if (!$oid) {
                            $ins_objektas->execute([$uzs['objektas']]);
                            $oid = $ins_objektas->fetchColumn();
                        }
                        $objektai_cache[$uzs['objektas']] = (int)$oid;
                    }
                    $obj_id_val = $objektai_cache[$uzs['objektas']];
                }

                if (isset($existing_local[$nr])) {
                    $upd_uzs->execute([$uzs['kiekis'], $uzs_id_val, $obj_id_val, $uzs['gaminiu_rusis_id'], $uzs['sukurtas'], $existing_local[$nr]]);
                    $rezultatas['atnaujinti']++;
                } else {
                    $vart_id = $uzs['vartotojas_id'] ?? 1;
                    $chk_vart->execute([$vart_id]);
                    if (!$chk_vart->fetchColumn()) {
                        $vart_id = (int)$localConn->query("SELECT id FROM vartotojai ORDER BY id LIMIT 1")->fetchColumn() ?: 1;
                    }

                    $ins_uzs->execute([$nr, $uzs['kiekis'], $uzs_id_val, $obj_id_val, $vart_id, $uzs['gaminiu_rusis_id'], $uzs['sukurtas']]);
                    $new_id = (int)$ins_uzs->fetchColumn();
                    $existing_local[$nr] = $new_id;
                    $rezultatas['nauji']++;

                    $chk_gam_exists->execute([$new_id]);
                    if (!$chk_gam_exists->fetchColumn()) {
                        $ins_gam_empty->execute([$new_id]);
                    }
                }
            }

            $qt_type_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'gaminio_tipai'")->fetchAll(PDO::FETCH_COLUMN);
            $has_atitikmuo = in_array('atitikmuo_kodas', $qt_type_cols);

            $type_select = "id, gaminio_tipas, grupe" . ($has_atitikmuo ? ", atitikmuo_kodas" : ", NULL as atitikmuo_kodas");
            $types = $qt->query("SELECT $type_select FROM gaminio_tipai WHERE grupe = 'MT'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($types as $t) {
                $exists = $localConn->prepare("SELECT id FROM gaminio_tipai WHERE id = ?");
                $exists->execute([$t['id']]);
                if (!$exists->fetchColumn()) {
                    $localConn->prepare("INSERT INTO gaminio_tipai (id, gaminio_tipas, grupe, atitikmuo_kodas) VALUES (?,?,?,?)")
                        ->execute([$t['id'], $t['gaminio_tipas'], $t['grupe'], $t['atitikmuo_kodas']]);
                }
            }

            $existing_local = [];
            $st = $localConn->query("SELECT id, uzsakymo_numeris FROM uzsakymai");
            foreach ($st as $r) $existing_local[trim($r['uzsakymo_numeris'])] = (int)$r['id'];

            $qt_mt_fb_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='mt_funkciniai_bandymai'")->fetchAll(PDO::FETCH_COLUMN);
            $use_mt_fb = !empty($qt_mt_fb_cols);
            if ($use_mt_fb) {
                $qt_fb_cols = $qt_mt_fb_cols;
            } else {
                $qt_fb_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='funkciniai_bandymai'")->fetchAll(PDO::FETCH_COLUMN);
            }
            $has_photo = in_array('defekto_nuotrauka', $qt_fb_cols);
            $qt_mk_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='mt_komponentai'")->fetchAll(PDO::FETCH_COLUMN);
            $has_parinkta = in_array('parinkta_projektui', $qt_mk_cols);

            $qt_uzs_ids = array_column($mt_uzsakymai, 'qt_id');
            $qt_uzs_map = [];
            foreach ($mt_uzsakymai as $uzs) {
                $qt_uzs_map[(int)$uzs['qt_id']] = trim($uzs['uzsakymo_numeris'] ?? '');
            }

            if ($progressCallback) $progressCallback(50, $viso_uzsakymu, 'Fazė 2: kraunami gaminiai iš QT...');

            $all_qt_gaminiai = [];
            $qt_gam_by_uzs = [];
            if (!empty($qt_uzs_ids)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($qt_uzs_ids), '?'));
                    $gam_stmt = $qt->prepare("SELECT id as qt_gam_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr, uzsakymo_id FROM gaminiai WHERE uzsakymo_id IN ($placeholders)");
                    $gam_stmt->execute($qt_uzs_ids);
                    $all_qt_gaminiai = $gam_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $rezultatas['klaidos'][] = "QT gaminiai batch: {$e->getMessage()}";
                }
            }

            foreach ($all_qt_gaminiai as $g) {
                $uid = (int)$g['uzsakymo_id'];
                if (!isset($qt_gam_by_uzs[$uid])) $qt_gam_by_uzs[$uid] = [];
                $qt_gam_by_uzs[$uid][] = $g;
            }

            $all_qt_gam_ids = array_column($all_qt_gaminiai, 'qt_gam_id');

            if ($progressCallback) $progressCallback(55, $viso_uzsakymu, 'Fazė 2: kraunami bandymai iš QT...');

            if ($use_mt_fb) {
                $fb_gam_col = in_array('gaminio_id', $qt_fb_cols) ? 'gaminio_id' : 'gaminys_id';
                $fb_atliko_col = in_array('darba_atliko', $qt_fb_cols) ? 'darba_atliko' : (in_array('atliko_vardas', $qt_fb_cols) ? 'atliko_vardas' : 'darba_atliko');
                $fb_irase_col = in_array('irase_vartotojas', $qt_fb_cols) ? 'irase_vartotojas' : (in_array('pildytojas_vardas', $qt_fb_cols) ? 'pildytojas_vardas' : 'irase_vartotojas');
                $fb_table = 'mt_funkciniai_bandymai';
            } else {
                $fb_gam_col = 'gaminys_id';
                $fb_atliko_col = 'atliko_vardas';
                $fb_irase_col = 'pildytojas_vardas';
                $fb_table = 'funkciniai_bandymai';
            }
            $fb_sel_cols = "$fb_gam_col AS gaminio_id, eil_nr, reikalavimas, isvada, defektas, $fb_atliko_col AS darba_atliko, $fb_irase_col AS irase_vartotojas";
            if ($has_photo) $fb_sel_cols .= ", defekto_nuotrauka, defekto_nuotraukos_pavadinimas";
            $all_fb_by_gam = [];
            if (!empty($all_qt_gam_ids)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($all_qt_gam_ids), '?'));
                    $fb_batch = $qt->prepare("SELECT $fb_sel_cols FROM $fb_table WHERE $fb_gam_col IN ($placeholders) ORDER BY $fb_gam_col, eil_nr");
                    $fb_batch->execute($all_qt_gam_ids);
                    foreach ($fb_batch->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $gid = (int)$row['gaminio_id'];
                        if (!isset($all_fb_by_gam[$gid])) $all_fb_by_gam[$gid] = [];
                        $all_fb_by_gam[$gid][] = $row;
                    }
                } catch (Exception $e) {
                    $rezultatas['klaidos'][] = "QT bandymai batch ($fb_table): {$e->getMessage()}";
                }
            }

            if ($progressCallback) $progressCallback(60, $viso_uzsakymu, 'Fazė 2: kraunami komponentai iš QT...');

            $mk_sel = "gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas" . ($has_parinkta ? ", parinkta_projektui" : ", NULL as parinkta_projektui");
            $all_mk_by_gam = [];
            if (!empty($all_qt_gam_ids)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($all_qt_gam_ids), '?'));
                    $mk_batch = $qt->prepare("SELECT $mk_sel FROM mt_komponentai WHERE gaminio_id IN ($placeholders) ORDER BY gaminio_id, eiles_numeris");
                    $mk_batch->execute($all_qt_gam_ids);
                    foreach ($mk_batch->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $gid = (int)$row['gaminio_id'];
                        if (!isset($all_mk_by_gam[$gid])) $all_mk_by_gam[$gid] = [];
                        $all_mk_by_gam[$gid][] = $row;
                    }
                } catch (Exception $e) {
                    $rezultatas['klaidos'][] = "QT komponentai batch: {$e->getMessage()}";
                }
            }

            if ($progressCallback) $progressCallback(65, $viso_uzsakymu, 'Fazė 2: rašomi duomenys...');

            $chk_gam_by_nr = $localConn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id=? AND gaminio_numeris=?");
            $chk_gam_null = $localConn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id=? AND gaminio_numeris IS NULL LIMIT 1");
            $ins_gam = $localConn->prepare("INSERT INTO gaminiai (uzsakymo_id,gaminio_numeris,gaminio_tipas_id,protokolo_nr) VALUES (?,?,?,?) RETURNING id");
            $del_fb = $localConn->prepare("DELETE FROM funkciniai_bandymai WHERE gaminio_id = ?");
            $del_mk = $localConn->prepare("DELETE FROM komponentai WHERE gaminio_id = ?");

            if ($has_photo) {
                $ins_fb = $localConn->prepare("INSERT INTO funkciniai_bandymai (gaminio_id,eil_nr,reikalavimas,isvada,defektas,darba_atliko,irase_vartotojas,defekto_nuotrauka,defekto_nuotraukos_pavadinimas) VALUES (?,?,?,?,?,?,?,?,?)");
            } else {
                $ins_fb = $localConn->prepare("INSERT INTO funkciniai_bandymai (gaminio_id,eil_nr,reikalavimas,isvada,defektas,darba_atliko,irase_vartotojas) VALUES (?,?,?,?,?,?,?)");
            }
            $ins_mk = $localConn->prepare("INSERT INTO komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui) VALUES (?,?,?,?,?,?,?)");

            foreach ($mt_uzsakymai as $idx2 => $uzs) {
                $nr = trim($uzs['uzsakymo_numeris'] ?? '');
                if ($progressCallback && $idx2 % 5 === 0) {
                    $proc = 65 + (int)(($idx2 / max($viso_uzsakymu, 1)) * 35);
                    $progressCallback($proc, $viso_uzsakymu, 'Fazė 2: ' . ($idx2 + 1) . '/' . $viso_uzsakymu . ' (užs. ' . $nr . ')');
                }
                if ($nr === '' || !isset($existing_local[$nr])) {
                    if ($nr !== '') {
                        $rezultatas['faze2_praleisti']++;
                        $rezultatas['praleisti_gaminiai']++;
                    }
                    continue;
                }
                $local_uzs_id = $existing_local[$nr];

                $qt_id = (int)$uzs['qt_id'];
                $gaminiai = $qt_gam_by_uzs[$qt_id] ?? [];
                $rezultatas['qt_gaminiu'] += count($gaminiai);

                if (empty($gaminiai)) {
                    $rezultatas['faze2_be_gaminiu']++;
                } else {
                    $rezultatas['faze2_apdoroti']++;
                }

                foreach ($gaminiai as $gam) {
                    $local_gid = false;
                    if ($gam['gaminio_numeris'] !== null && $gam['gaminio_numeris'] !== '') {
                        $chk_gam_by_nr->execute([$local_uzs_id, $gam['gaminio_numeris']]);
                        $local_gid = $chk_gam_by_nr->fetchColumn();
                    }
                    if (!$local_gid) {
                        $chk_gam_null->execute([$local_uzs_id]);
                        $local_gid = $chk_gam_null->fetchColumn();
                    }

                    if ($local_gid) {
                        $sets = [];
                        $params = [':id' => $local_gid];
                        if ($gam['gaminio_numeris'] !== null) { $sets[] = "gaminio_numeris = :gn"; $params[':gn'] = $gam['gaminio_numeris']; }
                        if ($gam['gaminio_tipas_id'] !== null) { $sets[] = "gaminio_tipas_id = :gti"; $params[':gti'] = $gam['gaminio_tipas_id']; }
                        if ($gam['protokolo_nr'] !== null) { $sets[] = "protokolo_nr = :pnr"; $params[':pnr'] = $gam['protokolo_nr']; }
                        if (!empty($sets)) {
                            $localConn->prepare("UPDATE gaminiai SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
                        }
                    } else {
                        $ins_gam->execute([$local_uzs_id, $gam['gaminio_numeris'], $gam['gaminio_tipas_id'], $gam['protokolo_nr']]);
                        $local_gid = (int)$ins_gam->fetchColumn();
                    }
                    $rezultatas['gaminiai']++;

                    $qt_gam_id = (int)$gam['qt_gam_id'];
                    $fb_rows = $all_fb_by_gam[$qt_gam_id] ?? [];

                    if (!empty($fb_rows)) {
                        try {
                            $localConn->beginTransaction();
                            $del_fb->execute([$local_gid]);
                            foreach ($fb_rows as $r) {
                                if ($has_photo) {
                                    $foto = $r['defekto_nuotrauka'] ?? null;
                                    $ins_fb->bindValue(1, $local_gid);
                                    $ins_fb->bindValue(2, $r['eil_nr']);
                                    $ins_fb->bindValue(3, $r['reikalavimas']);
                                    $ins_fb->bindValue(4, $r['isvada']);
                                    $ins_fb->bindValue(5, $r['defektas']);
                                    $ins_fb->bindValue(6, $r['darba_atliko']);
                                    $ins_fb->bindValue(7, $r['irase_vartotojas']);
                                    $ins_fb->bindValue(8, $foto, $foto !== null ? PDO::PARAM_LOB : PDO::PARAM_NULL);
                                    $ins_fb->bindValue(9, $r['defekto_nuotraukos_pavadinimas'] ?? null);
                                    $ins_fb->execute();
                                } else {
                                    $ins_fb->execute([$local_gid, $r['eil_nr'], $r['reikalavimas'], $r['isvada'], $r['defektas'], $r['darba_atliko'], $r['irase_vartotojas']]);
                                }
                            }
                            $localConn->commit();
                            $rezultatas['bandymai'] += count($fb_rows);
                        } catch (Exception $e) {
                            if ($localConn->inTransaction()) $localConn->rollBack();
                            $rezultatas['klaidos'][] = "Bandymai uzs=$nr: {$e->getMessage()}";
                        }
                    }

                    $mk_rows = $all_mk_by_gam[$qt_gam_id] ?? [];

                    if (!empty($mk_rows)) {
                        try {
                            $localConn->beginTransaction();
                            $del_mk->execute([$local_gid]);
                            foreach ($mk_rows as $r) {
                                $ins_mk->execute([$local_gid, $r['eiles_numeris'], $r['gamintojo_kodas'], $r['kiekis'], $r['aprasymas'], $r['gamintojas'], $r['parinkta_projektui']]);
                            }
                            $localConn->commit();
                            $rezultatas['komponentai'] += count($mk_rows);
                        } catch (Exception $e) {
                            if ($localConn->inTransaction()) $localConn->rollBack();
                            $rezultatas['klaidos'][] = "Komponentai uzs=$nr: {$e->getMessage()}";
                        }
                    }
                }
            }

            if ($progressCallback) $progressCallback(92, $viso_uzsakymu, 'Fazė 3: importuojamos pretenzijos...');

            $qt = self::createFreshQTConnection();
            if (!$qt) {
                $rezultatas['klaidos'][] = 'Fazė 3: nepavyko atnaujinti ryšio su QT DB pretenzijoms';
                if ($progressCallback) $progressCallback(100, $viso_uzsakymu, 'Baigta (pretenzijos: ryšio klaida)');
                return $rezultatas;
            }

            $qt_uzs_id_to_local = [];
            foreach ($mt_uzsakymai as $uzs) {
                $nr = trim($uzs['uzsakymo_numeris'] ?? '');
                if ($nr !== '' && isset($existing_local[$nr])) {
                    $qt_uzs_id_to_local[(int)$uzs['qt_id']] = $existing_local[$nr];
                }
            }

            $qt_gam_to_local_gam = [];
            foreach ($mt_uzsakymai as $uzs) {
                $qt_id = (int)$uzs['qt_id'];
                $nr = trim($uzs['uzsakymo_numeris'] ?? '');
                if ($nr === '' || !isset($existing_local[$nr])) continue;
                $local_uzs_id_for_map = $existing_local[$nr];
                $gaminiai_for_map = $qt_gam_by_uzs[$qt_id] ?? [];
                foreach ($gaminiai_for_map as $gam) {
                    $local_gid_map = false;
                    if ($gam['gaminio_numeris'] !== null && $gam['gaminio_numeris'] !== '') {
                        $chk_gam_by_nr->execute([$local_uzs_id_for_map, $gam['gaminio_numeris']]);
                        $local_gid_map = $chk_gam_by_nr->fetchColumn();
                    }
                    if (!$local_gid_map) {
                        $chk_gam_null->execute([$local_uzs_id_for_map]);
                        $local_gid_map = $chk_gam_null->fetchColumn();
                    }
                    if ($local_gid_map) {
                        $qt_gam_to_local_gam[(int)$gam['qt_gam_id']] = (int)$local_gid_map;
                    }
                }
            }

            try {
                $qt_pret_table_check = $qt->query("SELECT to_regclass('pretenzijos')")->fetchColumn();
                error_log("Pretenzijos fazė: QT pretenzijos lentelė = " . ($qt_pret_table_check ?: 'NERASTA'));
                if ($qt_pret_table_check) {
                    $qt_pret_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='pretenzijos'")->fetchAll(PDO::FETCH_COLUMN);
                    $qt_has_gaminys = in_array('gaminys_id', $qt_pret_cols);
                    $qt_has_defekto_pdf = in_array('defekto_pdf_pavadinimas', $qt_pret_cols);

                    $pret_sel = "id, uzsakymo_id, tipas, statusas, aprasymas, priezastis, veiksmai, atsakingas_asmuo, gavimo_data, terminas, uzbaigimo_data, sukure_vardas, sukurta, atnaujinta, aptikimo_vieta, gaminys_info, atsakingas_padalinys, siulomas_sprendimas, uzfiksavo_padalinys, uzfiksavo_asmuo, uzsakymo_numeris_ranka";
                    if ($qt_has_gaminys) $pret_sel .= ", gaminys_id";
                    if ($qt_has_defekto_pdf) $pret_sel .= ", defekto_pdf_pavadinimas, defekto_pdf_turinys";

                    $qt_pretenzijos = $qt->query("SELECT $pret_sel FROM pretenzijos ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
                    error_log("Pretenzijos fazė: QT turi " . count($qt_pretenzijos) . " pretenzijų, stulpeliai: " . implode(',', $qt_pret_cols));

                    $local_has_qt_id = false;
                    try {
                        $local_has_qt_id = (bool)$localConn->query("SELECT column_name FROM information_schema.columns WHERE table_name='pretenzijos' AND column_name='qt_pretenzija_id'")->fetchColumn();
                    } catch (Exception $e) {}

                    $chk_pret_by_qt_id = null;
                    if ($local_has_qt_id) {
                        $chk_pret_by_qt_id = $localConn->prepare("SELECT id FROM pretenzijos WHERE qt_pretenzija_id = ? LIMIT 1");
                    }
                    $chk_pret_fallback = $localConn->prepare("SELECT id FROM pretenzijos WHERE aprasymas = ? AND sukure_vardas = ? AND gavimo_data = ? LIMIT 1");

                    $qt_nuotr_table = (bool)$qt->query("SELECT to_regclass('pretenzijos_nuotraukos')")->fetchColumn();
                    $qt_email_table = (bool)$qt->query("SELECT to_regclass('pretenzijos_email_history')")->fetchColumn();

                    foreach ($qt_pretenzijos as $p) {
                        try {
                        $qt_pret_id = (int)$p['id'];

                        $local_uzs_id = null;
                        if ($p['uzsakymo_id']) {
                            $local_uzs_id = $qt_uzs_id_to_local[(int)$p['uzsakymo_id']] ?? null;
                        }

                        $local_gam_id = null;
                        if ($qt_has_gaminys && !empty($p['gaminys_id'])) {
                            $local_gam_id = $qt_gam_to_local_gam[(int)$p['gaminys_id']] ?? null;
                        }

                        $existing_pret_id = false;
                        if ($local_has_qt_id && $chk_pret_by_qt_id) {
                            $chk_pret_by_qt_id->execute([$qt_pret_id]);
                            $existing_pret_id = $chk_pret_by_qt_id->fetchColumn();
                        }
                        if (!$existing_pret_id) {
                            $chk_pret_fallback->execute([$p['aprasymas'], $p['sukure_vardas'], $p['gavimo_data']]);
                            $existing_pret_id = $chk_pret_fallback->fetchColumn();
                        }

                        $pret_data = $p['gavimo_data'] ?? $p['sukurta'] ?? date('Y-m-d');
                        $pret_prioritetas = 'vidutinis';

                        $common_cols = "tipas=?, statusas=?, aprasymas=?, priezastis=?, veiksmai=?, atsakingas_asmuo=?, gavimo_data=?, terminas=?, uzbaigimo_data=?, sukure_vardas=?, aptikimo_vieta=?, gaminys_info=?, atsakingas_padalinys=?, siulomas_sprendimas=?, uzfiksavo_padalinys=?, uzfiksavo_asmuo=?, uzsakymo_numeris_ranka=?, uzsakymo_id=?, gaminio_id=?";
                        $common_params = [$p['tipas'], $p['statusas'], $p['aprasymas'], $p['priezastis'] ?? null, $p['veiksmai'] ?? null, $p['atsakingas_asmuo'] ?? null, $p['gavimo_data'], $p['terminas'] ?? null, $p['uzbaigimo_data'] ?? null, $p['sukure_vardas'], $p['aptikimo_vieta'] ?? null, $p['gaminys_info'] ?? null, $p['atsakingas_padalinys'] ?? null, $p['siulomas_sprendimas'] ?? null, $p['uzfiksavo_padalinys'] ?? null, $p['uzfiksavo_asmuo'] ?? null, $p['uzsakymo_numeris_ranka'] ?? null, $local_uzs_id, $local_gam_id];

                        if ($existing_pret_id) {
                            $upd_sql = "UPDATE pretenzijos SET $common_cols";
                            $upd_params = $common_params;
                            if ($qt_has_defekto_pdf) {
                                $upd_sql .= ", defekto_pdf_pavadinimas=?, defekto_pdf_turinys=?";
                                $upd_params[] = $p['defekto_pdf_pavadinimas'] ?? null;
                                $upd_params[] = $p['defekto_pdf_turinys'] ?? null;
                            }
                            if ($local_has_qt_id) {
                                $upd_sql .= ", qt_pretenzija_id=?";
                                $upd_params[] = $qt_pret_id;
                            }
                            $upd_sql .= " WHERE id=?";
                            $upd_params[] = $existing_pret_id;
                            $localConn->prepare($upd_sql)->execute($upd_params);
                            $local_pret_id = (int)$existing_pret_id;
                        } else {
                            $ins_cols = "tipas, statusas, aprasymas, priezastis, veiksmai, atsakingas_asmuo, gavimo_data, terminas, uzbaigimo_data, sukure_vardas, sukurta, atnaujinta, aptikimo_vieta, gaminys_info, atsakingas_padalinys, siulomas_sprendimas, uzfiksavo_padalinys, uzfiksavo_asmuo, uzsakymo_numeris_ranka, uzsakymo_id, gaminio_id, data, prioritetas, perziuros_token";
                            $ins_vals = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, md5(random()::text || clock_timestamp()::text)";
                            $ins_params = $common_params;
                            array_splice($ins_params, 10, 0, [$p['sukurta'] ?? date('Y-m-d H:i:s'), $p['atnaujinta'] ?? date('Y-m-d H:i:s')]);
                            $ins_params[] = $pret_data;
                            $ins_params[] = $pret_prioritetas;
                            if ($qt_has_defekto_pdf) {
                                $ins_cols .= ", defekto_pdf_pavadinimas, defekto_pdf_turinys";
                                $ins_vals .= ", ?, ?";
                                $ins_params[] = $p['defekto_pdf_pavadinimas'] ?? null;
                                $ins_params[] = $p['defekto_pdf_turinys'] ?? null;
                            }
                            if ($local_has_qt_id) {
                                $ins_cols .= ", qt_pretenzija_id";
                                $ins_vals .= ", ?";
                                $ins_params[] = $qt_pret_id;
                            }
                            $ins_pret_stmt = $localConn->prepare("INSERT INTO pretenzijos ($ins_cols) VALUES ($ins_vals) RETURNING id");
                            $ins_pret_stmt->execute($ins_params);
                            $local_pret_id = (int)$ins_pret_stmt->fetchColumn();
                        }
                        $rezultatas['pretenzijos']++;

                        if ($qt_nuotr_table) {
                            $localConn->prepare("DELETE FROM pretenzijos_nuotraukos WHERE pretenzija_id = ?")->execute([$local_pret_id]);
                            $nuotr_rows = $qt->prepare("SELECT pavadinimas, tipas, turinys FROM pretenzijos_nuotraukos WHERE pretenzija_id = ?");
                            $nuotr_rows->execute([$qt_pret_id]);
                            $nuotraukos = $nuotr_rows->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($nuotraukos)) {
                                $ins_nuotr = $localConn->prepare("INSERT INTO pretenzijos_nuotraukos (pretenzija_id, pavadinimas, tipas, turinys) VALUES (?, ?, ?, ?)");
                                foreach ($nuotraukos as $n) {
                                    $ins_nuotr->bindValue(1, $local_pret_id, PDO::PARAM_INT);
                                    $ins_nuotr->bindValue(2, $n['pavadinimas']);
                                    $ins_nuotr->bindValue(3, $n['tipas']);
                                    $ins_nuotr->bindValue(4, $n['turinys'], $n['turinys'] !== null ? PDO::PARAM_LOB : PDO::PARAM_NULL);
                                    $ins_nuotr->execute();
                                    $rezultatas['pretenzijos_nuotraukos']++;
                                }
                            }
                        }

                        if ($qt_email_table) {
                            $localConn->prepare("DELETE FROM pretenzijos_email_history WHERE pretenzija_id = ?")->execute([$local_pret_id]);
                            $email_rows = $qt->prepare("SELECT email_delegated_to, email_cc, email_subject, sent_by, sent_at, feedback_text, feedback_at, feedback_by FROM pretenzijos_email_history WHERE pretenzija_id = ?");
                            $email_rows->execute([$qt_pret_id]);
                            $emails = $email_rows->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($emails)) {
                                $ins_email = $localConn->prepare("INSERT INTO pretenzijos_email_history (pretenzija_id, email_delegated_to, email_cc, email_subject, sent_by, sent_at, feedback_text, feedback_at, feedback_by) VALUES (?,?,?,?,?,?,?,?,?)");
                                foreach ($emails as $em) {
                                    $ins_email->execute([$local_pret_id, $em['email_delegated_to'] ?? null, $em['email_cc'] ?? null, $em['email_subject'] ?? null, $em['sent_by'] ?? null, $em['sent_at'] ?? null, $em['feedback_text'] ?? null, $em['feedback_at'] ?? null, $em['feedback_by'] ?? null]);
                                    $rezultatas['pretenzijos_email']++;
                                }
                            }
                        }

                        } catch (Exception $row_e) {
                            $rezultatas['klaidos'][] = "Pretenzija qt_id={$p['id']}: {$row_e->getMessage()}";
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Pretenzijos fazė KLAIDA: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
                $rezultatas['klaidos'][] = "Pretenzijos: {$e->getMessage()}";
            }

            if ($progressCallback) $progressCallback(100, $viso_uzsakymu, 'Baigta!');

            $log_detail = sprintf(
                'Užs: +%d nauji, %d atn. | Fazė2: %d apdoroti, %d be gaminių, %d praleisti | QT gaminiai: %d, local: %d | Bandymai: %d | Komponentai: %d | Pretenzijos: %d (nuotr: %d, email: %d)',
                $rezultatas['nauji'], $rezultatas['atnaujinti'],
                $rezultatas['faze2_apdoroti'], $rezultatas['faze2_be_gaminiu'], $rezultatas['faze2_praleisti'],
                $rezultatas['qt_gaminiu'], $rezultatas['gaminiai'],
                $rezultatas['bandymai'], $rezultatas['komponentai'],
                $rezultatas['pretenzijos'], $rezultatas['pretenzijos_nuotraukos'], $rezultatas['pretenzijos_email']
            );
            error_log('importuotiILocalDB rezultatas: ' . $log_detail);

            self::irasytLog(
                'Importas iš quality_tomas į local DB',
                'uzsakymai+gaminiai+bandymai',
                null,
                $rezultatas['nauji'] + $rezultatas['atnaujinti'] + $rezultatas['gaminiai'] + $rezultatas['bandymai'] + $rezultatas['komponentai'],
                empty($rezultatas['klaidos']) ? 'ok' : 'klaida',
                empty($rezultatas['klaidos']) ? $log_detail : implode('; ', array_slice($rezultatas['klaidos'], 0, 5))
            );

        } catch (Exception $e) {
            $rezultatas['klaidos'][] = $e->getMessage();
            self::irasytLog('Importo klaida (local)', 'uzsakymai', null, 0, 'klaida', $e->getMessage());
        }

        return $rezultatas;
    }

    public static function importuotiIsQualityTomas(): array {
        $qt = self::getQualityTomasConnection();
        $tomo = self::getConnection();
        if (!$qt || !$tomo) return ['klaida' => 'Nepavyko prisijungti prie duomenų bazių'];

        $rezultatas = ['vartotojai' => 0, 'nauji' => 0, 'atnaujinti' => 0, 'gaminiai' => 0, 'bandymai' => 0, 'klaidos' => []];

        try {
            // === 1. VARTOTOJAI ===
            $qt_users = $qt->query("SELECT id, vardas, pavarde, el_pastas, slaptazodis, role FROM vartotojai ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($qt_users as $u) {
                $check = $tomo->prepare("SELECT id FROM vartotojai WHERE id = ?");
                $check->execute([$u['id']]);
                if ($check->fetchColumn()) {
                    $tomo->prepare("UPDATE vartotojai SET vardas=?, pavarde=?, role=? WHERE id=?")
                        ->execute([$u['vardas'], $u['pavarde'], $u['role'], $u['id']]);
                } else {
                    $tomo->prepare("INSERT INTO vartotojai (id, vardas, pavarde, el_pastas, slaptazodis, role) VALUES (?,?,?,?,?,?)")
                        ->execute([$u['id'], $u['vardas'], $u['pavarde'], $u['el_pastas'] ?? '', $u['slaptazodis'] ?? '', $u['role'] ?? 'vartotojas']);
                }
                $rezultatas['vartotojai']++;
            }
            $max_id = $tomo->query("SELECT MAX(id) FROM vartotojai")->fetchColumn();
            if ($max_id) $tomo->exec("SELECT setval(pg_get_serial_sequence('vartotojai', 'id'), $max_id, true)");

            // === 2. GAMINIO TIPAI ===
            $types = $qt->query("SELECT id, gaminio_tipas, grupe, atitikmuo_kodas FROM gaminio_tipai WHERE grupe = 'MT'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($types as $t) {
                $exists = $tomo->prepare("SELECT id FROM gaminio_tipai WHERE id = ?");
                $exists->execute([$t['id']]);
                if (!$exists->fetchColumn()) {
                    $tomo->prepare("INSERT INTO gaminio_tipai (id, gaminio_tipas, grupe, atitikmuo_kodas) VALUES (?,?,?,?)")
                        ->execute([$t['id'], $t['gaminio_tipas'], $t['grupe'], $t['atitikmuo_kodas']]);
                }
            }

            // === 3. UŽSAKOVAI IR OBJEKTAI (batch) ===
            $stmt = $qt->query("
                SELECT u.id as qt_id, u.uzsakymo_numeris, u.sukurtas, u.kiekis, u.gaminiu_rusis_id, u.vartotojas_id,
                       uz.uzsakovas, o.pavadinimas as objektas
                FROM uzsakymai u
                LEFT JOIN uzsakovai uz ON uz.id = u.uzsakovas_id
                LEFT JOIN objektai o ON o.id = u.objektas_id
                WHERE u.gaminiu_rusis_id = 2
                ORDER BY u.id
            ");
            $mt_uzsakymai = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $uzsakovai_cache = [];
            $objektai_cache = [];
            foreach ($mt_uzsakymai as $uzs) {
                if ($uzs['uzsakovas'] && !isset($uzsakovai_cache[$uzs['uzsakovas']])) {
                    $uzsakovai_cache[$uzs['uzsakovas']] = self::gautiArbaKurtiUzsakova($uzs['uzsakovas']);
                }
                if ($uzs['objektas'] && !isset($objektai_cache[$uzs['objektas']])) {
                    $objektai_cache[$uzs['objektas']] = self::gautiArbaKurtiObjekta($uzs['objektas']);
                }
            }

            // === 4. UŽSAKYMAI ===
            $existing = [];
            $st = $tomo->query("SELECT id, uzsakymo_numeris FROM uzsakymai");
            foreach ($st as $r) $existing[trim($r['uzsakymo_numeris'] ?? '')] = (int)$r['id'];

            $order_map = [];
            foreach ($mt_uzsakymai as $uzs) {
                $nr = trim($uzs['uzsakymo_numeris'] ?? '');
                if ($nr === '') continue;
                $uzs_id_val = $uzs['uzsakovas'] ? ($uzsakovai_cache[$uzs['uzsakovas']] ?? null) : null;
                $obj_id_val = $uzs['objektas'] ? ($objektai_cache[$uzs['objektas']] ?? null) : null;

                if (isset($existing[$nr])) {
                    $tomo->prepare("UPDATE uzsakymai SET kiekis=?,uzsakovas_id=?,objektas_id=?,gaminiu_rusis_id=?,vartotojas_id=?,sukurtas=? WHERE id=?")
                        ->execute([$uzs['kiekis'], $uzs_id_val, $obj_id_val, $uzs['gaminiu_rusis_id'], $uzs['vartotojas_id'] ?? 1, $uzs['sukurtas'], $existing[$nr]]);
                    $order_map[$uzs['qt_id']] = $existing[$nr];
                    $rezultatas['atnaujinti']++;
                } else {
                    $ins = $tomo->prepare("INSERT INTO uzsakymai (uzsakymo_numeris,kiekis,uzsakovas_id,objektas_id,vartotojas_id,gaminiu_rusis_id,sukurtas) VALUES (?,?,?,?,?,?,?) RETURNING id");
                    $ins->execute([$nr, $uzs['kiekis'], $uzs_id_val, $obj_id_val, $uzs['vartotojas_id'] ?? 1, $uzs['gaminiu_rusis_id'], $uzs['sukurtas']]);
                    $tid = (int)$ins->fetchColumn();
                    $order_map[$uzs['qt_id']] = $tid;
                    $rezultatas['nauji']++;
                }
            }

            // === 5. GAMINIAI ===
            $qt_to_tomo_gam = [];
            foreach ($mt_uzsakymai as $uzs) {
                $tomo_uzs_id = $order_map[$uzs['qt_id']] ?? null;
                if (!$tomo_uzs_id) continue;
                $gam_stmt = $qt->prepare("SELECT id as qt_gam_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr FROM gaminiai WHERE uzsakymo_id = ?");
                $gam_stmt->execute([$uzs['qt_id']]);
                foreach ($gam_stmt as $gam) {
                    $chk = $tomo->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id=? AND gaminio_numeris=? LIMIT 1");
                    $chk->execute([$tomo_uzs_id, $gam['gaminio_numeris']]);
                    $gid = $chk->fetchColumn();
                    if (!$gid) {
                        $chk2 = $tomo->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id=? AND gaminio_numeris IS NULL LIMIT 1");
                        $chk2->execute([$tomo_uzs_id]);
                        $gid = $chk2->fetchColumn();
                    }
                    if ($gid) {
                        $tomo->prepare("UPDATE gaminiai SET gaminio_numeris=?,gaminio_tipas_id=?,protokolo_nr=? WHERE id=?")
                            ->execute([$gam['gaminio_numeris'], $gam['gaminio_tipas_id'], $gam['protokolo_nr'], $gid]);
                        $qt_to_tomo_gam[(int)$gam['qt_gam_id']] = (int)$gid;
                    } else {
                        $ins2 = $tomo->prepare("INSERT INTO gaminiai (uzsakymo_id,gaminio_numeris,gaminio_tipas_id,protokolo_nr) VALUES (?,?,?,?) RETURNING id");
                        $ins2->execute([$tomo_uzs_id, $gam['gaminio_numeris'], $gam['gaminio_tipas_id'], $gam['protokolo_nr']]);
                        $qt_to_tomo_gam[(int)$gam['qt_gam_id']] = (int)$ins2->fetchColumn();
                    }
                    $rezultatas['gaminiai']++;
                }
            }

            // Visi TOMO gaminio ID sąrašas (naudojamas masiniams DELETE §6–§11)
            $all_tomo_gam_ids = array_values($qt_to_tomo_gam);
            $ph_all = implode(',', array_fill(0, count($all_tomo_gam_ids), '?'));

            // === 6. FUNKCINIAI BANDYMAI (bulk DELETE + INSERT) ===
            $rezultatas['bandymai'] = 0;
            try {
                $fb_data = $qt->query("
                    SELECT fb.gaminio_id AS qt_gam_id, fb.eil_nr, fb.reikalavimas, fb.isvada,
                           fb.defektas, fb.darba_atliko, fb.irase_vartotojas
                    FROM mt_funkciniai_bandymai fb
                    JOIN gaminiai g ON g.id = fb.gaminio_id
                    JOIN uzsakymai u ON u.id = g.uzsakymo_id
                    WHERE u.gaminiu_rusis_id = 2
                    ORDER BY fb.gaminio_id, fb.eil_nr
                ")->fetchAll(PDO::FETCH_ASSOC);

                $tomo->prepare("DELETE FROM funkciniai_bandymai WHERE gaminio_id IN ($ph_all)")->execute($all_tomo_gam_ids);
                $tomo->exec("SELECT setval('mt_funkciniai_bandymai_id_seq', COALESCE((SELECT MAX(id) FROM funkciniai_bandymai), 0) + 50000, false)");

                $ins = $tomo->prepare("INSERT INTO funkciniai_bandymai (gaminio_id,eil_nr,reikalavimas,isvada,defektas,darba_atliko,irase_vartotojas) VALUES (?,?,?,?,?,?,?)");
                foreach ($fb_data as $r) {
                    $tid = $qt_to_tomo_gam[(int)$r['qt_gam_id']] ?? null;
                    if (!$tid) continue;
                    $ins->execute([$tid, $r['eil_nr'], $r['reikalavimas'], $r['isvada'], $r['defektas'], $r['darba_atliko'], $r['irase_vartotojas']]);
                    $rezultatas['bandymai']++;
                }
            } catch (Exception $e) {
                $rezultatas['klaidos'][] = "Funkciniai bandymai: {$e->getMessage()}";
            }

            // === 7. MT KOMPONENTAI (bulk DELETE + INSERT) ===
            $rezultatas['komponentai'] = 0;
            try {
                $komp_data = $qt->query("
                    SELECT mk.gaminio_id AS qt_gam_id, mk.eiles_numeris, mk.gamintojo_kodas,
                           mk.kiekis, mk.aprasymas, mk.gamintojas, mk.parinkta_projektui
                    FROM mt_komponentai mk
                    JOIN gaminiai g ON g.id = mk.gaminio_id
                    JOIN uzsakymai u ON u.id = g.uzsakymo_id
                    WHERE u.gaminiu_rusis_id = 2
                    ORDER BY mk.gaminio_id, mk.eiles_numeris
                ")->fetchAll(PDO::FETCH_ASSOC);

                $tomo->prepare("DELETE FROM komponentai WHERE gaminio_id IN ($ph_all)")->execute($all_tomo_gam_ids);
                $tomo->exec("SELECT setval('mt_komponentai_id_seq', COALESCE((SELECT MAX(id) FROM komponentai), 0) + 50000, false)");

                $ins = $tomo->prepare("INSERT INTO komponentai (gaminio_id,eiles_numeris,gamintojo_kodas,kiekis,aprasymas,gamintojas,parinkta_projektui) VALUES (?,?,?,?,?,?,?)");
                foreach ($komp_data as $r) {
                    $tid = $qt_to_tomo_gam[(int)$r['qt_gam_id']] ?? null;
                    if (!$tid) continue;
                    $ins->execute([$tid, $r['eiles_numeris'], $r['gamintojo_kodas'], $r['kiekis'], $r['aprasymas'], $r['gamintojas'], $r['parinkta_projektui']]);
                    $rezultatas['komponentai']++;
                }
            } catch (Exception $e) {
                $rezultatas['klaidos'][] = "Komponentai: {$e->getMessage()}";
            }

            // === 8. DIELEKTRINIAI BANDYMAI ===
            $rezultatas['dielektriniai'] = 0;
            try {
                $diel_data = $qt->query("
                    SELECT d.gaminys_id as qt_gam_id, d.eiles_nr, d.aprasymas, d.itampa,
                           d.schema1, d.schema2, d.schema3, d.schema4, d.schema5, d.schema6, d.isvada
                    FROM mt_dielektriniai_bandymai d
                    JOIN gaminiai g ON g.id = d.gaminys_id
                    JOIN uzsakymai u ON u.id = g.uzsakymo_id
                    WHERE u.gaminiu_rusis_id = 2
                    ORDER BY d.gaminys_id, d.eiles_nr
                ")->fetchAll(PDO::FETCH_ASSOC);

                $tomo->prepare("DELETE FROM dielektriniai_bandymai WHERE gaminys_id IN ($ph_all)")->execute($all_tomo_gam_ids);
                $tomo->exec("SELECT setval('mt_dielektriniai_bandymai_id_seq', COALESCE((SELECT MAX(id) FROM dielektriniai_bandymai), 0) + 50000, false)");

                $ins = $tomo->prepare("INSERT INTO dielektriniai_bandymai (gaminys_id,eiles_nr,aprasymas,itampa,schema1,schema2,schema3,schema4,schema5,schema6,isvada) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                foreach ($diel_data as $d) {
                    $tomo_gam_id = $qt_to_tomo_gam[(int)$d['qt_gam_id']] ?? null;
                    if (!$tomo_gam_id) continue;
                    $ins->execute([$tomo_gam_id, $d['eiles_nr'], $d['aprasymas'], $d['itampa'], $d['schema1'], $d['schema2'], $d['schema3'], $d['schema4'], $d['schema5'], $d['schema6'], $d['isvada']]);
                    $rezultatas['dielektriniai']++;
                }
            } catch (Exception $e) {
                $rezultatas['klaidos'][] = "Dielektriniai: {$e->getMessage()}";
            }

            // === 9. ĮŽEMINIMO TIKRINIMAS ===
            $rezultatas['izeminimas'] = 0;
            try {
                $izem_data = $qt->query("
                    SELECT i.gaminys_id as qt_gam_id, i.eil_nr, i.tasko_pavadinimas,
                           i.matavimo_tasku_skaicius, i.varza_ohm, i.budas, i.bukle
                    FROM mt_izeminimo_tikrinimas i
                    JOIN gaminiai g ON g.id = i.gaminys_id
                    JOIN uzsakymai u ON u.id = g.uzsakymo_id
                    WHERE u.gaminiu_rusis_id = 2
                    ORDER BY i.gaminys_id, i.eil_nr
                ")->fetchAll(PDO::FETCH_ASSOC);

                $tomo->prepare("DELETE FROM izeminimo_tikrinimas WHERE gaminys_id IN ($ph_all)")->execute($all_tomo_gam_ids);
                $tomo->exec("SELECT setval('mt_izeminimo_tikrinimas_id_seq', COALESCE((SELECT MAX(id) FROM izeminimo_tikrinimas), 0) + 50000, false)");

                $ins = $tomo->prepare("INSERT INTO izeminimo_tikrinimas (gaminys_id,eil_nr,tasko_pavadinimas,matavimo_tasku_skaicius,varza_ohm,budas,bukle) VALUES (?,?,?,?,?,?,?)");
                foreach ($izem_data as $i) {
                    $tomo_gam_id = $qt_to_tomo_gam[(int)$i['qt_gam_id']] ?? null;
                    if (!$tomo_gam_id) continue;
                    $ins->execute([$tomo_gam_id, $i['eil_nr'], $i['tasko_pavadinimas'], $i['matavimo_tasku_skaicius'], $i['varza_ohm'], $i['budas'], $i['bukle']]);
                    $rezultatas['izeminimas']++;
                }
            } catch (Exception $e) {
                $rezultatas['klaidos'][] = "Įžeminimas: {$e->getMessage()}";
            }

            // === 10. SAUGIKLIŲ ĮDĖKLAI ===
            $rezultatas['saugikliai'] = 0;
            try {
                $saug_data = $qt->query("
                    SELECT s.gaminio_id as qt_gam_id, s.sekcija, s.pozicija,
                           s.gabaritas, s.nominalas, s.pozicijos_numeris
                    FROM mt_saugikliu_ideklai s
                    JOIN gaminiai g ON g.id = s.gaminio_id
                    JOIN uzsakymai u ON u.id = g.uzsakymo_id
                    WHERE u.gaminiu_rusis_id = 2
                    ORDER BY s.gaminio_id, s.pozicijos_numeris
                ")->fetchAll(PDO::FETCH_ASSOC);

                $tomo->prepare("DELETE FROM saugikliu_ideklai WHERE gaminio_id IN ($ph_all)")->execute($all_tomo_gam_ids);
                $tomo->exec("SELECT setval('mt_saugikliu_ideklai_id_seq', COALESCE((SELECT MAX(id) FROM saugikliu_ideklai), 0) + 50000, false)");

                $ins = $tomo->prepare("INSERT INTO saugikliu_ideklai (gaminio_id,sekcija,pozicija,gabaritas,nominalas,pozicijos_numeris) VALUES (?,?,?,?,?,?)");
                foreach ($saug_data as $s) {
                    $tomo_gam_id = $qt_to_tomo_gam[(int)$s['qt_gam_id']] ?? null;
                    if (!$tomo_gam_id) continue;
                    $ins->execute([$tomo_gam_id, $s['sekcija'], $s['pozicija'], $s['gabaritas'], $s['nominalas'], $s['pozicijos_numeris']]);
                    $rezultatas['saugikliai']++;
                }
            } catch (Exception $e) {
                $rezultatas['klaidos'][] = "Saugikliai: {$e->getMessage()}";
            }

            // === 11. PASO TEKSTO KOREKCIJOS (MT pasas) ===
            $rezultatas['paso_korekcijos'] = 0;
            try {
                $paso_data = $qt->query("
                    SELECT p.gaminio_id as qt_gam_id, p.field_key, p.lang, p.tekstas, p.updated_at
                    FROM mt_paso_teksto_korekcijos p
                    JOIN gaminiai g ON g.id = p.gaminio_id
                    JOIN uzsakymai u ON u.id = g.uzsakymo_id
                    WHERE u.gaminiu_rusis_id = 2
                    ORDER BY p.gaminio_id, p.field_key
                ")->fetchAll(PDO::FETCH_ASSOC);

                $tomo->prepare("DELETE FROM paso_teksto_korekcijos WHERE gaminio_id IN ($ph_all)")->execute($all_tomo_gam_ids);
                $tomo->exec("SELECT setval('mt_paso_teksto_korekcijos_id_seq', COALESCE((SELECT MAX(id) FROM paso_teksto_korekcijos), 0) + 50000, false)");

                $ins = $tomo->prepare("INSERT INTO paso_teksto_korekcijos (gaminio_id,field_key,lang,tekstas,updated_at) VALUES (?,?,?,?,?)");
                foreach ($paso_data as $p) {
                    $tomo_gam_id = $qt_to_tomo_gam[(int)$p['qt_gam_id']] ?? null;
                    if (!$tomo_gam_id) continue;
                    $ins->execute([$tomo_gam_id, $p['field_key'], $p['lang'], $p['tekstas'], $p['updated_at']]);
                    $rezultatas['paso_korekcijos']++;
                }
            } catch (Exception $e) {
                $rezultatas['klaidos'][] = "Paso korekcijos: {$e->getMessage()}";
            }

            self::irasytLog(
                'Importas iš quality_tomas',
                'uzsakymai+bandymai+komponentai+dielektriniai+izeminimas+saugikliai+pasas',
                null,
                $rezultatas['nauji'] + $rezultatas['atnaujinti'] + $rezultatas['bandymai'] + $rezultatas['komponentai'] + $rezultatas['dielektriniai'] + $rezultatas['izeminimas'] + $rezultatas['saugikliai'] + $rezultatas['paso_korekcijos'],
                empty($rezultatas['klaidos']) ? 'ok' : 'klaida',
                empty($rezultatas['klaidos']) ? null : implode('; ', array_slice($rezultatas['klaidos'], 0, 5))
            );

        } catch (Exception $e) {
            $rezultatas['klaidos'][] = $e->getMessage();
            self::irasytLog('Importo klaida', 'uzsakymai', null, 0, 'klaida', $e->getMessage());
        }

        return $rezultatas;
    }

    /**
     * Importuoja pretenzijas iš quality_tomas į Tomo QMS duomenų bazę.
     * Kopijuojamos pretenzijos, jų nuotraukos ir el. pašto istorija.
     * Dublikatai atnaujinami pagal qt_pretenzija_id arba aprašymą+datą.
     */
    public static function importuotiPretenzijasSiQualityTomas(): array {
        $qt   = self::getQualityTomasConnection();
        $tomo = self::getConnection();
        if (!$qt)   return ['klaida' => 'Nepavyko prisijungti prie quality_tomas DB'];
        if (!$tomo) return ['klaida' => 'Nepavyko prisijungti prie Tomo QMS DB'];

        $rez = ['pretenzijos' => 0, 'nuotraukos' => 0, 'email' => 0, 'klaidos' => []];

        try {
            $qt_pret_exists = $qt->query("SELECT to_regclass('pretenzijos')")->fetchColumn();
            if (!$qt_pret_exists) return ['klaida' => 'quality_tomas neturi pretenzijos lentelės'];

            $qt_pret_cols    = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='pretenzijos'")->fetchAll(PDO::FETCH_COLUMN);
            $qt_has_gaminys  = in_array('gaminys_id', $qt_pret_cols);
            $qt_has_pdf      = in_array('defekto_pdf_pavadinimas', $qt_pret_cols);

            $pret_sel = "id, uzsakymo_id, tipas, statusas, aprasymas, priezastis, veiksmai, atsakingas_asmuo, gavimo_data, terminas, uzbaigimo_data, sukure_vardas, sukurta, atnaujinta, aptikimo_vieta, gaminys_info, atsakingas_padalinys, siulomas_sprendimas, uzfiksavo_padalinys, uzfiksavo_asmuo, uzsakymo_numeris_ranka";
            if ($qt_has_gaminys) $pret_sel .= ", gaminys_id";
            if ($qt_has_pdf)     $pret_sel .= ", defekto_pdf_pavadinimas, defekto_pdf_turinys";

            $qt_pretenzijos = $qt->query("SELECT $pret_sel FROM pretenzijos ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($qt_pretenzijos)) return ['klaida' => 'quality_tomas neturi pretenzijų'];

            // Užsakymų žemėlapis: qt uzsakymo_id → uzsakymo_numeris → tomo uzsakymo_id
            $qt_uzs_nr = [];
            foreach ($qt->query("SELECT id, uzsakymo_numeris FROM uzsakymai")->fetchAll(PDO::FETCH_ASSOC) as $o)
                $qt_uzs_nr[(int)$o['id']] = trim($o['uzsakymo_numeris'] ?? '');

            $tomo_uzs_map = [];
            foreach ($tomo->query("SELECT id, uzsakymo_numeris FROM uzsakymai")->fetchAll(PDO::FETCH_ASSOC) as $o)
                $tomo_uzs_map[trim($o['uzsakymo_numeris'] ?? '')] = (int)$o['id'];

            // Gaminių žemėlapis: qt gaminys_id → tomo gaminys_id
            $qt_gam_map = [];
            if ($qt_has_gaminys) {
                $qt_gam_rows = $qt->query("
                    SELECT g.id, g.gaminio_numeris, u.uzsakymo_numeris
                    FROM gaminiai g JOIN uzsakymai u ON u.id = g.uzsakymo_id
                    WHERE u.gaminiu_rusis_id = 2
                ")->fetchAll(PDO::FETCH_ASSOC);
                $chk_gam = $tomo->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id = ? AND gaminio_numeris = ? LIMIT 1");
                foreach ($qt_gam_rows as $g) {
                    $tomo_uzs_id = $tomo_uzs_map[trim($g['uzsakymo_numeris'] ?? '')] ?? null;
                    if (!$tomo_uzs_id) continue;
                    $chk_gam->execute([$tomo_uzs_id, $g['gaminio_numeris']]);
                    $tomo_gid = $chk_gam->fetchColumn();
                    if ($tomo_gid) $qt_gam_map[(int)$g['id']] = (int)$tomo_gid;
                }
            }

            $tomo_has_qt_id  = (bool)$tomo->query("SELECT column_name FROM information_schema.columns WHERE table_name='pretenzijos' AND column_name='qt_pretenzija_id'")->fetchColumn();
            $qt_nuotr_table  = (bool)$qt->query("SELECT to_regclass('pretenzijos_nuotraukos')")->fetchColumn();
            $qt_email_table  = (bool)$qt->query("SELECT to_regclass('pretenzijos_email_history')")->fetchColumn();

            foreach ($qt_pretenzijos as $p) {
                try {
                    $qt_pret_id = (int)$p['id'];

                    $tomo_uzs_id = null;
                    if ($p['uzsakymo_id']) {
                        $nr = $qt_uzs_nr[(int)$p['uzsakymo_id']] ?? null;
                        if ($nr) $tomo_uzs_id = $tomo_uzs_map[$nr] ?? null;
                    }
                    $tomo_gam_id = ($qt_has_gaminys && !empty($p['gaminys_id']))
                        ? ($qt_gam_map[(int)$p['gaminys_id']] ?? null) : null;

                    // Tikrina dublikatus
                    $existing_id = false;
                    if ($tomo_has_qt_id) {
                        $chk = $tomo->prepare("SELECT id FROM pretenzijos WHERE qt_pretenzija_id = ? LIMIT 1");
                        $chk->execute([$qt_pret_id]);
                        $existing_id = $chk->fetchColumn();
                    }
                    if (!$existing_id) {
                        $chk2 = $tomo->prepare("SELECT id FROM pretenzijos WHERE aprasymas = ? AND sukure_vardas = ? AND gavimo_data = ? LIMIT 1");
                        $chk2->execute([$p['aprasymas'], $p['sukure_vardas'], $p['gavimo_data']]);
                        $existing_id = $chk2->fetchColumn();
                    }

                    $common_cols   = "tipas=?, statusas=?, aprasymas=?, priezastis=?, veiksmai=?, atsakingas_asmuo=?, gavimo_data=?, terminas=?, uzbaigimo_data=?, sukure_vardas=?, aptikimo_vieta=?, gaminys_info=?, atsakingas_padalinys=?, siulomas_sprendimas=?, uzfiksavo_padalinys=?, uzfiksavo_asmuo=?, uzsakymo_numeris_ranka=?, uzsakymo_id=?, gaminio_id=?";
                    $common_params = [$p['tipas'], $p['statusas'], $p['aprasymas'], $p['priezastis'] ?? null, $p['veiksmai'] ?? null, $p['atsakingas_asmuo'] ?? null, $p['gavimo_data'], $p['terminas'] ?? null, $p['uzbaigimo_data'] ?? null, $p['sukure_vardas'], $p['aptikimo_vieta'] ?? null, $p['gaminys_info'] ?? null, $p['atsakingas_padalinys'] ?? null, $p['siulomas_sprendimas'] ?? null, $p['uzfiksavo_padalinys'] ?? null, $p['uzfiksavo_asmuo'] ?? null, $p['uzsakymo_numeris_ranka'] ?? null, $tomo_uzs_id, $tomo_gam_id];

                    if ($existing_id) {
                        $upd = "UPDATE pretenzijos SET $common_cols";
                        $upd_p = $common_params;
                        if ($qt_has_pdf) { $upd .= ", defekto_pdf_pavadinimas=?, defekto_pdf_turinys=?"; $upd_p[] = $p['defekto_pdf_pavadinimas'] ?? null; $upd_p[] = $p['defekto_pdf_turinys'] ?? null; }
                        if ($tomo_has_qt_id) { $upd .= ", qt_pretenzija_id=?"; $upd_p[] = $qt_pret_id; }
                        $upd .= " WHERE id=?"; $upd_p[] = $existing_id;
                        $tomo->prepare($upd)->execute($upd_p);
                        $local_pret_id = (int)$existing_id;
                    } else {
                        $ic = "tipas, statusas, aprasymas, priezastis, veiksmai, atsakingas_asmuo, gavimo_data, terminas, uzbaigimo_data, sukure_vardas, sukurta, atnaujinta, aptikimo_vieta, gaminys_info, atsakingas_padalinys, siulomas_sprendimas, uzfiksavo_padalinys, uzfiksavo_asmuo, uzsakymo_numeris_ranka, uzsakymo_id, gaminio_id, data, prioritetas, perziuros_token";
                        $iv = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, md5(random()::text || clock_timestamp()::text)";
                        $ip = $common_params;
                        array_splice($ip, 10, 0, [$p['sukurta'] ?? date('Y-m-d H:i:s'), $p['atnaujinta'] ?? date('Y-m-d H:i:s')]);
                        $ip[] = $p['gavimo_data'] ?? $p['sukurta'] ?? date('Y-m-d');
                        $ip[] = 'vidutinis';
                        if ($qt_has_pdf) { $ic .= ", defekto_pdf_pavadinimas, defekto_pdf_turinys"; $iv .= ", ?, ?"; $ip[] = $p['defekto_pdf_pavadinimas'] ?? null; $ip[] = $p['defekto_pdf_turinys'] ?? null; }
                        if ($tomo_has_qt_id) { $ic .= ", qt_pretenzija_id"; $iv .= ", ?"; $ip[] = $qt_pret_id; }
                        $ins = $tomo->prepare("INSERT INTO pretenzijos ($ic) VALUES ($iv) RETURNING id");
                        $ins->execute($ip);
                        $local_pret_id = (int)$ins->fetchColumn();
                    }
                    $rez['pretenzijos']++;

                    if ($qt_nuotr_table) {
                        $tomo->prepare("DELETE FROM pretenzijos_nuotraukos WHERE pretenzija_id = ?")->execute([$local_pret_id]);
                        $n_stmt = $qt->prepare("SELECT pavadinimas, tipas, turinys FROM pretenzijos_nuotraukos WHERE pretenzija_id = ?");
                        $n_stmt->execute([$qt_pret_id]);
                        $ins_n = $tomo->prepare("INSERT INTO pretenzijos_nuotraukos (pretenzija_id, pavadinimas, tipas, turinys) VALUES (?, ?, ?, ?)");
                        foreach ($n_stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
                            $turinys = $n['turinys'];
                            if (is_resource($turinys)) $turinys = stream_get_contents($turinys);
                            $ins_n->bindValue(1, $local_pret_id, PDO::PARAM_INT);
                            $ins_n->bindValue(2, $n['pavadinimas']);
                            $ins_n->bindValue(3, $n['tipas']);
                            $ins_n->bindValue(4, $turinys, $turinys !== null ? PDO::PARAM_LOB : PDO::PARAM_NULL);
                            $ins_n->execute();
                            $rez['nuotraukos']++;
                        }
                    }

                    if ($qt_email_table) {
                        $tomo->prepare("DELETE FROM pretenzijos_email_history WHERE pretenzija_id = ?")->execute([$local_pret_id]);
                        $e_stmt = $qt->prepare("SELECT email_delegated_to, email_cc, email_subject, sent_by, sent_at, feedback_text, feedback_at, feedback_by FROM pretenzijos_email_history WHERE pretenzija_id = ?");
                        $e_stmt->execute([$qt_pret_id]);
                        $ins_e = $tomo->prepare("INSERT INTO pretenzijos_email_history (pretenzija_id, email_delegated_to, email_cc, email_subject, sent_by, sent_at, feedback_text, feedback_at, feedback_by) VALUES (?,?,?,?,?,?,?,?,?)");
                        foreach ($e_stmt->fetchAll(PDO::FETCH_ASSOC) as $em) {
                            $ins_e->execute([$local_pret_id, $em['email_delegated_to'] ?? null, $em['email_cc'] ?? null, $em['email_subject'] ?? null, $em['sent_by'] ?? null, $em['sent_at'] ?? null, $em['feedback_text'] ?? null, $em['feedback_at'] ?? null, $em['feedback_by'] ?? null]);
                            $rez['email']++;
                        }
                    }

                } catch (Exception $row_e) {
                    $rez['klaidos'][] = "Pretenzija qt_id=$qt_pret_id: {$row_e->getMessage()}";
                }
            }

            self::irasytLog('Pretenzijos iš quality_tomas į Tomo QMS', 'pretenzijos', null, $rez['pretenzijos'], empty($rez['klaidos']) ? 'ok' : 'klaida', empty($rez['klaidos']) ? null : implode('; ', array_slice($rez['klaidos'], 0, 5)));

        } catch (Exception $e) {
            $rez['klaidos'][] = $e->getMessage();
        }

        return $rez;
    }

    /**
     * Grąžina kiek vietinių gaminių turi kiekvieną PDF tipą.
     * Naudojama persiusti_pdf.php peržiūroje prieš perkėlimą.
     */
    public static function gautiLokalusPDFKieki(PDO $localConn): array {
        $resultado = [
            'paso'        => 0,
            'dielektriniu' => 0,
            'funkciniu'   => 0,
            'viso_gaminiu' => 0,
        ];
        try {
            $resultado['paso']        = (int)$localConn->query("SELECT COUNT(*) FROM gaminiai WHERE mt_paso_pdf IS NOT NULL")->fetchColumn();
            $resultado['dielektriniu'] = (int)$localConn->query("SELECT COUNT(*) FROM gaminiai WHERE mt_dielektriniu_pdf IS NOT NULL")->fetchColumn();
            $resultado['funkciniu']   = (int)$localConn->query("SELECT COUNT(*) FROM gaminiai WHERE mt_funkciniu_pdf IS NOT NULL")->fetchColumn();
            $resultado['viso_gaminiu'] = (int)$localConn->query("SELECT COUNT(*) FROM gaminiai WHERE mt_paso_pdf IS NOT NULL OR mt_dielektriniu_pdf IS NOT NULL OR mt_funkciniu_pdf IS NOT NULL")->fetchColumn();
        } catch (Exception $e) {
            error_log('TomoQMS gautiLokalusPDFKieki klaida: ' . $e->getMessage());
        }
        return $resultado;
    }

    /**
     * Persiunta VISUS vietinius PDF į Tomo_QMS, praleidžiant tuos,
     * kurie jau yra Tomo_QMS (stulpelis IS NOT NULL).
     * Veikia greičiau nei pilnas sinchronizavimas — sinchronizuoja TIK PDF.
     *
     * @param PDO $localConn Ryšys su vietine DB
     * @return array ['perkelti' => int, 'praleisti' => int, 'klaidos' => string[], 'trukme' => float]
     */
    public static function sinchVisusPDF(PDO $localConn): array {
        $conn = self::getConnection();
        if (!$conn) {
            return ['klaida' => 'Nepavyko prisijungti prie Tomo_QMS duomenų bazės (TOMO_QMS_DATABASE_URL nenustatytas)'];
        }

        $pradzia = microtime(true);

        $pdf_stulpeliai = [
            ['pdf' => 'mt_paso_pdf',        'failas' => 'mt_paso_failas',        'pav' => 'paso'],
            ['pdf' => 'mt_dielektriniu_pdf', 'failas' => 'mt_dielektriniu_failas', 'pav' => 'dielektrinių'],
            ['pdf' => 'mt_funkciniu_pdf',    'failas' => 'mt_funkciniu_failas',    'pav' => 'funkcinių'],
        ];

        $tomo_cols = $conn->query(
            "SELECT column_name FROM information_schema.columns WHERE table_name='gaminiai' AND table_schema='public'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $perkelti  = 0;
        $praleisti = 0;
        $klaidos   = [];

        foreach ($pdf_stulpeliai as $col) {
            if (!in_array($col['pdf'], $tomo_cols)) continue;

            $stmt = $localConn->prepare(
                "SELECT id FROM gaminiai WHERE {$col['pdf']} IS NOT NULL AND {$col['failas']} IS NOT NULL"
            );
            $stmt->execute();
            $local_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($local_ids as $local_gid) {
                $local_gid = (int)$local_gid;

                $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gid);
                if (!$tomo_gid) {
                    $praleisti++;
                    continue;
                }

                $chk = $conn->prepare("SELECT {$col['pdf']} IS NOT NULL AS turi FROM gaminiai WHERE id = ?");
                $chk->execute([$tomo_gid]);
                $turi = $chk->fetchColumn();
                if ($turi) {
                    $praleisti++;
                    continue;
                }

                try {
                    $ok = self::sinchPDF($localConn, $local_gid, $col['pdf'], $col['failas']);
                    if ($ok) {
                        $perkelti++;
                    } else {
                        $praleisti++;
                    }
                } catch (Exception $e) {
                    $klaidos[] = ucfirst($col['pav']) . ' PDF (gaminio ID ' . $local_gid . '): ' . $e->getMessage();
                    $praleisti++;
                }
            }
        }

        return [
            'perkelti'  => $perkelti,
            'praleisti' => $praleisti,
            'klaidos'   => $klaidos,
            'trukme'    => round(microtime(true) - $pradzia, 2),
        ];
    }

    /**
     * Grąžina tikslų skaičių PDF, kurie yra vietinėje DB bet dar nėra Tomo_QMS.
     * Naudoja batch užklausas (ne po vieną) — efektyviai lygina abi DB.
     *
     * @return array ['paso'=>int, 'dielektriniu'=>int, 'funkciniu'=>int,
     *               'viso_laukia'=>int, 'viso_jau_turi'=>int]
     *               arba ['klaida'=>string] jei nėra Tomo_QMS ryšio
     */
    public static function gautiPDFSkirtuma(PDO $localConn): array {
        $conn = self::getConnection();
        if (!$conn) {
            return ['klaida' => 'Nėra Tomo_QMS ryšio'];
        }

        $tomo_cols = $conn->query(
            "SELECT column_name FROM information_schema.columns WHERE table_name='gaminiai' AND table_schema='public'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $visi = [
            ['pdf' => 'mt_paso_pdf',        'pav' => 'paso'],
            ['pdf' => 'mt_dielektriniu_pdf', 'pav' => 'dielektriniu'],
            ['pdf' => 'mt_funkciniu_pdf',    'pav' => 'funkciniu'],
        ];
        $aktyvus = array_filter($visi, fn($c) => in_array($c['pdf'], $tomo_cols));
        $aktyvus = array_values($aktyvus);

        $out = ['paso' => 0, 'dielektriniu' => 0, 'funkciniu' => 0, 'viso_laukia' => 0, 'viso_jau_turi' => 0];

        if (empty($aktyvus)) return $out;

        // ── Batch: Tomo_QMS orders (nr → uzs_id) ──────────────────────────────
        $tomo_uzs_by_nr = [];
        foreach ($conn->query("SELECT id, TRIM(uzsakymo_numeris) AS nr FROM uzsakymai")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tomo_uzs_by_nr[trim($r['nr'])] = (int)$r['id'];
        }

        // ── Batch: Tomo_QMS gaminiai PDF flags (uzsakymo_id → row) ────────────
        $tomo_gam_by_uzs = [];
        if (!empty($tomo_uzs_by_nr)) {
            $ids = array_values($tomo_uzs_by_nr);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $null_checks = implode(', ', array_map(fn($c) => "{$c['pdf']} IS NOT NULL AS turi_{$c['pav']}", $aktyvus));
            $stmt = $conn->prepare("SELECT uzsakymo_id, $null_checks FROM gaminiai WHERE uzsakymo_id IN ($ph) ORDER BY id ASC");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $uid = (int)$r['uzsakymo_id'];
                if (!isset($tomo_gam_by_uzs[$uid])) {
                    $tomo_gam_by_uzs[$uid] = $r;
                }
            }
        }

        // ── Batch: local gaminiai with any PDF ────────────────────────────────
        $local_where = implode(' OR ', array_map(fn($c) => "g.{$c['pdf']} IS NOT NULL", $aktyvus));
        $local_cols  = implode(', ', array_map(fn($c) => "g.{$c['pdf']} IS NOT NULL AS turi_{$c['pav']}", $aktyvus));
        $local_rows  = $localConn->query("
            SELECT $local_cols, TRIM(u.uzsakymo_numeris) AS nr
            FROM gaminiai g
            JOIN uzsakymai u ON u.id = g.uzsakymo_id
            WHERE $local_where
        ")->fetchAll(PDO::FETCH_ASSOC);

        // ── Join & count ───────────────────────────────────────────────────────
        foreach ($local_rows as $r) {
            $nr          = trim($r['nr']);
            $tomo_uzs_id = $tomo_uzs_by_nr[$nr] ?? null;
            $tomo_gam    = $tomo_uzs_id ? ($tomo_gam_by_uzs[$tomo_uzs_id] ?? null) : null;

            foreach ($aktyvus as $c) {
                if (!$r["turi_{$c['pav']}"]) continue;

                if ($tomo_gam === null) {
                    // Order or gaminys not yet in Tomo_QMS — will be created+transferred
                    $out[$c['pav']]++;
                    $out['viso_laukia']++;
                } elseif ($tomo_gam["turi_{$c['pav']}"]) {
                    // Already present in Tomo_QMS — will be skipped
                    $out['viso_jau_turi']++;
                } else {
                    // Gaminys exists but PDF is null — will be transferred
                    $out[$c['pav']]++;
                    $out['viso_laukia']++;
                }
            }
        }

        return $out;
    }

    /**
     * Persiunta vieną PDF į Tomo_QMS. Grąžina true jei sėkmingai perkelta,
     * false jei praleista (nėra duomenų / stulpelio). Meta Exception jei klaida.
     *
     * @throws Exception jei UPDATE nepavyksta
     */
    public static function sinchPDF(PDO $localConn, int $local_gaminio_id, string $pdf_column, string $failas_column): bool {
        $conn = self::getConnection();
        if (!$conn) return false;

        $allowed_columns = ['mt_paso_pdf', 'mt_dielektriniu_pdf', 'mt_funkciniu_pdf'];
        $allowed_failas = ['mt_paso_failas', 'mt_dielektriniu_failas', 'mt_funkciniu_failas'];
        if (!in_array($pdf_column, $allowed_columns) || !in_array($failas_column, $allowed_failas)) return false;

        $tomo_cols = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='gaminiai' AND table_schema='public'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($pdf_column, $tomo_cols)) return false;

        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return false;

        try {
            $stmt = $localConn->prepare("SELECT $pdf_column, $failas_column FROM gaminiai WHERE id = ?");
            $stmt->execute([$local_gaminio_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !$row[$pdf_column]) return false;

            $pdfVal = $row[$pdf_column];
            if (is_resource($pdfVal)) {
                $pdfVal = stream_get_contents($pdfVal);
            }
            if (!$pdfVal) return false;
            // Jei PDO grąžino BYTEA kaip \x... hex eilutę — perduodame tiesiai,
            // kitaip (gryna binarinė eilutė) — hex-koduojame bin2hex().
            if (str_starts_with((string)$pdfVal, '\\x')) {
                $hexPdf = $pdfVal;
            } else {
                $hexPdf = '\\x' . bin2hex($pdfVal);
            }

            // Jei failo vardo nėra — naudojame numatytąjį pagal PDF tipą
            $failasVal = $row[$failas_column] ?? null;
            if (!$failasVal) {
                $tipas = str_replace(['mt_', '_pdf'], '', $pdf_column); // paso / dielektriniu / funkciniu
                $failasVal = 'mt_' . $tipas . '.pdf';
            }

            $upd = $conn->prepare("UPDATE gaminiai SET $pdf_column = :pdf, $failas_column = :failas WHERE id = :id");
            $upd->bindValue(':pdf', $hexPdf, PDO::PARAM_STR);
            $upd->bindValue(':failas', $failasVal);
            $upd->bindValue(':id', $tomo_gid);
            $upd->execute();
            $pdf_type = str_replace(['mt_', '_pdf'], '', $pdf_column);
            self::irasytLog("PDF ($pdf_type)", 'gaminiai', $uzs_nr, 1);
            return true;
        } catch (Exception $e) {
            self::irasytLog("PDF klaida ($pdf_column)", 'gaminiai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log("TomoQMS sinchPDF ($pdf_column) klaida: " . $e->getMessage());
            throw $e;
        }
    }
}
