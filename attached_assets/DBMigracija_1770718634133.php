<?php
/**
 * Duomenų bazės migracijos klasė
 * Automatiškai patikrina ir pataiso per mažus varchar laukus
 */
class DBMigracija {
    private $conn;
    
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }
    
    /**
     * Paleidžia visas reikalingas migracijas
     */
    public function paleisti(): void {
        $this->pataisytiVarcharLaukus();
    }
    
    /**
     * Patikrina ir pakeičia varchar(50) laukus į TEXT
     */
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
    
    /**
     * Pakeičia konkretų lauką į TEXT tipą jei jis nėra TEXT
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
            // Ignoruojame klaidas jei lentelė/laukas neegzistuoja
        }
    }
}
