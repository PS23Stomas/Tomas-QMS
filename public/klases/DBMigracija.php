<?php
/**
 * Duomenų bazės migracijos klasė
 *
 * Migracija — tai tarsi "remontas" duomenų bazėje.
 * Kai programa atnaujinama ir reikia naujų lentelių ar stulpelių,
 * ši klasė automatiškai juos sukuria — be rankinio darbo.
 *
 * Kaip veikia: kiekvieną kartą paleidus serverį, paleisti() metodas
 * peržiūri visas reikalingas lenteles ir stulpelius. Jei ko nors trūksta —
 * sukuria. Jei viskas yra — nieko nedaro. Taip sistema visada būna atnaujinta.
 *
 * Visi metodai yra saugūs pakartotiniam paleidimui (idempotentiniai) —
 * tai reiškia, kad nors kvieski 100 kartų, duomenys nesidubliuos.
 */
class DBMigracija {
    /** Duomenų bazės ryšys */
    private $conn;

    /**
     * Sukuria migracijos objektą su duomenų bazės ryšiu.
     */
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Paleidžia VISAS migracijas iš eilės.
     * Ši funkcija kviečiama automatiškai kiekvieno puslapio pradžioje
     * per config.php. Jei duomenų bazė jau atnaujinta — viskas vyksta greitai.
     */
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
        $this->atnaujintiElgaRekvizitus();
        $this->pridetiVartotojoParasoStulpelius();
        $this->pridetiVartotojoPareiguStulpeli();
        $this->pridetiUzsakymoImonesStulpelius();
        $this->sukurtiRememberTokensLentele();
        $this->pridetiDefektoPdfStulpelius();
        $this->pridetiQtPretenzijaIdStulpeli();
        $this->sukurtiPretenzijoFailuLentele();
        $this->pridetiPapildomoKomentaroStulpeli();
        $this->pataisytiKomponentuVarchar();
        $this->pridetiPerziurosTokena();
        $this->normalizuotiRolesDB();
        $this->pridetiAktyvusStulpeli();
    }

    /**
     * Sukuria bandymai_prietaisai lentelę, jei jos dar nėra.
     * Šioje lentelėje saugoma informacija apie matavimo prietaisus,
     * naudotus atliekant bandymus (tipas, serijos numeris, sertifikatas).
     */
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

    /**
     * Prideda vidutinės įtampos bandymų stulpelius į dielektriniai_bandymai lentelę.
     * Šie stulpeliai reikalingi aukštos įtampos (10 kV) bandymų duomenims saugoti:
     * grandinės pavadinimas, įtampa, bandymo schema, trukmė ir kt.
     */
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

    /**
     * Sukuria funkciniu_sablonas lentelę ir užpildo ją 21 numatytuoju reikalavimu,
     * jei ji dar tuščia. Šablono reikalavimai — tai gamybos žingsniai, kuriuos
     * reikia patikrinti gaminant kiekvieną MT transformatorinę.
     */
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

    /**
     * Prideda mt_paso_pdf ir mt_paso_failas stulpelius į gaminiai lentelę.
     * Juose saugoma sugeneruoto MT paso PDF failo turinis (dvejetainiai duomenys)
     * ir failo pavadinimas.
     */
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

    /**
     * Prideda mt_dielektriniu_pdf ir mt_dielektriniu_failas stulpelius į gaminiai lentelę.
     * Juose saugoma sugeneruoto dielektrinių bandymų protokolo PDF failo turinis ir pavadinimas.
     */
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

    /**
     * Prideda defekto nuotraukų stulpelius į funkciniai_bandymai lentelę.
     * Kai atliekant funkcinį bandymą randamas defektas, galima įkelti jo nuotrauką —
     * ji saugoma duomenų bazėje dvejetainiu formatu (BYTEA).
     */
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

    /**
     * Prideda mt_funkciniu_pdf ir mt_funkciniu_failas stulpelius į gaminiai lentelę.
     * Juose saugoma sugeneruoto funkcinių bandymų protokolo PDF failo turinis ir pavadinimas.
     */
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

    /**
     * Prideda pataisyta stulpelį į funkciniai_bandymai lentelę.
     * Šiame stulpelyje saugoma informacija ar rastas defektas buvo ištaisytas
     * ir kas jį ištaisė.
     */
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

    /**
     * Prideda issiusta_kam stulpelį į funkciniai_bandymai lentelę.
     * Saugo informaciją kam buvo išsiųstas pranešimas apie šį defektą
     * (pvz. "Jonas Jonaitis, jonas@elga.lt").
     */
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

    /**
     * Prideda gaminiu_rusis_id stulpelį į funkciniu_sablonas lentelę.
     * Tai leidžia turėti SKIRTINGUS šablonų reikalavimų sąrašus kiekvienai
     * gaminių rūšiai (MT, USN, SI-04 ir kt.), o ne vieną bendrą.
     */
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

    /**
     * Pataiso nurodytus varchar laukus, pakeisdama juos į TEXT tipą.
     * TEXT tipas neturi ilgio apribojimų — tinka ilgiems tekstiniams laukams.
     */
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

    /**
     * Pakeičia konkretaus lauko tipą į TEXT (neriboto ilgio tekstas),
     * bet tik jei dabartinis tipas nėra TEXT. Jei jau TEXT — nieko nedaro.
     *
     * @param string $lentele Lentelės pavadinimas
     * @param string $laukas  Stulpelio pavadinimas
     */
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

    /**
     * Prideda pavadinimas stulpelį į gaminiai lentelę.
     * Leidžia saugoti laisvą gaminio pavadinimą be ryšio su gaminio_tipai lentele.
     */
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

    /**
     * Sinchronizuoja automatinio numeravimo sekas (SEQUENCE) su faktiniais duomenimis.
     *
     * Problema: kai duomenys importuojami tiesiogiai (ne per programą),
     * automatinis ID skaitliukas gali "atsilikti" ir bandyti priskirti ID,
     * kuris jau egzistuoja. Tai sukelia klaidas.
     *
     * Šis metodas sutvarko skaitliukus — nustato juos į didžiausią esamą ID.
     */
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

    /**
     * Sukuria pretenzijos_email_history lentelę, jei jos dar nėra.
     * Šioje lentelėje saugoma kiekvieno laiško, išsiųsto dėl pretenzijos, istorija:
     * kam išsiųsta, kas išsiuntė, kada, koks atsakymas gautas ir t.t.
     */
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

    /**
     * Pervadina senąsias lenteles, kurios turėjo "mt_" priešdėlį, į universalius pavadinimus.
     *
     * Senos lentelės (pvz. mt_funkciniai_bandymai) buvo skirtos tik MT gaminiams.
     * Sistemos plėtros metu priešdėlis "mt_" buvo pašalintas, kad lenteles galėtų
     * naudoti ir kitos gaminių rūšys (USN, SI-04 ir kt.).
     *
     * Pervadinimas atliekamas tik jei senasis pavadinimas egzistuoja ir naujasis — ne.
     */
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

    /**
     * Sukuria imones_nustatymai lentelę su UAB "ELGA" rekvizitais,
     * jei jos dar nėra. Šioje lentelėje saugomi įmonės duomenys,
     * rodomi PDF dokumentuose ir laiškuose (pavadinimas, adresas, telefonas ir kt.).
     */
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

    /**
     * Vienkartinis pataisymas: atnaujina UAB "ELGA" rekvizitus, bet TIK jei
     * pavadinimas yra "UAB tomas" (senas bandomasis įrašas). Jei administratorius
     * jau pakeitė duomenis — šis metodas jų nepalies.
     */
    private function atnaujintiElgaRekvizitus(): void {
        try {
            $this->conn->exec("
                UPDATE imones_nustatymai SET
                    pavadinimas  = 'UAB \"ELGA\"',
                    adresas      = 'Pramonės g. 12, LT-78150 Šiauliai, Lietuva',
                    telefonas    = '+370 41 594710',
                    faksas       = '+370 41 594725',
                    el_pastas    = 'info@elga.lt',
                    internetas   = 'www.elga.lt'
                WHERE id = 1
                  AND pavadinimas = 'UAB tomas'
            ");
        } catch (PDOException $e) {
        }
    }

    /**
     * Prideda parasas ir parasas_tipas stulpelius į vartotojai lentelę.
     * Vartotojas gali įkelti savo parašo vaizdą — jis naudojamas
     * PDF dokumentuose (funkcinių ir dielektrinių bandymų protokolai, paso dokumente).
     */
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

    /**
     * Prideda pareigos stulpelį į vartotojai lentelę.
     * Pareigos (pvz. "Kokybės inžinierius") rodomos PDF dokumentų parašo blokuose.
     * Jei pareigos neįvestos — naudojamas numatytasis tekstas "Kokybės inžinierius".
     */
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

    /**
     * Prideda įmonės rekvizitų stulpelius į uzsakymai lentelę.
     * Kai sukuriamas užsakymas, įmonės duomenys (pavadinimas, adresas, telefonas ir kt.)
     * įrašomi tiesiai į užsakymą — taip PDF dokumentas visada rodys teisingus
     * to meto rekvizitus, net jei vėliau įmonės duomenys pasikeis.
     */
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

    /**
     * Sukuria remember_tokens lentelę, jei jos dar nėra.
     * Šioje lentelėje saugomi "prisimink mane" žetonai — ilgalaikiai
     * prisijungimo rakteliai, leidžiantys vartotojui automatiškai prisijungti
     * po naršyklės uždarymo (be slaptažodžio įvedimo).
     */
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

    /**
     * Prideda defekto PDF stulpelius į pretenzijos lentelę.
     * Leidžia prie pretenzijos pridėti PDF dokumentą su defekto aprašymu
     * (defekto_pdf_pavadinimas — failo pavadinimas, defekto_pdf_turinys — turinys).
     */
    private function pridetiDefektoPdfStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pretenzijos' AND column_name = 'defekto_pdf_pavadinimas'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE pretenzijos ADD COLUMN IF NOT EXISTS defekto_pdf_pavadinimas VARCHAR(255), ADD COLUMN IF NOT EXISTS defekto_pdf_turinys BYTEA");
            }
        } catch (PDOException $e) {
        }
    }

    /**
     * Prideda qt_pretenzija_id stulpelį į pretenzijos lentelę.
     * Šis unikalus ID naudojamas sinchronizuojant pretenzijas su išorine
     * Tomo QMS sistema — leidžia žinoti kuris mūsų įrašas atitinka kurį ten.
     */
    private function pridetiQtPretenzijaIdStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pretenzijos' AND column_name = 'qt_pretenzija_id'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE pretenzijos ADD COLUMN qt_pretenzija_id INTEGER");
                $this->conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_pretenzijos_qt_id ON pretenzijos(qt_pretenzija_id) WHERE qt_pretenzija_id IS NOT NULL");
            }
        } catch (PDOException $e) {
        }
    }

    /**
     * Sukuria pretenzijos_failai lentelę, jei jos dar nėra.
     * Šioje lentelėje saugomi prie pretenzijos pridėti failai (PDF, .msg ir kt.).
     * Viena pretenzija gali turėti kelis failus. Jei pretenzija ištrintina —
     * visi jos failai ištrinami automatiškai (ON DELETE CASCADE).
     */
    private function sukurtiPretenzijoFailuLentele(): void {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS pretenzijos_failai (
                    id SERIAL PRIMARY KEY,
                    pretenzija_id INTEGER NOT NULL REFERENCES pretenzijos(id) ON DELETE CASCADE,
                    pavadinimas VARCHAR(500) NOT NULL,
                    tipas VARCHAR(255),
                    turinys BYTEA NOT NULL,
                    ikelta TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $this->conn->exec("CREATE INDEX IF NOT EXISTS idx_pretenzijos_failai_pid ON pretenzijos_failai(pretenzija_id)");
        } catch (PDOException $e) {
        }
    }

    /**
     * Prideda dielektriniai_issaugoti stulpelį į gaminiai lentelę.
     * Tai vėliavėlė (true/false), kuri nurodo ar dielektrinių bandymų protokolas
     * jau buvo sugeneruotas ir išsaugotas PDF formatu.
     */
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

    /**
     * Prideda papildomas_komentaras stulpelį į pretenzijos_email_history lentelę.
     * Leidžia prie laiško istorijos įrašo pridėti vidinį komentarą —
     * naudinga kai pokalbis tęsiamas ir reikia pažymėti papildomą informaciją.
     */
    private function pridetiPapildomoKomentaroStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pretenzijos_email_history' AND column_name = 'papildomas_komentaras'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE pretenzijos_email_history ADD COLUMN papildomas_komentaras TEXT");
            }
        } catch (PDOException $e) {
        }
    }

    /**
     * Pataiso komponentų tekstinių laukų tipą iš VARCHAR į TEXT.
     * VARCHAR turi ilgio apribojimą, TEXT — ne. Gamintojų kodai ir pavadinimai
     * gali būti ilgi, todėl juos geriau laikyti TEXT tipo lauke.
     */
    private function pataisytiKomponentuVarchar(): void {
        try {
            $this->conn->exec("ALTER TABLE komponentai ALTER COLUMN gamintojo_kodas TYPE TEXT");
        } catch (PDOException $e) {}
        try {
            $this->conn->exec("ALTER TABLE komponentai ALTER COLUMN gamintojas TYPE TEXT");
        } catch (PDOException $e) {}
    }

    /**
     * Prideda perziuros_token stulpelį į pretenzijos lentelę.
     * Tai unikalus saugus žetonas (atsitiktinis kodas), kuris leidžia
     * klientui peržiūrėti savo pretenzijos statusą be prisijungimo prie sistemos
     * (per specialią viešą nuorodą). Kiekvienai pretenzijai generuojamas skirtingas kodas.
     */
    private function pridetiPerziurosTokena(): void {
        try {
            $exists = $this->conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='pretenzijos' AND column_name='perziuros_token'")->fetchColumn();
            if (!$exists) {
                $this->conn->exec("ALTER TABLE pretenzijos ADD COLUMN perziuros_token VARCHAR(64) DEFAULT md5(random()::text || clock_timestamp()::text)");
            } else {
                $this->conn->exec("ALTER TABLE pretenzijos ALTER COLUMN perziuros_token SET DEFAULT md5(random()::text || clock_timestamp()::text)");
            }
            $this->conn->exec("UPDATE pretenzijos SET perziuros_token = md5(id::text || '-' || EXTRACT(EPOCH FROM COALESCE(sukurta, now()))::text || '-' || random()::text) WHERE perziuros_token IS NULL OR perziuros_token = ''");
            $this->conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS pretenzijos_perziuros_token_idx ON pretenzijos(perziuros_token)");
        } catch (PDOException $e) {}
    }

    /**
     * Prideda aktyvus stulpelį į vartotojai lentelę.
     * Leidžia administratoriui sustabdyti vartotojo prisijungimą
     * neištrindamas paskyros. Visi esami vartotojai lieka aktyvūs (true).
     */
    private function pridetiAktyvusStulpeli(): void {
        try {
            $exists = $this->conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='vartotojai' AND column_name='aktyvus'")->fetchColumn();
            if (!$exists) {
                $this->conn->exec("ALTER TABLE vartotojai ADD COLUMN aktyvus BOOLEAN NOT NULL DEFAULT true");
            }
        } catch (PDOException $e) {}
    }

    /**
     * Normalizuoja rolių pavadinimus duomenų bazėje.
     * Production DB gali turėti senas angliškas reikšmes ("admin", "user")
     * arba lietuviškas su didžiąja raide ("Vartotojas", "Skaitytojas").
     * Šis metodas vieną kartą sutvarkys visas reikšmes į vienodą formatą.
     */
    private function normalizuotiRolesDB(): void {
        try {
            $this->conn->exec("UPDATE vartotojai SET role = 'administratorius' WHERE role IN ('admin', 'administrator', 'Administratorius')");
            $this->conn->exec("UPDATE vartotojai SET role = 'vartotojas'       WHERE role IN ('user', 'Vartotojas')");
            $this->conn->exec("UPDATE vartotojai SET role = 'skaitytojas'      WHERE role IN ('Skaitytojas', 'reader')");
        } catch (PDOException $e) {}
    }
}
