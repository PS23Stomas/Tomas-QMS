<?php
class Komponentas {
    private int $id;
    private string $kodas;
    private int $kiekis;
    private string $aprasymas;
    private string $gamintojas;
    private bool $parinkta;
    private bool $irasyta;
    private array $kodai;
    private array $visiGamintojai;

    public function __construct(array $data, array $kodai = [], array $visiGamintojai = []) {
        $this->id = (int)($data['id'] ?? 0);
        $this->kodas = $data['kodas'] ?? '';
        $this->kiekis = (int)($data['kiekis'] ?? 0);
        $this->aprasymas = $data['aprasymas'] ?? '';
        $this->gamintojas = $data['gamintojas'] ?? '';
        $this->parinkta = (bool)($data['parinkta_projektui'] ?? false);
        $this->irasyta = (bool)($data['irasyta'] ?? false);
        $this->kodai = $kodai;
        $this->visiGamintojai = $visiGamintojai;
    }

    public function isParinkta(): bool {
        return $this->parinkta;
    }

    public function render(): string {
        $id = $this->id;
        $kodas = htmlspecialchars($this->kodas);
        $kiekis = $this->kiekis;
        $aprasymas = htmlspecialchars($this->aprasymas);
        $gamintojas = htmlspecialchars($this->gamintojas);
        $cellBg = $this->irasyta ? "background-color: #cbd5e1;" : "";

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
            <td style='padding: 6px 4px; vertical-align: top; text-align: center; font-weight: 600; font-size: 13px; {$cellBg}'>
                {$id}
                <input type='hidden' name='eile_id[]' value='{$id}'>
            </td>
            <td style='padding: 5px 4px; vertical-align: top; {$cellBg}'>
                <select class='form-select form-select-sm' name='kodas[]' style='font-size: 12px;'>{$kodaiOptions}</select>
                <input type='text' class='form-control form-control-sm mt-1' name='kodas_naujas[]' placeholder='Naujas kodas' style='font-size: 12px;'>
            </td>
            <td style='padding: 5px 4px; vertical-align: top; {$cellBg}'>
                <input type='number' class='form-control form-control-sm' name='kiekis[]' value='{$kiekis}' style='font-size: 12px;'>
            </td>
            <td style='padding: 5px 4px; vertical-align: top; {$cellBg}'>
                <input type='text' class='form-control form-control-sm' name='aprasymas[]' value='{$aprasymas}' style='font-size: 12px;'>
            </td>
            <td style='padding: 5px 4px; vertical-align: top; {$cellBg}'>
                <select class='form-select form-select-sm' name='gamintojas[]' style='font-size: 12px;'>{$gamintojaiOptions}</select>
                <input type='text' class='form-control form-control-sm mt-1' name='gamintojas_naujas[]' placeholder='Naujas gamintojas' style='font-size: 12px;'>
            </td>
            <td style='padding: 5px 2px; vertical-align: middle; text-align: center; {$cellBg}'>
                <button type='submit' name='saugoti[]' value='{$id}' class='btn btn-outline-secondary btn-sm' title='Išsaugoti eilutę' style='padding: 3px 6px;'>
                    <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z'/><polyline points='17 21 17 13 7 13 7 21'/><polyline points='7 3 7 8 15 8'/></svg>
                </button>
            </td>
        </tr>";
    }
}
