<?php
/**
 * Duomenų bazės migracijos klasė - automatinis lentelių kūrimas ir laukų taisymas
 */
class DBMigracija {
    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /** Paleidžia visas migracijas: sukuria trūkstamas lenteles, prideda stulpelius ir pataiso varchar laukus */
    public function paleisti(): void {
        $this->pervadintiMtLenteles();
        $this->sukurtiTrukstamasLenteles();
        $this->sukurtiFunkciniuSablona();
        $this->pridetiDielektriniuVidutinesStulpelius();
        $this->pridetiMtPasoStulpelius();
        $this->pridetiMtDielektriniuStulpelius();
        $this->pridetiDefektoNuotraukuStulpelius();
        $this->pridetiMtFunkciniuPdfStulpelius();
        $this->pridetiPataisytaStulpeli();
        $this->pridetiIssiustaKamStulpeli();
        $this->pataisytiVarcharLaukus();
        $this->pridetiSablonoGrupesStulpeli();
        $this->pridetiGaminioPavadinimaStulpeli();
        $this->pridetiDielektriniuIssaugotiStulpeli();
        $this->sukurtiPretenzijoEmailHistoryLentele();
        $this->sinchronizuotiSekas();
        $this->sukurtiImonesNustatymuLentele();
        $this->pridetiVartotojoParasoStulpelius();
        $this->pridetiVartotojoPareiguStulpeli();
        $this->pridetiUzsakymoImonesStulpelius();
        $this->sukurtiRememberTokensLentele();
    }

    /** Sukuria trūkstamas duomenų bazės lenteles (bandymai_prietaisai) */
    private function sukurtiTrukstamasLenteles(): void {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS bandymai_prietaisai (
                    id SERIAL PRIMARY KEY,
                    gaminys_id INTEGER NOT NULL,
                    prietaiso_tipas VARCHAR(255),
                    prietaiso_nr VARCHAR(255),
                    patikra_data DATE,
                    galioja_iki DATE,
                    sertifikato_nr VARCHAR(255)
                )
            ");
        } catch (PDOException $e) {
        }
    }

    /** Prideda vidutinės įtampos stulpelius prie dielektriniai_bandymai lentelės */
    private function pridetiDielektriniuVidutinesStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'dielektriniai_bandymai' AND column_name = 'tipas'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE dielektriniai_bandymai ADD COLUMN tipas VARCHAR(20) DEFAULT 'mazos_itampos'");
                $this->conn->exec("ALTER TABLE dielektriniai_bandymai ADD COLUMN grandines_pavadinimas TEXT");
                $this->conn->exec("ALTER TABLE dielektriniai_bandymai ADD COLUMN grandines_itampa VARCHAR(50)");
                $this->conn->exec("ALTER TABLE dielektriniai_bandymai ADD COLUMN bandymo_schema VARCHAR(255)");
                $this->conn->exec("ALTER TABLE dielektriniai_bandymai ADD COLUMN bandymo_itampa_kv VARCHAR(50)");
                $this->conn->exec("ALTER TABLE dielektriniai_bandymai ADD COLUMN bandymo_trukme VARCHAR(50)");
            }
        } catch (PDOException $e) {
        }
    }

    /** Sukuria funkcinių bandymų šablono lentelę su numatytais reikalavimais */
    private function sukurtiFunkciniuSablona(): void {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS funkciniu_sablonas (
                    id SERIAL PRIMARY KEY,
                    eil_nr INTEGER NOT NULL,
                    pavadinimas TEXT NOT NULL
                )
            ");
            $stmt = $this->conn->query("SELECT COUNT(*) FROM funkciniu_sablonas");
            if ((int)$stmt->fetchColumn() === 0) {
                $numatytieji = [
                    'MT korpuso surinkimas','MT sienų surinkimas','MT stogo surinkimas','MT stogo tvirtinimas',
                    'Pagrindo (pamato) surinkimas įžeminimo ženklų prikniedijimas','10 kV kabelių gaminimas',
                    '0,4 kV kabelių gaminimas','10 kV kabelių sumontavimas į MT ir movų komplektacija',
                    '0,4 kV kabelių sumontavimas į MT','MT durų surinkimas','MT durų sumontavimas sureguliavimas',
                    '10 kV narvelio sumontavimas','10 kV šynų , skardos, laikikliai montavimas',
                    '0,4 kV komutacinių aparatų montavimas,šynų montavimas','Apskaitos ir antrinių grandinių montavimas',
                    'Komplektacija','MT sumontavimas ant pamato','Pagalbinių grandinių (apšvietimas, ventiliacija) montavimas',
                    '0,4 kV įrenginių izoliacijos varža (atitiktis)','Lipdukai pagal projektą suklijavimas','Išvalymas'
                ];
                $ins = $this->conn->prepare("INSERT INTO funkciniu_sablonas (eil_nr, pavadinimas) VALUES (?, ?)");
                foreach ($numatytieji as $i => $pav) {
                    $ins->execute([$i + 1, $pav]);
                }
            }
        } catch (PDOException $e) {
        }
    }

    /** Prideda mt_paso_pdf ir mt_paso_failas stulpelius į gaminiai lentelę */
    private function pridetiMtPasoStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'gaminiai' AND column_name = 'mt_paso_pdf'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN mt_paso_pdf BYTEA");
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN mt_paso_failas VARCHAR(255)");
            }
        } catch (PDOException $e) {
        }
    }

    /** Prideda mt_dielektriniu_pdf ir mt_dielektriniu_failas stulpelius į gaminiai lentelę */
    private function pridetiMtDielektriniuStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'gaminiai' AND column_name = 'mt_dielektriniu_pdf'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN mt_dielektriniu_pdf BYTEA");
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN mt_dielektriniu_failas VARCHAR(255)");
            }
        } catch (PDOException $e) {
        }
    }

    /** Prideda defekto nuotraukų stulpelius į funkciniai_bandymai lentelę */
    private function pridetiDefektoNuotraukuStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'funkciniai_bandymai' AND column_name = 'defekto_nuotrauka'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE funkciniai_bandymai ADD COLUMN defekto_nuotrauka BYTEA");
                $this->conn->exec("ALTER TABLE funkciniai_bandymai ADD COLUMN defekto_nuotraukos_pavadinimas VARCHAR(255)");
            }
        } catch (PDOException $e) {
        }
    }

    /** Prideda mt_funkciniu_pdf ir mt_funkciniu_failas stulpelius į gaminiai lentelę */
    private function pridetiMtFunkciniuPdfStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'gaminiai' AND column_name = 'mt_funkciniu_pdf'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN mt_funkciniu_pdf BYTEA");
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN mt_funkciniu_failas VARCHAR(255)");
            }
        } catch (PDOException $e) {
        }
    }

    /** Prideda pataisyta stulpelį į funkciniai_bandymai lentelę */
    private function pridetiPataisytaStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'funkciniai_bandymai' AND column_name = 'pataisyta'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE funkciniai_bandymai ADD COLUMN pataisyta TEXT DEFAULT ''");
            }
        } catch (PDOException $e) {
        }
    }

    private function pridetiIssiustaKamStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'funkciniai_bandymai' AND column_name = 'issiusta_kam'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE funkciniai_bandymai ADD COLUMN issiusta_kam TEXT DEFAULT ''");
            }
        } catch (PDOException $e) {
        }
    }

    private function pridetiSablonoGrupesStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'funkciniu_sablonas' AND column_name = 'gaminiu_rusis_id'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE funkciniu_sablonas ADD COLUMN gaminiu_rusis_id INTEGER DEFAULT 2");
                $this->conn->exec("UPDATE funkciniu_sablonas SET gaminiu_rusis_id = 2 WHERE gaminiu_rusis_id IS NULL");
            }
        } catch (PDOException $e) {
        }
    }

    /** Pataiso nurodytus varchar laukus, pakeisdama juos į TEXT tipą */
    private function pataisytiVarcharLaukus(): void {
        $laukai = [
            ['lentele' => 'gaminio_kirtikliai', 'laukas' => 'linijos_10kv_nr'],
            ['lentele' => 'gaminio_kirtikliai', 'laukas' => 'sekcijinis_04kv_nr'],
            ['lentele' => 'gaminio_kirtikliai', 'laukas' => 'ivadinis_04kv_nr'],
            ['lentele' => 'paso_teksto_korekcijos', 'laukas' => 'tekstas'],
        ];

        foreach ($laukai as $info) {
            $this->pakeistiIText($info['lentele'], $info['laukas']);
        }
    }

    /** Pakeičia nurodyto lauko tipą į TEXT, jei dabartinis tipas nėra TEXT */
    private function pakeistiIText(string $lentele, string $laukas): void {
        try {
            $sql = "SELECT data_type FROM information_schema.columns 
                    WHERE table_name = :lentele AND column_name = :laukas";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':lentele' => $lentele, ':laukas' => $laukas]);
            $tipas = $stmt->fetchColumn();

            if ($tipas && $tipas !== 'text') {
                $alter = "ALTER TABLE {$lentele} ALTER COLUMN {$laukas} TYPE TEXT";
                $this->conn->exec($alter);
            }
        } catch (PDOException $e) {
        }
    }

    private function pridetiGaminioPavadinimaStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'gaminiai' AND column_name = 'pavadinimas'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN pavadinimas TEXT DEFAULT NULL");
            }
        } catch (PDOException $e) {
        }
    }

    private function sinchronizuotiSekas(): void {
        $lenteles = ['uzsakymai', 'gaminiai', 'gaminiu_rusys', 'uzsakovai', 'objektai', 'vartotojai', 'pretenzijos', 'prietaisai', 'gaminio_tipai', 'funkciniu_sablonas'];
        foreach ($lenteles as $lentele) {
            try {
                $col_check = $this->conn->prepare("SELECT column_default FROM information_schema.columns WHERE table_name = :t AND column_name = 'id'");
                $col_check->execute([':t' => $lentele]);
                $default = $col_check->fetchColumn();
                if ($default && preg_match("/nextval\('([^']+)'/", $default, $m)) {
                    $seq_name = $m[1];
                    $max_id = (int)$this->conn->query("SELECT COALESCE(MAX(id), 0) FROM {$lentele}")->fetchColumn();
                    if ($max_id > 0) {
                        $this->conn->exec("SELECT setval('{$seq_name}', {$max_id})");
                    }
                }
            } catch (PDOException $e) {
            }
        }
    }

    private function sukurtiPretenzijoEmailHistoryLentele(): void {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS pretenzijos_email_history (
                    id SERIAL PRIMARY KEY,
                    pretenzija_id INTEGER NOT NULL REFERENCES pretenzijos(id) ON DELETE CASCADE,
                    email_delegated_to VARCHAR(255),
                    email_cc TEXT,
                    email_subject VARCHAR(500),
                    sent_by VARCHAR(255),
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    feedback_text TEXT,
                    feedback_at TIMESTAMP,
                    feedback_by VARCHAR(255)
                )
            ");
        } catch (PDOException $e) {
        }
    }

    private function pervadintiMtLenteles(): void {
        $pervadinimas = [
            'mt_dielektriniai_bandymai' => 'dielektriniai_bandymai',
            'mt_funkciniai_bandymai' => 'funkciniai_bandymai',
            'mt_funkciniu_sablonas' => 'funkciniu_sablonas',
            'mt_izeminimo_tikrinimas' => 'izeminimo_tikrinimas',
            'mt_komponentai' => 'komponentai',
            'mt_paso_teksto_korekcijos' => 'paso_teksto_korekcijos',
            'mt_saugikliu_ideklai' => 'saugikliu_ideklai',
        ];
        foreach ($pervadinimas as $senas => $naujas) {
            try {
                $stmt = $this->conn->prepare("SELECT to_regclass(:senas)");
                $stmt->execute([':senas' => $senas]);
                $senasEgzistuoja = $stmt->fetchColumn();
                if (!$senasEgzistuoja) continue;

                $stmt2 = $this->conn->prepare("SELECT to_regclass(:naujas)");
                $stmt2->execute([':naujas' => $naujas]);
                $naujasEgzistuoja = $stmt2->fetchColumn();
                if ($naujasEgzistuoja) {
                    $cnt = (int)$this->conn->query("SELECT COUNT(*) FROM {$naujas}")->fetchColumn();
                    if ($cnt > 0) continue;
                    $this->conn->exec("DROP TABLE {$naujas} CASCADE");
                }
                $this->conn->exec("ALTER TABLE {$senas} RENAME TO {$naujas}");
            } catch (PDOException $e) {
            }
        }
    }

    private function sukurtiImonesNustatymuLentele(): void {
        try {
            $stmt = $this->conn->query("
                SELECT data_type FROM information_schema.columns 
                WHERE table_name = 'imones_nustatymai' AND column_name = 'pavadinimas'
            ");
            $tipas = $stmt->fetchColumn();
            if ($tipas !== false && $tipas !== 'character varying') {
                $this->conn->exec("DROP TABLE imones_nustatymai");
            }

            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS imones_nustatymai (
                    id SERIAL PRIMARY KEY,
                    pavadinimas VARCHAR(255) DEFAULT 'UAB \"ELGA\"',
                    adresas TEXT DEFAULT 'Pramonės g. 12, LT-78150 Šiauliai, Lietuva',
                    telefonas VARCHAR(100) DEFAULT '+370 41 594710',
                    faksas VARCHAR(100) DEFAULT '+370 41 594725',
                    el_pastas VARCHAR(255) DEFAULT 'info@elga.lt',
                    internetas VARCHAR(255) DEFAULT 'www.elga.lt',
                    logotipas BYTEA,
                    logotipo_tipas VARCHAR(50)
                )
            ");
            $cnt = (int)$this->conn->query("SELECT COUNT(*) FROM imones_nustatymai")->fetchColumn();
            if ($cnt === 0) {
                $this->conn->exec("INSERT INTO imones_nustatymai (pavadinimas) VALUES ('UAB \"ELGA\"')");
            }
        } catch (PDOException $e) {
        }
    }

    private function pridetiVartotojoParasoStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'vartotojai' AND column_name = 'parasas'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE vartotojai ADD COLUMN parasas BYTEA, ADD COLUMN parasas_tipas VARCHAR(50)");
            }
        } catch (PDOException $e) {
        }
    }

    private function pridetiVartotojoPareiguStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'vartotojai' AND column_name = 'pareigos'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE vartotojai ADD COLUMN pareigos VARCHAR(100) DEFAULT ''");
            }
        } catch (PDOException $e) {
        }
    }

    private function pridetiUzsakymoImonesStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'uzsakymai' AND column_name = 'imone_pavadinimas'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE uzsakymai 
                    ADD COLUMN imone_pavadinimas VARCHAR(255),
                    ADD COLUMN imone_adresas TEXT,
                    ADD COLUMN imone_telefonas VARCHAR(100),
                    ADD COLUMN imone_faksas VARCHAR(100),
                    ADD COLUMN imone_el_pastas VARCHAR(255),
                    ADD COLUMN imone_internetas VARCHAR(255)
                ");
            }
        } catch (PDOException $e) {
        }
    }

    private function sukurtiRememberTokensLentele(): void {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS remember_tokens (
                    id SERIAL PRIMARY KEY,
                    vartotojas_id INTEGER NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expires_at TIMESTAMP NOT NULL
                )
            ");
        } catch (PDOException $e) {
        }
    }

    private function pridetiDielektriniuIssaugotiStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'gaminiai' AND column_name = 'dielektriniai_issaugoti'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE gaminiai ADD COLUMN dielektriniai_issaugoti BOOLEAN DEFAULT FALSE");
            }
        } catch (PDOException $e) {
        }
    }
}
