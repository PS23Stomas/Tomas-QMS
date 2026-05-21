<?php
/**
 * MT paso komponentų susiejimo klasė
 *
 * MT pasas — tai oficialus dokumentas (kaip gimimo liudijimas gaminiui),
 * kuriame išvardinti visi MT transformatorinėje sumontuoti komponentai.
 *
 * Šis dokumentas turi konkrečias sekcijas (1.1, 1.2, 2.1 ir t.t.),
 * ir kiekviena sekcija atitinka konkretų komponentą iš komponentų sąrašo.
 *
 * Ši klasė žino KURIAM paso punktui priklauso KURIS komponentas
 * (pagal eilės numerį duomenų bazėje) ir grąžina jo duomenis paso generavimui.
 *
 * Naudojama generuojant MT paso PDF dokumentą.
 */
class MTPasasKomponentai {
    /** Duomenų bazės ryšys */
    private $conn;

    /** Gaminio ID, kurio komponentus tvarkome */
    private $gaminio_id;

    /** Visi gaminio komponentai, užkrauti iš duomenų bazės */
    private $komponentai = [];

    /**
     * Sukuria objektą ir iš karto užkrauna gaminio komponentus iš duomenų bazės.
     *
     * @param PDO $conn      Duomenų bazės ryšys
     * @param int $gaminio_id Gaminio, kurio paso komponentus reikia, ID
     */
    public function __construct($conn, $gaminio_id) {
        $this->conn = $conn;
        $this->gaminio_id = $gaminio_id;
        $this->uzkrauti();
    }

    /**
     * Užkrauna visus gaminio komponentus iš duomenų bazės,
     * surikiuotus pagal eilės numerį (nuo mažiausio).
     */
    private function uzkrauti() {
        $stmt = $this->conn->prepare("SELECT * FROM komponentai WHERE gaminio_id = ? ORDER BY eiles_numeris");
        $stmt->execute([$this->gaminio_id]);
        $this->komponentai = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suranda komponentą pagal jo eilės numerį.
     * Jei tokio eilės numerio nėra — grąžina null.
     *
     * @param int $eiles_nr Eilės numeris (pvz. 14 = 10kV linijos kirtiklis)
     * @return array|null   Komponento duomenys arba null jei nerastas
     */
    private function gautiPagalEilesNr($eiles_nr) {
        foreach ($this->komponentai as $k) {
            if ((int)$k['eiles_numeris'] === $eiles_nr) {
                return $k;
            }
        }
        return null;
    }

    /**
     * Suformatuoja komponento duomenis į standartinį masyvą.
     * Jei komponentas nerastas — grąžina tuščius laukus (paso eilutė bus tuščia).
     *
     * @param array|null $komp Komponento duomenys arba null
     * @return array           ['gamintojo_kodas', 'gamintojas', 'kiekis', 'aprasymas']
     */
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

    /**
     * Paso 1 skyrius: Aukštosios įtampos įranga
     */

    /** Paso punktas 1.1 — 10kV linijos kirtiklis (komponentas nr. 14).
     *  Grąžina eilučių masyvą — po vieną eilutę kiekvienam kirtiklio vienetui. */
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

    /** Paso punktas 1.2 — sekcijinis 0,4kV kirtiklis (komponentas nr. 13) */
    public function punktas1_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(13));
    }

    /** Paso punktas 1.3 — įvadinis 0,4kV kirtiklis (komponentas nr. 11) */
    public function punktas1_3() {
        return $this->formatuoti($this->gautiPagalEilesNr(11));
    }

    /** Paso punktas 1.4 — saugiklių skydelis (komponentas nr. 12) */
    public function punktas1_4() {
        return $this->formatuoti($this->gautiPagalEilesNr(12));
    }

    /** Paso punktas 1.5 — galios transformatorius (komponentas nr. 16) */
    public function punktas1_5() {
        return $this->formatuoti($this->gautiPagalEilesNr(16));
    }

    /** Paso punktas 1.6 — įžeminimo įrenginys (komponentas nr. 15) */
    public function punktas1_6() {
        return $this->formatuoti($this->gautiPagalEilesNr(15));
    }

    /**
     * Paso 2 skyrius: Apskaitos įranga
     */

    /** Paso punktas 2.1 — apskaitos skydelis (komponentas nr. 17) */
    public function punktas2_1() {
        return $this->formatuoti($this->gautiPagalEilesNr(17));
    }

    /** Paso punktas 2.2 — apskaitos skaitiklis (komponentas nr. 18) */
    public function punktas2_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(18));
    }

    /**
     * Paso 3 skyrius: Aukštosios įtampos kamera
     */

    /** Paso punktas 3.1 — viršįtampių ribotuvai (komponentas nr. 2) */
    public function punktas3_1() {
        return $this->formatuoti($this->gautiPagalEilesNr(2));
    }

    /** Paso punktas 3.2 — izoliatoriai (komponentas nr. 1) */
    public function punktas3_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(1));
    }

    /** Paso punktas 3.3 — srovės transformatoriai (komponentas nr. 6) */
    public function punktas3_3() {
        return $this->formatuoti($this->gautiPagalEilesNr(6));
    }

    /** Paso punktas 3.4 — įtampos transformatoriai (komponentas nr. 5) */
    public function punktas3_4() {
        return $this->formatuoti($this->gautiPagalEilesNr(5));
    }

    /** Paso punktas 3.9 — kabelinės galvutės (komponentas nr. 4) */
    public function punktas3_9() {
        return $this->formatuoti($this->gautiPagalEilesNr(4));
    }

    /** Paso punktas 3.10 — sekcinio saugiklio įdėklas (komponentas nr. 3) */
    public function punktas3_10() {
        return $this->formatuoti($this->gautiPagalEilesNr(3));
    }

    /** Paso punktas 3.11 — komercinė apskaita: srovės transformatorius (komponentas nr. 9) */
    public function punktas3_11() {
        return $this->formatuoti($this->gautiPagalEilesNr(9));
    }

    /** Paso punktas 3.12 — kontrolinė apskaita: srovės transformatorius (komponentas nr. 10) */
    public function punktas3_12() {
        return $this->formatuoti($this->gautiPagalEilesNr(10));
    }
}
