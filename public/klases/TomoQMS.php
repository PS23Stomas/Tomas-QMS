<?php
class TomoQMS {
    private static ?PDO $conn = null;
    private static bool $available = true;
    private static bool $logTableChecked = false;

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

    public static function gautiSyncLogKieki(): int {
        $conn = self::getConnection();
        if (!$conn) return 0;
        try {
            return (int)$conn->query("SELECT COUNT(*) FROM sync_log")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

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
            $stmt = $localConn->prepare("SELECT eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas, defekto_nuotrauka, defekto_nuotraukos_pavadinimas FROM mt_funkciniai_bandymai WHERE gaminio_id = ? ORDER BY eil_nr");
            $stmt->execute([$local_gaminio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->beginTransaction();
            $conn->prepare("DELETE FROM mt_funkciniai_bandymai WHERE gaminio_id = ?")->execute([$tomo_gid]);
            $ins = $conn->prepare("INSERT INTO mt_funkciniai_bandymai (gaminio_id, eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas, defekto_nuotrauka, defekto_nuotraukos_pavadinimas) VALUES (:gid, :enr, :reik, :isv, :def, :da, :iv, :foto, :fpav)");
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
            self::irasytLog('Funkciniai bandymai', 'mt_funkciniai_bandymai', $uzs_nr, count($rows));
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Funkcinių band. klaida', 'mt_funkciniai_bandymai', $uzs_nr, 0, 'klaida', $e->getMessage());
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
            $stmt = $localConn->prepare("SELECT eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui FROM mt_komponentai WHERE gaminio_id = ?");
            $stmt->execute([$local_gaminio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->beginTransaction();
            $conn->prepare("DELETE FROM mt_komponentai WHERE gaminio_id = ?")->execute([$tomo_gid]);
            $ins = $conn->prepare("INSERT INTO mt_komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($rows as $r) {
                $ins->execute([$tomo_gid, $r['eiles_numeris'], $r['gamintojo_kodas'], $r['kiekis'], $r['aprasymas'], $r['gamintojas'], $r['parinkta_projektui']]);
            }
            $conn->commit();
            self::irasytLog('Komponentai', 'mt_komponentai', $uzs_nr, count($rows));
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Komponentų klaida', 'mt_komponentai', $uzs_nr, 0, 'klaida', $e->getMessage());
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

            $stmt = $localConn->prepare("SELECT eiles_nr, grandines_pavadinimas, grandines_itampa, bandymo_schema, bandymo_itampa_kv, bandymo_trukme, isvada FROM antriniu_grandiniu_bandymai WHERE gaminys_id = ?");
            $stmt->execute([$local_gaminys_id]);
            $vid_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $conn->prepare("DELETE FROM antriniu_grandiniu_bandymai WHERE gaminys_id = ?")->execute([$tomo_gid]);
            $ins1 = $conn->prepare("INSERT INTO antriniu_grandiniu_bandymai (gaminys_id, eiles_nr, grandines_pavadinimas, grandines_itampa, bandymo_schema, bandymo_itampa_kv, bandymo_trukme, isvada) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($vid_rows as $r) {
                $ins1->execute([$tomo_gid, $r['eiles_nr'], $r['grandines_pavadinimas'], $r['grandines_itampa'], $r['bandymo_schema'], $r['bandymo_itampa_kv'], $r['bandymo_trukme'], $r['isvada']]);
            }

            $stmt = $localConn->prepare("SELECT eiles_nr, aprasymas, itampa, schema1, schema2, schema3, schema4, schema5, schema6, isvada FROM mt_dielektriniai_bandymai WHERE gaminys_id = ?");
            $stmt->execute([$local_gaminys_id]);
            $maz_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $conn->prepare("DELETE FROM mt_dielektriniai_bandymai WHERE gaminys_id = ?")->execute([$tomo_gid]);
            $ins2 = $conn->prepare("INSERT INTO mt_dielektriniai_bandymai (gaminys_id, eiles_nr, aprasymas, itampa, schema1, schema2, schema3, schema4, schema5, schema6, isvada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($maz_rows as $r) {
                $ins2->execute([$tomo_gid, $r['eiles_nr'], $r['aprasymas'], $r['itampa'], $r['schema1'], $r['schema2'], $r['schema3'], $r['schema4'], $r['schema5'], $r['schema6'], $r['isvada']]);
            }

            $stmt = $localConn->prepare("SELECT eil_nr, tasko_pavadinimas, matavimo_tasku_skaicius, varza_ohm, budas, bukle FROM mt_izeminimo_tikrinimas WHERE gaminys_id = ?");
            $stmt->execute([$local_gaminys_id]);
            $iz_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $conn->prepare("DELETE FROM mt_izeminimo_tikrinimas WHERE gaminys_id = ?")->execute([$tomo_gid]);
            $ins3 = $conn->prepare("INSERT INTO mt_izeminimo_tikrinimas (gaminys_id, eil_nr, tasko_pavadinimas, matavimo_tasku_skaicius, varza_ohm, budas, bukle) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($iz_rows as $r) {
                $ins3->execute([$tomo_gid, $r['eil_nr'], $r['tasko_pavadinimas'], $r['matavimo_tasku_skaicius'], $r['varza_ohm'], $r['budas'], $r['bukle']]);
            }

            $conn->commit();
            $total = count($vid_rows) + count($maz_rows) + count($iz_rows);
            self::irasytLog('Dielektriniai bandymai', 'mt_dielektriniai_bandymai', $uzs_nr, $total);
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Dielektrinių klaida', 'mt_dielektriniai_bandymai', $uzs_nr, 0, 'klaida', $e->getMessage());
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
            $stmt = $localConn->prepare("SELECT sekcija, pozicija, gabaritas, nominalas, pozicijos_numeris FROM mt_saugikliu_ideklai WHERE gaminio_id = ?");
            $stmt->execute([$local_gaminio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->beginTransaction();
            $conn->prepare("DELETE FROM mt_saugikliu_ideklai WHERE gaminio_id = ?")->execute([$tomo_gid]);
            $ins = $conn->prepare("INSERT INTO mt_saugikliu_ideklai (gaminio_id, sekcija, pozicija, gabaritas, nominalas, pozicijos_numeris) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($rows as $r) {
                $ins->execute([$tomo_gid, $r['sekcija'], $r['pozicija'], $r['gabaritas'], $r['nominalas'], $r['pozicijos_numeris']]);
            }
            $conn->commit();
            self::irasytLog('Saugikliai', 'mt_saugikliu_ideklai', $uzs_nr, count($rows));
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            self::irasytLog('Saugiklių klaida', 'mt_saugikliu_ideklai', $uzs_nr, 0, 'klaida', $e->getMessage());
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
            $sql = "INSERT INTO mt_paso_teksto_korekcijos (gaminio_id, field_key, lang, tekstas, updated_at)
                    VALUES (:gid, :fk, :lang, :txt, CURRENT_TIMESTAMP)
                    ON CONFLICT (gaminio_id, field_key, lang) 
                    DO UPDATE SET tekstas = EXCLUDED.tekstas, updated_at = CURRENT_TIMESTAMP";
            $conn->prepare($sql)->execute([':gid' => $tomo_gid, ':fk' => $field_key, ':lang' => $lang, ':txt' => $tekstas]);
            self::irasytLog('Paso tekstas', 'mt_paso_teksto_korekcijos', $uzs_nr, 1);
        } catch (Exception $e) {
            self::irasytLog('Paso teksto klaida', 'mt_paso_teksto_korekcijos', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log('TomoQMS sinchPasoTeksta klaida: ' . $e->getMessage());
        }
    }

    public static function getQualityTomasConnection(): ?PDO {
        static $qtConn = null;
        $url = getenv('QUALITY_TOMAS_DATABASE_URL');
        if (!$url) return null;
        if ($qtConn !== null) return $qtConn;
        try {
            $parts = parse_url($url);
            if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'], $parts['path'])) return null;
            $dsn = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432) . ';dbname=' . ltrim($parts['path'], '/');
            if (strpos($url, 'sslmode=require') !== false) $dsn .= ';sslmode=require';
            $qtConn = new PDO($dsn, $parts['user'], $parts['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $qtConn;
        } catch (Exception $e) {
            error_log('QualityTomas prisijungimo klaida: ' . $e->getMessage());
            return null;
        }
    }

    public static function importuotiILocalDB(PDO $localConn): array {
        $qt = self::getQualityTomasConnection();
        if (!$qt) return ['klaida' => 'Nepavyko prisijungti prie quality_tomas duomenų bazės'];

        $rezultatas = ['nauji' => 0, 'atnaujinti' => 0, 'gaminiai' => 0, 'bandymai' => 0, 'komponentai' => 0, 'klaidos' => []];

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

            $existing_local = [];
            $st = $localConn->query("SELECT id, uzsakymo_numeris FROM uzsakymai");
            foreach ($st as $r) $existing_local[trim($r['uzsakymo_numeris'])] = (int)$r['id'];

            $uzsakovai_cache = [];
            $objektai_cache = [];

            foreach ($mt_uzsakymai as $uzs) {
                $nr = trim($uzs['uzsakymo_numeris'] ?? '');
                if ($nr === '') continue;

                $uzs_id_val = null;
                if (!empty($uzs['uzsakovas'])) {
                    if (!isset($uzsakovai_cache[$uzs['uzsakovas']])) {
                        $chk = $localConn->prepare("SELECT id FROM uzsakovai WHERE TRIM(uzsakovas) = TRIM(?)");
                        $chk->execute([$uzs['uzsakovas']]);
                        $uid = $chk->fetchColumn();
                        if (!$uid) {
                            $ins = $localConn->prepare("INSERT INTO uzsakovai (uzsakovas) VALUES (?) RETURNING id");
                            $ins->execute([$uzs['uzsakovas']]);
                            $uid = $ins->fetchColumn();
                        }
                        $uzsakovai_cache[$uzs['uzsakovas']] = (int)$uid;
                    }
                    $uzs_id_val = $uzsakovai_cache[$uzs['uzsakovas']];
                }

                $obj_id_val = null;
                if (!empty($uzs['objektas'])) {
                    if (!isset($objektai_cache[$uzs['objektas']])) {
                        $chk = $localConn->prepare("SELECT id FROM objektai WHERE TRIM(pavadinimas) = TRIM(?)");
                        $chk->execute([$uzs['objektas']]);
                        $oid = $chk->fetchColumn();
                        if (!$oid) {
                            $ins = $localConn->prepare("INSERT INTO objektai (pavadinimas) VALUES (?) RETURNING id");
                            $ins->execute([$uzs['objektas']]);
                            $oid = $ins->fetchColumn();
                        }
                        $objektai_cache[$uzs['objektas']] = (int)$oid;
                    }
                    $obj_id_val = $objektai_cache[$uzs['objektas']];
                }

                if (isset($existing_local[$nr])) {
                    $localConn->prepare("UPDATE uzsakymai SET kiekis=?,uzsakovas_id=?,objektas_id=?,gaminiu_rusis_id=?,sukurtas=? WHERE id=?")
                        ->execute([$uzs['kiekis'], $uzs_id_val, $obj_id_val, $uzs['gaminiu_rusis_id'], $uzs['sukurtas'], $existing_local[$nr]]);
                    $rezultatas['atnaujinti']++;
                } else {
                    $vart_id = $uzs['vartotojas_id'] ?? 1;
                    $chk_vart = $localConn->prepare("SELECT id FROM vartotojai WHERE id = ?");
                    $chk_vart->execute([$vart_id]);
                    if (!$chk_vart->fetchColumn()) {
                        $vart_id = (int)$localConn->query("SELECT id FROM vartotojai ORDER BY id LIMIT 1")->fetchColumn() ?: 1;
                    }

                    $ins = $localConn->prepare("INSERT INTO uzsakymai (uzsakymo_numeris,kiekis,uzsakovas_id,objektas_id,vartotojas_id,gaminiu_rusis_id,sukurtas) VALUES (?,?,?,?,?,?,?) RETURNING id");
                    $ins->execute([$nr, $uzs['kiekis'], $uzs_id_val, $obj_id_val, $vart_id, $uzs['gaminiu_rusis_id'], $uzs['sukurtas']]);
                    $new_id = (int)$ins->fetchColumn();
                    $existing_local[$nr] = $new_id;
                    $rezultatas['nauji']++;
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

            $qt_fb_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='mt_funkciniai_bandymai'")->fetchAll(PDO::FETCH_COLUMN);
            $has_photo = in_array('defekto_nuotrauka', $qt_fb_cols);
            $qt_mk_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='mt_komponentai'")->fetchAll(PDO::FETCH_COLUMN);
            $has_parinkta = in_array('parinkta_projektui', $qt_mk_cols);

            foreach ($mt_uzsakymai as $uzs) {
                $nr = trim($uzs['uzsakymo_numeris'] ?? '');
                if ($nr === '' || !isset($existing_local[$nr])) continue;
                $local_uzs_id = $existing_local[$nr];

                $gam_stmt = $qt->prepare("SELECT id as qt_gam_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr FROM gaminiai WHERE uzsakymo_id = ?");
                $gam_stmt->execute([$uzs['qt_id']]);
                $gaminiai = $gam_stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($gaminiai as $gam) {
                    $local_gid = false;
                    if ($gam['gaminio_numeris'] !== null && $gam['gaminio_numeris'] !== '') {
                        $chk = $localConn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id=? AND gaminio_numeris=?");
                        $chk->execute([$local_uzs_id, $gam['gaminio_numeris']]);
                        $local_gid = $chk->fetchColumn();
                    }
                    if (!$local_gid) {
                        $chk2 = $localConn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id=? AND gaminio_numeris IS NULL LIMIT 1");
                        $chk2->execute([$local_uzs_id]);
                        $local_gid = $chk2->fetchColumn();
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
                        $ins = $localConn->prepare("INSERT INTO gaminiai (uzsakymo_id,gaminio_numeris,gaminio_tipas_id,protokolo_nr) VALUES (?,?,?,?) RETURNING id");
                        $ins->execute([$local_uzs_id, $gam['gaminio_numeris'], $gam['gaminio_tipas_id'], $gam['protokolo_nr']]);
                        $local_gid = (int)$ins->fetchColumn();
                    }
                    $rezultatas['gaminiai']++;

                    $sel_cols = "eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas";
                    if ($has_photo) $sel_cols .= ", defekto_nuotrauka, defekto_nuotraukos_pavadinimas";

                    $fb_stmt = $qt->prepare("SELECT $sel_cols FROM mt_funkciniai_bandymai WHERE gaminio_id = ? ORDER BY eil_nr");
                    $fb_stmt->execute([$gam['qt_gam_id']]);
                    $fb_rows = $fb_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($fb_rows)) {
                        try {
                            $localConn->beginTransaction();
                            $localConn->prepare("DELETE FROM mt_funkciniai_bandymai WHERE gaminio_id = ?")->execute([$local_gid]);
                            foreach ($fb_rows as $r) {
                                if ($has_photo) {
                                    $fb_ins = $localConn->prepare("INSERT INTO mt_funkciniai_bandymai (gaminio_id,eil_nr,reikalavimas,isvada,defektas,darba_atliko,irase_vartotojas,defekto_nuotrauka,defekto_nuotraukos_pavadinimas) VALUES (?,?,?,?,?,?,?,?,?)");
                                    $foto = $r['defekto_nuotrauka'] ?? null;
                                    $fb_ins->bindValue(1, $local_gid);
                                    $fb_ins->bindValue(2, $r['eil_nr']);
                                    $fb_ins->bindValue(3, $r['reikalavimas']);
                                    $fb_ins->bindValue(4, $r['isvada']);
                                    $fb_ins->bindValue(5, $r['defektas']);
                                    $fb_ins->bindValue(6, $r['darba_atliko']);
                                    $fb_ins->bindValue(7, $r['irase_vartotojas']);
                                    $fb_ins->bindValue(8, $foto, $foto !== null ? PDO::PARAM_LOB : PDO::PARAM_NULL);
                                    $fb_ins->bindValue(9, $r['defekto_nuotraukos_pavadinimas'] ?? null);
                                    $fb_ins->execute();
                                } else {
                                    $localConn->prepare("INSERT INTO mt_funkciniai_bandymai (gaminio_id,eil_nr,reikalavimas,isvada,defektas,darba_atliko,irase_vartotojas) VALUES (?,?,?,?,?,?,?)")
                                        ->execute([$local_gid, $r['eil_nr'], $r['reikalavimas'], $r['isvada'], $r['defektas'], $r['darba_atliko'], $r['irase_vartotojas']]);
                                }
                            }
                            $localConn->commit();
                            $rezultatas['bandymai'] += count($fb_rows);
                        } catch (Exception $e) {
                            if ($localConn->inTransaction()) $localConn->rollBack();
                            $rezultatas['klaidos'][] = "Bandymai uzs=$nr: {$e->getMessage()}";
                        }
                    }

                    $mk_sel = "eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas" . ($has_parinkta ? ", parinkta_projektui" : ", NULL as parinkta_projektui");

                    $mk_stmt = $qt->prepare("SELECT $mk_sel FROM mt_komponentai WHERE gaminio_id = ? ORDER BY eiles_numeris");
                    $mk_stmt->execute([$gam['qt_gam_id']]);
                    $mk_rows = $mk_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($mk_rows)) {
                        try {
                            $localConn->beginTransaction();
                            $localConn->prepare("DELETE FROM mt_komponentai WHERE gaminio_id = ?")->execute([$local_gid]);
                            $mk_ins = $localConn->prepare("INSERT INTO mt_komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui) VALUES (?,?,?,?,?,?,?)");
                            foreach ($mk_rows as $r) {
                                $mk_ins->execute([$local_gid, $r['eiles_numeris'], $r['gamintojo_kodas'], $r['kiekis'], $r['aprasymas'], $r['gamintojas'], $r['parinkta_projektui']]);
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

            self::irasytLog(
                'Importas iš quality_tomas į local DB',
                'uzsakymai+gaminiai+bandymai',
                null,
                $rezultatas['nauji'] + $rezultatas['atnaujinti'] + $rezultatas['gaminiai'] + $rezultatas['bandymai'] + $rezultatas['komponentai'],
                empty($rezultatas['klaidos']) ? 'ok' : 'klaida',
                empty($rezultatas['klaidos']) ? null : implode('; ', array_slice($rezultatas['klaidos'], 0, 5))
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
                        ->execute([$u['id'], $u['vardas'], $u['pavarde'], $u['el_pastas'] ?? '', $u['slaptazodis'] ?? '', $u['role'] ?? 'user']);
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
            foreach ($st as $r) $existing[trim($r['uzsakymo_numeris'])] = (int)$r['id'];

            $order_map = [];
            foreach ($mt_uzsakymai as $uzs) {
                $nr = trim($uzs['uzsakymo_numeris']);
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

            // === 6. FUNKCINIAI BANDYMAI ===
            $qt_fb_cols = $qt->query("SELECT column_name FROM information_schema.columns WHERE table_name='mt_funkciniai_bandymai'")->fetchAll(PDO::FETCH_COLUMN);
            $has_photo = in_array('defekto_nuotrauka', $qt_fb_cols);

            $select_cols = "fb.gaminio_id, fb.eil_nr, fb.reikalavimas, fb.isvada, fb.defektas, fb.darba_atliko, fb.irase_vartotojas";
            if ($has_photo) $select_cols .= ", fb.defekto_nuotrauka, fb.defekto_nuotraukos_pavadinimas";

            $tests = $qt->query("
                SELECT $select_cols
                FROM mt_funkciniai_bandymai fb
                JOIN gaminiai g ON g.id = fb.gaminio_id
                JOIN uzsakymai u ON u.id = g.uzsakymo_id
                WHERE u.gaminiu_rusis_id = 2
                ORDER BY fb.gaminio_id, fb.eil_nr
            ")->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($tests as $t) $grouped[$t['gaminio_id']][] = $t;

            foreach ($grouped as $qt_gam_id => $rows) {
                $tomo_gam_id = $qt_to_tomo_gam[$qt_gam_id] ?? null;
                if (!$tomo_gam_id) continue;
                try {
                    $tomo->beginTransaction();
                    $tomo->prepare("DELETE FROM mt_funkciniai_bandymai WHERE gaminio_id = ?")->execute([$tomo_gam_id]);

                    if ($has_photo) {
                        $ins = $tomo->prepare("INSERT INTO mt_funkciniai_bandymai (gaminio_id,eil_nr,reikalavimas,isvada,defektas,darba_atliko,irase_vartotojas,defekto_nuotrauka,defekto_nuotraukos_pavadinimas) VALUES (?,?,?,?,?,?,?,?,?)");
                    } else {
                        $ins = $tomo->prepare("INSERT INTO mt_funkciniai_bandymai (gaminio_id,eil_nr,reikalavimas,isvada,defektas,darba_atliko,irase_vartotojas) VALUES (?,?,?,?,?,?,?)");
                    }
                    foreach ($rows as $r) {
                        $params = [$tomo_gam_id, $r['eil_nr'], $r['reikalavimas'], $r['isvada'], $r['defektas'], $r['darba_atliko'], $r['irase_vartotojas']];
                        if ($has_photo) {
                            $params[] = $r['defekto_nuotrauka'] ?? null;
                            $params[] = $r['defekto_nuotraukos_pavadinimas'] ?? null;
                        }
                        $ins->execute($params);
                    }
                    $tomo->commit();
                    $rezultatas['bandymai'] += count($rows);
                } catch (Exception $e) {
                    $tomo->rollBack();
                    $rezultatas['klaidos'][] = "Bandymai gam_id=$qt_gam_id: {$e->getMessage()}";
                }
            }

            // === 7. MT KOMPONENTAI ===
            $rezultatas['komponentai'] = 0;
            $komp_data = $qt->query("
                SELECT mk.gaminio_id as qt_gam_id, mk.eiles_numeris, mk.gamintojo_kodas, mk.kiekis, mk.aprasymas, mk.gamintojas, mk.parinkta_projektui
                FROM mt_komponentai mk
                JOIN gaminiai g ON g.id = mk.gaminio_id
                JOIN uzsakymai u ON u.id = g.uzsakymo_id
                WHERE u.gaminiu_rusis_id = 2
                ORDER BY mk.gaminio_id, mk.eiles_numeris
            ")->fetchAll(PDO::FETCH_ASSOC);

            $komp_grouped = [];
            foreach ($komp_data as $k) $komp_grouped[$k['qt_gam_id']][] = $k;

            foreach ($komp_grouped as $qt_gam_id => $rows) {
                $tomo_gam_id = $qt_to_tomo_gam[$qt_gam_id] ?? null;
                if (!$tomo_gam_id) continue;
                try {
                    $tomo->beginTransaction();
                    $tomo->prepare("DELETE FROM mt_komponentai WHERE gaminio_id = ?")->execute([$tomo_gam_id]);
                    $ins = $tomo->prepare("INSERT INTO mt_komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui) VALUES (?,?,?,?,?,?,?)");
                    foreach ($rows as $r) {
                        $ins->execute([$tomo_gam_id, $r['eiles_numeris'], $r['gamintojo_kodas'], $r['kiekis'], $r['aprasymas'], $r['gamintojas'], $r['parinkta_projektui']]);
                    }
                    $tomo->commit();
                    $rezultatas['komponentai'] += count($rows);
                } catch (Exception $e) {
                    $tomo->rollBack();
                    $rezultatas['klaidos'][] = "Komponentai gam_id=$qt_gam_id: {$e->getMessage()}";
                }
            }

            self::irasytLog(
                'Importas iš quality_tomas',
                'uzsakymai+bandymai+komponentai',
                null,
                $rezultatas['nauji'] + $rezultatas['atnaujinti'] + $rezultatas['bandymai'] + $rezultatas['komponentai'],
                empty($rezultatas['klaidos']) ? 'ok' : 'klaida',
                empty($rezultatas['klaidos']) ? null : implode('; ', array_slice($rezultatas['klaidos'], 0, 5))
            );

        } catch (Exception $e) {
            $rezultatas['klaidos'][] = $e->getMessage();
            self::irasytLog('Importo klaida', 'uzsakymai', null, 0, 'klaida', $e->getMessage());
        }

        return $rezultatas;
    }

    public static function sinchPDF(PDO $localConn, int $local_gaminio_id, string $pdf_column, string $failas_column): void {
        $conn = self::getConnection();
        if (!$conn) return;

        $allowed_columns = ['mt_paso_pdf', 'mt_dielektriniu_pdf', 'mt_funkciniu_pdf'];
        $allowed_failas = ['mt_paso_failas', 'mt_dielektriniu_failas', 'mt_funkciniu_failas'];
        if (!in_array($pdf_column, $allowed_columns) || !in_array($failas_column, $allowed_failas)) return;

        $tomo_cols = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='gaminiai' AND table_schema='public'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($pdf_column, $tomo_cols)) return;

        $uzs_nr = self::gautiUzsakymoNr($localConn, $local_gaminio_id);
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $stmt = $localConn->prepare("SELECT $pdf_column, $failas_column FROM gaminiai WHERE id = ?");
            $stmt->execute([$local_gaminio_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !$row[$pdf_column]) return;

            $upd = $conn->prepare("UPDATE gaminiai SET $pdf_column = :pdf, $failas_column = :failas WHERE id = :id");
            $upd->bindValue(':pdf', $row[$pdf_column], PDO::PARAM_LOB);
            $upd->bindValue(':failas', $row[$failas_column]);
            $upd->bindValue(':id', $tomo_gid);
            $upd->execute();
            $pdf_type = str_replace(['mt_', '_pdf'], '', $pdf_column);
            self::irasytLog("PDF ($pdf_type)", 'gaminiai', $uzs_nr, 1);
        } catch (Exception $e) {
            self::irasytLog("PDF klaida ($pdf_column)", 'gaminiai', $uzs_nr, 0, 'klaida', $e->getMessage());
            error_log("TomoQMS sinchPDF ($pdf_column) klaida: " . $e->getMessage());
        }
    }
}
