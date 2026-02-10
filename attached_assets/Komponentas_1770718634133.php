<?php 
// klases/Komponentas.php

class Komponentas {
    public $id;
    public $kodas;
    public $kiekis;
    public $aprasymas;
    public $gamintojas;
    public $parinkta_projektui;

    private $visi_kodai = [];
    private $visi_gamintojai = [];

    public function __construct($duomenys, $visi_kodai = [], $visi_gamintojai = []) {
        $this->id = $duomenys['id'] ?? null;
        $this->kodas = $duomenys['kodas'] ?? '';
        $this->kiekis = $duomenys['kiekis'] ?? '';
        $this->aprasymas = $duomenys['aprasymas'] ?? '';
        $this->gamintojas = $duomenys['gamintojas'] ?? '';
        $this->parinkta_projektui = $duomenys['parinkta_projektui'] ?? false;
        $this->visi_kodai = $visi_kodai;
        $this->visi_gamintojai = $visi_gamintojai;
    }

    public function render(): string {
        // Fono klasė jei pažymėta
        $tr_class = $this->parinkta_projektui ? "table-success" : "";

        $form = "<tr class='{$tr_class}'>";

        // Eilės numeris su hidden input
        $form .= "<td>
                    {$this->id}
                    <input type='hidden' name='eile_id[]' value='{$this->id}'>
                  </td>";

        // Kodas (dropdown + naujas įrašas)
        $form .= "<td><select name='kodas[]' class='form-select'>";
        foreach ($this->visi_kodai as $kodas) {
            $selected = ($kodas == $this->kodas) ? "selected" : "";
            $form .= "<option value='".htmlspecialchars($kodas)."' $selected>".htmlspecialchars($kodas)."</option>";
        }
        $form .= "</select>";
        $form .= "<input type='text' name='kodas_naujas[]' class='form-control mt-1' placeholder='Naujas kodas'></td>";

        // Kiekis
        $form .= "<td><input type='number' name='kiekis[]' class='form-control' value='".htmlspecialchars($this->kiekis)."'></td>";

        // Aprašymas
        $form .= "<td><input type='text' name='aprasymas[]' class='form-control' value='".htmlspecialchars($this->aprasymas)."'></td>";

        // Gamintojas (dropdown + naujas įrašas)
        $form .= "<td><select name='gamintojas[]' class='form-select'>";
        foreach ($this->visi_gamintojai as $g) {
            $selected = ($g == $this->gamintojas) ? "selected" : "";
            $form .= "<option value='".htmlspecialchars($g)."' $selected>".htmlspecialchars($g)."</option>";
        }
        $form .= "</select>";
        $form .= "<input type='text' name='gamintojas_naujas[]' class='form-control mt-1' placeholder='Naujas gamintojas'>";

        // ✔ Žyma jei parinkta pagal projektą
        if ($this->parinkta_projektui) {
            $form .= "<div class='text-success mt-1 ms-1' style='font-size: 0.9em;'>✔ Parinkta pagal projektą</div>";
        }

        $form .= "</td>";

        // Išsaugojimo mygtukas
        $form .= "<td class='text-center'>
                    <button type='submit' name='saugoti[]' value='{$this->id}' class='btn btn-outline-success'>💾</button>
                  </td>";

        $form .= "</tr>";
        return $form;
    }
}
