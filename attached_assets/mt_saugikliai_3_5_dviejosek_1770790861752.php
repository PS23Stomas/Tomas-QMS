<tr>  
    <td style="width: 5%; ">3.5</td>
    <td colspan="2">
        <form action="issaugoti_mt_saugiklius.php" method="post">
            <input type="hidden" name="gaminio_id" value="<?= intval($gaminio_id) ?>">
            <input type="hidden" name="sekcija" value="3.5">
            <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
            <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">

            <?php
            $standartines_pozicijos = array_merge(range(101, 106), range(301, 304));
            $reiksmes = [];
            foreach ($mt_saugikliai as $eilute) {
                $poz_n = $eilute['pozicijos_numeris'];
                $reiksmes[$poz_n] = [
                    'pozicija' => $eilute['pozicija'],
                    'gabaritas' => $eilute['gabaritas'],
                    'nominalas' => $eilute['nominalas']
                ];
            }
            ?>

            <table class="mt-lentele">
                <tr>
                    <td style="width: 25%; vertical-align: middle; text-align: left;">
                        Š1-0,4 (ir Š3-0,4 pagal schemą) sekcijos komplektuojamų saugiklių–lydžiųjų įdėklų gabaritas, nominalas:
                    </td>
                    <td style="width: 75%;">
                        <table border="1" class="vienodi-stulpeliai">
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td>
                                        <input type="hidden" name="pozicijos_numeris[]" value="<?= $poz_n ?>">
                                        <input type="text" name="pozicijos[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['pozicija'] ?? '') ?>" placeholder="Pozicija" style="width: 95%; text-align: center;">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td>
                                        <input type="text" name="gabaritai[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['gabaritas'] ?? '') ?>" placeholder="Gabaritas" style="width: 100%; text-align: center;">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td>
                                        <input type="text" name="nominalai[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['nominalas'] ?? '') ?>" placeholder="Nominalas" style="width: 100%; text-align: center;">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div style="text-align: right; margin-top: 5px;">
                <button type="submit" class="btn btn-primary">Išsaugoti 3.5 duomenis</button>
            </div>
        </form>
    </td>
</tr>
