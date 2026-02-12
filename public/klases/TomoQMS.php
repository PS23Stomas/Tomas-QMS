<?php
class TomoQMS {
    private static ?PDO $conn = null;
    private static bool $available = true;

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
            return self::$conn;
        } catch (Exception $e) {
            error_log('TomoQMS prisijungimo klaida: ' . $e->getMessage());
            self::$available = false;
            return null;
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

    public static function sinchronizuotiUzsakyma(string $uzsakymo_numeris, ?string $uzsakovas_pav, ?string $objektas_pav, int $kiekis = 1, int $vartotojas_id = 1): ?int {
        $conn = self::getConnection();
        if (!$conn || trim($uzsakymo_numeris) === '') return null;
        try {
            $stmt = $conn->prepare("SELECT id FROM uzsakymai WHERE TRIM(uzsakymo_numeris) = TRIM(:nr)");
            $stmt->execute([':nr' => $uzsakymo_numeris]);
            $uzs_id = $stmt->fetchColumn();

            $uzsakovas_id = $uzsakovas_pav ? self::gautiArbaKurtiUzsakova($uzsakovas_pav) : null;
            $objektas_id = $objektas_pav ? self::gautiArbaKurtiObjekta($objektas_pav) : null;

            if ($uzs_id) {
                $stmt = $conn->prepare("UPDATE uzsakymai SET kiekis = :kiekis, uzsakovas_id = :uzs_id, objektas_id = :obj_id WHERE id = :id");
                $stmt->execute([':kiekis' => $kiekis, ':uzs_id' => $uzsakovas_id, ':obj_id' => $objektas_id, ':id' => $uzs_id]);
                return (int)$uzs_id;
            } else {
                $stmt = $conn->prepare("INSERT INTO uzsakymai (uzsakymo_numeris, kiekis, uzsakovas_id, objektas_id, vartotojas_id) VALUES (:nr, :kiekis, :uzs_id, :obj_id, :vart_id) RETURNING id");
                $stmt->execute([':nr' => $uzsakymo_numeris, ':kiekis' => $kiekis, ':uzs_id' => $uzsakovas_id, ':obj_id' => $objektas_id, ':vart_id' => $vartotojas_id]);
                return (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log('TomoQMS uzsakymas klaida: ' . $e->getMessage());
            return null;
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
                       uz.uzsakovas, o.pavadinimas as objektas, u.kiekis
                FROM gaminiai g
                JOIN uzsakymai u ON u.id = g.uzsakymo_id
                LEFT JOIN uzsakovai uz ON uz.id = u.uzsakovas_id
                LEFT JOIN objektai o ON o.id = u.objektas_id
                WHERE g.id = :gid
            ");
            $stmt->execute([':gid' => $local_gaminio_id]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$info || !$info['uzsakymo_numeris']) return null;

            $tomo_uzs_id = self::sinchronizuotiUzsakyma(
                $info['uzsakymo_numeris'],
                $info['uzsakovas'] ?? null,
                $info['objektas'] ?? null,
                (int)($info['kiekis'] ?? 1)
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

    public static function sinchFunkciniai(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
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
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('TomoQMS sinchFunkciniai klaida: ' . $e->getMessage());
        }
    }

    public static function sinchKomponentai(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
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
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('TomoQMS sinchKomponentai klaida: ' . $e->getMessage());
        }
    }

    public static function sinchDielektriniai(PDO $localConn, int $local_gaminys_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
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
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('TomoQMS sinchDielektriniai klaida: ' . $e->getMessage());
        }
    }

    public static function sinchSaugiklius(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
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
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('TomoQMS sinchSaugiklius klaida: ' . $e->getMessage());
        }
    }

    public static function sinchPrietaisus(PDO $localConn, int $local_gaminio_id): void {
        $conn = self::getConnection();
        if (!$conn) return;
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
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('TomoQMS sinchPrietaisus klaida: ' . $e->getMessage());
        }
    }

    public static function sinchProtokoloNr(PDO $localConn, int $local_gaminio_id, string $protokolo_nr): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $conn->prepare("UPDATE gaminiai SET protokolo_nr = ? WHERE id = ?")->execute([$protokolo_nr, $tomo_gid]);
        } catch (Exception $e) {
            error_log('TomoQMS sinchProtokoloNr klaida: ' . $e->getMessage());
        }
    }

    public static function sinchPasoTeksta(PDO $localConn, int $local_gaminio_id, string $field_key, string $lang, string $tekstas): void {
        $conn = self::getConnection();
        if (!$conn) return;
        $tomo_gid = self::gautiTomoGaminioId($localConn, $local_gaminio_id);
        if (!$tomo_gid) return;
        try {
            $sql = "INSERT INTO mt_paso_teksto_korekcijos (gaminio_id, field_key, lang, tekstas, updated_at)
                    VALUES (:gid, :fk, :lang, :txt, CURRENT_TIMESTAMP)
                    ON CONFLICT (gaminio_id, field_key, lang) 
                    DO UPDATE SET tekstas = EXCLUDED.tekstas, updated_at = CURRENT_TIMESTAMP";
            $conn->prepare($sql)->execute([':gid' => $tomo_gid, ':fk' => $field_key, ':lang' => $lang, ':txt' => $tekstas]);
        } catch (Exception $e) {
            error_log('TomoQMS sinchPasoTeksta klaida: ' . $e->getMessage());
        }
    }

    public static function sinchPDF(PDO $localConn, int $local_gaminio_id, string $pdf_column, string $failas_column): void {
        $conn = self::getConnection();
        if (!$conn) return;

        $allowed_columns = ['mt_paso_pdf', 'mt_dielektriniu_pdf', 'mt_funkciniu_pdf'];
        $allowed_failas = ['mt_paso_failas', 'mt_dielektriniu_failas', 'mt_funkciniu_failas'];
        if (!in_array($pdf_column, $allowed_columns) || !in_array($failas_column, $allowed_failas)) return;

        $tomo_cols = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='gaminiai' AND table_schema='public'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($pdf_column, $tomo_cols)) return;

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
        } catch (Exception $e) {
            error_log("TomoQMS sinchPDF ($pdf_column) klaida: " . $e->getMessage());
        }
    }
}
