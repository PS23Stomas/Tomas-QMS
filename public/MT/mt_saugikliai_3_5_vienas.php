<tr>
    <td style="width: 5%;">3.5</td>
    <td colspan="2">
        <form action="issaugoti_mt_saugiklius.php" method="post">
            <input type="hidden" name="gaminio_id" value="<?= intval($gaminio_id) ?>">
            <input type="hidden" name="sekcija" value="3.5">
            <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
            <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
            <input type="hidden" name="gaminio_pavadinimas" value="<?= htmlspecialchars($gaminio_pavadinimas) ?>">
            <input type="hidden" name="gaminio_numeris" value="<?= htmlspecialchars($gaminio_numeris) ?>">
            <input type="hidden" name="uzsakymo_id" value="<?= htmlspecialchars($uzsakymo_id) ?>">

            <?php
            $standartines_pozicijos = range(1, 15);
            $reiksmes = [];
            foreach ($mt_saugikliai_35 as $eilute) {
                $poz_n = $eilute['pozicijos_numeris'];
                $reiksmes[$poz_n] = [
                    'pozicija' => $eilute['pozicija'],
                    'gabaritas' => $eilute['gabaritas'],
                    'nominalas' => $eilute['nominalas']
                ];
            }
            ?>

            <table border="1" style="width:100%; border-collapse: collapse;">
                <tr>
                    <td style="width:25%; padding:5px; vertical-align: middle; text-align: left;">
                        SI-0,4 sekcijos komplektuojamu saugikliu-lydzujuju ideklu gabaritas, nominalas:
                    </td>
                    <td style="width:75%;">
                        <table border="1" style="width:100%; table-layout: fixed; border-collapse: collapse;">
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td style="text-align:center; font-size:11px; padding:2px;">
                                        <input type="hidden" name="pozicijos_numeris[]" value="<?= $poz_n ?>">
                                        <input type="text" name="pozicijos[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['pozicija'] ?? '') ?>" style="width:90%; text-align:center; font-size:11px;" placeholder="<?= $poz_n ?>" data-testid="input-poz-35-<?= $poz_n ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td style="text-align:center; padding:2px;">
                                        <input type="text" name="gabaritai[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['gabaritas'] ?? '') ?>" style="width:90%; text-align:center; font-size:11px;" placeholder="Gab." data-testid="input-gab-35-<?= $poz_n ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td style="text-align:center; padding:2px;">
                                        <input type="text" name="nominalai[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['nominalas'] ?? '') ?>" style="width:90%; text-align:center; font-size:11px;" placeholder="Nom." data-testid="input-nom-35-<?= $poz_n ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div style="text-align: right; margin-top: 5px;">
                <button type="submit" class="btn btn-primary btn-sm" data-testid="button-save-35">Issaugoti 3.5 duomenis</button>
            </div>
        </form>
    </td>
</tr>
