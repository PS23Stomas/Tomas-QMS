<?php
class Komponentas {
    private int $id;
    private string $kodas;
    private int $kiekis;
    private string $aprasymas;
    private string $gamintojas;
    private bool $parinkta;
    private array $kodai;
    private array $visiGamintojai;

    public function __construct(array $data, array $kodai = [], array $visiGamintojai = []) {
        $this->id = (int)($data['id'] ?? 0);
        $this->kodas = $data['kodas'] ?? '';
        $this->kiekis = (int)($data['kiekis'] ?? 0);
        $this->aprasymas = $data['aprasymas'] ?? '';
        $this->gamintojas = $data['gamintojas'] ?? '';
        $this->parinkta = (bool)($data['parinkta_projektui'] ?? false);
        $this->kodai = $kodai;
        $this->visiGamintojai = $visiGamintojai;
    }

    public function render(): string {
        $id = $this->id;
        $kodas = htmlspecialchars($this->kodas);
        $kiekis = $this->kiekis;
        $aprasymas = htmlspecialchars($this->aprasymas);
        $gamintojas = htmlspecialchars($this->gamintojas);

        $kodaiOptions = '<option value="">Pasirinkite arba įveskite</option>';
        foreach ($this->kodai as $k) {
            $kEsc = htmlspecialchars($k);
            $sel = ($k === $this->kodas) ? ' selected' : '';
            $kodaiOptions .= "<option value=\"{$kEsc}\"{$sel}>{$kEsc}</option>";
        }
        if (!empty($this->kodas) && !in_array($this->kodas, $this->kodai)) {
            $kodaiOptions .= "<option value=\"{$kodas}\" selected>{$kodas}</option>";
        }

        $gamintojaiOptions = '<option value="">Pasirinkite arba įveskite</option>';
        foreach ($this->visiGamintojai as $g) {
            $gEsc = htmlspecialchars($g);
            $sel = ($g === $this->gamintojas) ? ' selected' : '';
            $gamintojaiOptions .= "<option value=\"{$gEsc}\"{$sel}>{$gEsc}</option>";
        }
        if (!empty($this->gamintojas) && !in_array($this->gamintojas, $this->visiGamintojai)) {
            $gEsc = htmlspecialchars($this->gamintojas);
            $gamintojaiOptions .= "<option value=\"{$gEsc}\" selected>{$gEsc}</option>";
        }

        return "
        <tr>
            <td><input type='text' class='form-control' name='eile_id[]' value='{$id}' readonly></td>
            <td>
                <select class='form-select' name='kodas[]'>{$kodaiOptions}</select>
                <input type='text' class='form-control mt-1' name='kodas_naujas[]' placeholder='Naujas kodas'>
            </td>
            <td><input type='number' class='form-control' name='kiekis[]' value='{$kiekis}'></td>
            <td><input type='text' class='form-control' name='aprasymas[]' value='{$aprasymas}'></td>
            <td>
                <select class='form-select' name='gamintojas[]'>{$gamintojaiOptions}</select>
                <input type='text' class='form-control mt-1' name='gamintojas_naujas[]' placeholder='Naujas gamintojas'>
            </td>
        </tr>";
    }
}
