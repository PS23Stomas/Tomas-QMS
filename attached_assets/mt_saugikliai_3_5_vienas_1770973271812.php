<tr> 
     <td style="width: 5%;  ">3.5</td>

    <td colspan="2">
        <form action="issaugoti_mt_saugiklius.php" method="post">
            <input type="hidden" name="gaminio_id" value="<?= intval($gaminio_id) ?>">
            <input type="hidden" name="sekcija" value="3.5">
            <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
            <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">

            <?php
            $standartines_pozicijos = array_merge(range(1, 15));
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

            <table border="1">
                <tr>
                    <td style="width:30%; padding:5px; ">
                        ŠĮ-0,4 sekcijos komplektuojamų saugiklių–lydžiųjų įdėklų gabaritas, nominalas:
                    </td>
                    <td style="width:70%;">
                        <table border="1" class="vienodi-stulpeliai">
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td>
                                        <input type="hidden" name="pozicijos_numeris[]" value="<?= $poz_n ?>">
                                        <input type="text" name="pozicijos[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['pozicija'] ?? '') ?>" style="width:90%; text-align:center;" placeholder="Pozicija">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td>
                                        <input type="text" name="gabaritai[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['gabaritas'] ?? '') ?>" style="width:90%; text-align:center;" placeholder="Gabaritas">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td>
                                        <input type="text" name="nominalai[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['nominalas'] ?? '') ?>" style="width:90%; text-align:center;" placeholder="Nominalas">
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
