<?php
$sekcija_36 = '3.6';
$stmt_36 = $conn->prepare("SELECT * FROM mt_saugikliu_ideklai WHERE gaminio_id = :gaminio_id AND sekcija = :sekcija ORDER BY pozicijos_numeris ASC");
$stmt_36->execute([':gaminio_id' => $gaminio_id, ':sekcija' => $sekcija_36]);
$mt_saugikliai_36 = $stmt_36->fetchAll(PDO::FETCH_ASSOC);

$reiksmes_36 = [];
foreach ($mt_saugikliai_36 as $eilute) {
    $poz_n = $eilute['pozicijos_numeris'];
    $reiksmes_36[$poz_n] = [
        'pozicija' => $eilute['pozicija'],
        'gabaritas' => $eilute['gabaritas'],
        'nominalas' => $eilute['nominalas']
    ];
}

$standartines_pozicijos_36 = array_merge(range(201, 206), range(401, 404));
?>
<tr>
    <td style="width: 5%;">3.6</td>
    <td colspan="2">
        <form action="issaugoti_mt_saugiklius.php" method="post">
            <input type="hidden" name="gaminio_id" value="<?= intval($gaminio_id) ?>">
            <input type="hidden" name="sekcija" value="3.6">
            <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
            <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
            <input type="hidden" name="gaminio_pavadinimas" value="<?= htmlspecialchars($gaminio_pavadinimas) ?>">
            <input type="hidden" name="gaminio_numeris" value="<?= htmlspecialchars($gaminio_numeris) ?>">
            <input type="hidden" name="uzsakymo_id" value="<?= htmlspecialchars($uzsakymo_id) ?>">

            <table border="1" style="width:100%; border-collapse: collapse;">
                <tr>
                    <td style="width:25%; vertical-align: middle; padding:5px; text-align: left;">
                        S2-0,4 (ir S4-0,4 pagal schema) sekcijos komplektuojamu saugikliu-lydzujuju ideklu gabaritas, nominalas:
                    </td>
                    <td style="width:75%;">
                        <table border="1" style="width:100%; table-layout: fixed; border-collapse: collapse;">
                            <tr>
                                <?php foreach ($standartines_pozicijos_36 as $poz_n): ?>
                                    <td style="text-align:center; font-size:11px; padding:2px;">
                                        <input type="hidden" name="pozicijos_numeris[]" value="<?= $poz_n ?>">
                                        <input type="text" name="pozicijos[]" value="<?= htmlspecialchars($reiksmes_36[$poz_n]['pozicija'] ?? '') ?>" placeholder="<?= $poz_n ?>" style="width:90%; text-align:center; font-size:11px;" data-testid="input-poz-36-<?= $poz_n ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos_36 as $poz_n): ?>
                                    <td style="text-align:center; padding:2px;">
                                        <input type="text" name="gabaritai[]" value="<?= htmlspecialchars($reiksmes_36[$poz_n]['gabaritas'] ?? '') ?>" placeholder="Gab." style="width:90%; text-align:center; font-size:11px;" data-testid="input-gab-36-<?= $poz_n ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos_36 as $poz_n): ?>
                                    <td style="text-align:center; padding:2px;">
                                        <input type="text" name="nominalai[]" value="<?= htmlspecialchars($reiksmes_36[$poz_n]['nominalas'] ?? '') ?>" placeholder="Nom." style="width:90%; text-align:center; font-size:11px;" data-testid="input-nom-36-<?= $poz_n ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div style="text-align: right; margin-top: 5px;">
                <button type="submit" class="btn btn-primary btn-sm" data-testid="button-save-36">Issaugoti 3.6 duomenis</button>
            </div>
        </form>
    </td>
</tr>
