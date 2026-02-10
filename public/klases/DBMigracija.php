<?php
class DBMigracija {
    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function paleisti(): void {
        $this->sukurtiTrukstamasLenteles();
        $this->pataisytiVarcharLaukus();
    }

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
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS antriniu_grandiniu_bandymai (
                    id SERIAL PRIMARY KEY,
                    gaminys_id INTEGER NOT NULL,
                    eiles_nr INTEGER,
                    grandines_pavadinimas TEXT,
                    grandines_itampa VARCHAR(50),
                    bandymo_schema VARCHAR(255),
                    bandymo_itampa_kV VARCHAR(50),
                    bandymo_trukme VARCHAR(50),
                    isvada TEXT
                )
            ");
        } catch (PDOException $e) {
        }
    }

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
