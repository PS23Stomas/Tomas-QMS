<?php
class MTPasasKomponentai {
    private $conn;
    private $gaminio_id;
    private $komponentai = [];

    public function __construct($conn, $gaminio_id) {
        $this->conn = $conn;
        $this->gaminio_id = $gaminio_id;
        $this->uzkrauti();
    }

    private function uzkrauti() {
        $stmt = $this->conn->prepare("SELECT * FROM mt_komponentai WHERE gaminio_id = ? ORDER BY eiles_numeris");
        $stmt->execute([$this->gaminio_id]);
        $this->komponentai = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function gautiPagalEilesNr($eiles_nr) {
        foreach ($this->komponentai as $k) {
            if ((int)$k['eiles_numeris'] === $eiles_nr) {
                return $k;
            }
        }
        return null;
    }

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

    public function punktas1_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(13));
    }

    public function punktas1_3() {
        return $this->formatuoti($this->gautiPagalEilesNr(11));
    }

    public function punktas1_4() {
        return $this->formatuoti($this->gautiPagalEilesNr(12));
    }

    public function punktas1_5() {
        return $this->formatuoti($this->gautiPagalEilesNr(16));
    }

    public function punktas1_6() {
        return $this->formatuoti($this->gautiPagalEilesNr(15));
    }

    public function punktas2_1() {
        return $this->formatuoti($this->gautiPagalEilesNr(17));
    }

    public function punktas2_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(18));
    }

    public function punktas3_1() {
        return $this->formatuoti($this->gautiPagalEilesNr(2));
    }

    public function punktas3_2() {
        return $this->formatuoti($this->gautiPagalEilesNr(1));
    }

    public function punktas3_3() {
        return $this->formatuoti($this->gautiPagalEilesNr(6));
    }

    public function punktas3_4() {
        return $this->formatuoti($this->gautiPagalEilesNr(5));
    }

    public function punktas3_9() {
        return $this->formatuoti($this->gautiPagalEilesNr(4));
    }

    public function punktas3_10() {
        return $this->formatuoti($this->gautiPagalEilesNr(9));
    }

    public function punktas3_11() {
        return $this->formatuoti($this->gautiPagalEilesNr(10));
    }
}
