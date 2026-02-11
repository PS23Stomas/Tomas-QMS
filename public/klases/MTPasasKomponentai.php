<?php
/**
 * MT paso komponentų susiejimo klasė - eilės numerių priskyrimas paso sekcijoms
 */
class MTPasasKomponentai {
    private $conn;
    private $gaminio_id;
    private $komponentai = [];

    public function __construct($conn, $gaminio_id) {
        $this->conn = $conn;
        $this->gaminio_id = $gaminio_id;
        $this->uzkrauti();
    }

    /** Užkrauna visus gaminio komponentus iš duomenų bazės, surikiuotus pagal eilės numerį */
    private function uzkrauti() {
        $stmt = $this->conn->prepare("SELECT * FROM mt_komponentai WHERE gaminio_id = ? ORDER BY eiles_numeris");
        $stmt->execute([$this->gaminio_id]);
        $this->komponentai = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Suranda ir grąžina komponentą pagal eilės numerį */
    private function gautiPagalEilesNr($eiles_nr) {
        foreach ($this->komponentai as $k) {
            if ((int)$k['eiles_numeris'] === $eiles_nr) {
                return $k;
            }
        }
        return null;
    }

    /** Suformatuoja komponento duomenis į standartinį masyvą (kodas, gamintojas, kiekis, aprašymas) */
    private function formatuoti($komp) {
        if (!$komp) {
            return ['gamintojo_kodas' => '', 'gamintojas' => '', 'kiekis' => '', 'aprasymas' => ''];
        }
        return [
            'gamintojo_kodas' => $komp['gamintojo_kodas'] ?? '',
            'gamintojas' => $komp['gamintojas'] ?? '',
            'kiekis' => $komp['kiekis'] ?? '',
            'aprasymas' => $komp['aprasymas'] ?? ''
        ];
    }

    /** Paso sekcija 1.1 - 10kV linijos kirtiklis (eilės nr. 14), grąžina eilutes pagal kiekį */
    public function punktas1_1() {
        $komp = $this->gautiPagalEilesNr(14);
        if (!$komp) return [];
        $kiekis = (int)($komp['kiekis'] ?? 1);
        $eilutes = [];
        for ($i = 0; $i < max($kiekis, 1); $i++) {
            $eilutes[] = [
                'linija' => '',
                'kodas' => $komp['gamintojo_kodas'] ?? '',
                'gamintojas' => $komp['gamintojas'] ?? ''
            ];
        }
        return $eilutes;
    }

    /** Paso sekcija 1.2 - sekcijinis 0,4kV kirtiklis (eilės nr. 13) */
    public function punktas1_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(13));
    }

    /** Paso sekcija 1.3 - įvadinis 0,4kV kirtiklis (eilės nr. 11) */
    public function punktas1_3() {
        return $this->formatuoti($this->gautiPagalEilesNr(11));
    }

    /** Paso sekcija 1.4 - saugiklių skydelis (eilės nr. 12) */
    public function punktas1_4() {
        return $this->formatuoti($this->gautiPagalEilesNr(12));
    }

    /** Paso sekcija 1.5 - galios transformatorius (eilės nr. 16) */
    public function punktas1_5() {
        return $this->formatuoti($this->gautiPagalEilesNr(16));
    }

    /** Paso sekcija 1.6 - įžeminimo įrenginys (eilės nr. 15) */
    public function punktas1_6() {
        return $this->formatuoti($this->gautiPagalEilesNr(15));
    }

    /** Paso sekcija 2.1 - apskaitos skydelis (eilės nr. 17) */
    public function punktas2_1() {
        return $this->formatuoti($this->gautiPagalEilesNr(17));
    }

    /** Paso sekcija 2.2 - apskaitos skaitiklis (eilės nr. 18) */
    public function punktas2_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(18));
    }

    /** Paso sekcija 3.1 - viršįtampių ribotuvai (eilės nr. 2) */
    public function punktas3_1() {
        return $this->formatuoti($this->gautiPagalEilesNr(2));
    }

    /** Paso sekcija 3.2 - izoliatoriai (eilės nr. 1) */
    public function punktas3_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(1));
    }

    /** Paso sekcija 3.3 - srovės transformatoriai (eilės nr. 6) */
    public function punktas3_3() {
        return $this->formatuoti($this->gautiPagalEilesNr(6));
    }

    /** Paso sekcija 3.4 - įtampos transformatoriai (eilės nr. 5) */
    public function punktas3_4() {
        return $this->formatuoti($this->gautiPagalEilesNr(5));
    }

    /** Paso sekcija 3.9 - kabelinės galvutės (eilės nr. 4) */
    public function punktas3_9() {
        return $this->formatuoti($this->gautiPagalEilesNr(4));
    }

    /** Paso sekcija 3.10 - sekcinio saugiklio įdėklas (eilės nr. 3) */
    public function punktas3_10() {
        return $this->formatuoti($this->gautiPagalEilesNr(3));
    }

    /** Paso sekcija 3.11 - komercinė apskaita, srovės transformatorius (eilės nr. 9) */
    public function punktas3_11() {
        return $this->formatuoti($this->gautiPagalEilesNr(9));
    }

    /** Paso sekcija 3.12 - kontrolinė apskaita, srovės transformatorius (eilės nr. 10) */
    public function punktas3_12() {
        return $this->formatuoti($this->gautiPagalEilesNr(10));
    }
}
