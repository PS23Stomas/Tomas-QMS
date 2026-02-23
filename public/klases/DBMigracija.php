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

    /** Prideda vidutinės įtampos stulpelius prie mt_dielektriniai_bandymai lentelės */
    private function pridetiDielektriniuVidutinesStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'mt_dielektriniai_bandymai' AND column_name = 'tipas'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE mt_dielektriniai_bandymai ADD COLUMN tipas VARCHAR(20) DEFAULT 'mazos_itampos'");
                $this->conn->exec("ALTER TABLE mt_dielektriniai_bandymai ADD COLUMN grandines_pavadinimas TEXT");
                $this->conn->exec("ALTER TABLE mt_dielektriniai_bandymai ADD COLUMN grandines_itampa VARCHAR(50)");
                $this->conn->exec("ALTER TABLE mt_dielektriniai_bandymai ADD COLUMN bandymo_schema VARCHAR(255)");
                $this->conn->exec("ALTER TABLE mt_dielektriniai_bandymai ADD COLUMN bandymo_itampa_kv VARCHAR(50)");
                $this->conn->exec("ALTER TABLE mt_dielektriniai_bandymai ADD COLUMN bandymo_trukme VARCHAR(50)");
            }
        } catch (PDOException $e) {
        }
    }

    /** Sukuria funkcinių bandymų šablono lentelę su numatytais reikalavimais */
    private function sukurtiFunkciniuSablona(): void {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS mt_funkciniu_sablonas (
                    id SERIAL PRIMARY KEY,
                    eil_nr INTEGER NOT NULL,
                    pavadinimas TEXT NOT NULL
                )
            ");
            $stmt = $this->conn->query("SELECT COUNT(*) FROM mt_funkciniu_sablonas");
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
                $ins = $this->conn->prepare("INSERT INTO mt_funkciniu_sablonas (eil_nr, pavadinimas) VALUES (?, ?)");
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

    /** Prideda defekto nuotraukų stulpelius į mt_funkciniai_bandymai lentelę */
    private function pridetiDefektoNuotraukuStulpelius(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'mt_funkciniai_bandymai' AND column_name = 'defekto_nuotrauka'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE mt_funkciniai_bandymai ADD COLUMN defekto_nuotrauka BYTEA");
                $this->conn->exec("ALTER TABLE mt_funkciniai_bandymai ADD COLUMN defekto_nuotraukos_pavadinimas VARCHAR(255)");
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

    /** Prideda pataisyta stulpelį į mt_funkciniai_bandymai lentelę */
    private function pridetiPataisytaStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'mt_funkciniai_bandymai' AND column_name = 'pataisyta'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE mt_funkciniai_bandymai ADD COLUMN pataisyta TEXT DEFAULT ''");
            }
        } catch (PDOException $e) {
        }
    }

    private function pridetiIssiustaKamStulpeli(): void {
        try {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'mt_funkciniai_bandymai' AND column_name = 'issiusta_kam'";
            $stmt = $this->conn->query($sql);
            if (!$stmt->fetchColumn()) {
                $this->conn->exec("ALTER TABLE mt_funkciniai_bandymai ADD COLUMN issiusta_kam TEXT DEFAULT ''");
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
            ['lentele' => 'mt_paso_teksto_korekcijos', 'laukas' => 'tekstas'],
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
}
