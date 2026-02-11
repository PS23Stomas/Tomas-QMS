<tr>
<?php
require_once '../klases/Database.php';
$db = new Database();
$conn = $db->getConnection();

$gaminio_id = isset($_GET['gaminio_id']) ? intval($_GET['gaminio_id']) : 0;
$sekcija = '3.6';
$uzsakymo_numeris = isset($_GET['uzsakymo_numeris']) ? $_GET['uzsakymo_numeris'] : '';
$uzsakovas = isset($_GET['uzsakovas']) ? $_GET['uzsakovas'] : '';

$sql = "SELECT * FROM mt_saugikliu_ideklai WHERE gaminio_id = :gaminio_id AND sekcija = :sekcija";
$stmt = $conn->prepare($sql);
$stmt->execute([
    ':gaminio_id' => $gaminio_id,
    ':sekcija' => $sekcija
]);
$mt_saugikliai = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Paruošiame masyvą duomenų priskyrimui pagal pozicijos numerį
$reiksmes = [];
foreach ($mt_saugikliai as $eilute) {
    $poz_n = $eilute['pozicijos_numeris'];
    $reiksmes[$poz_n] = [
        'pozicija' => $eilute['pozicija'],
        'gabaritas' => $eilute['gabaritas'],
        'nominalas' => $eilute['nominalas']
    ];
}

// Naudojamos pozicijos
$standartines_pozicijos = array_merge(range(201, 206), range(401, 404));
?>

<tr>
    <td>3.6</td>
    <td colspan="2">
        <form action="issaugoti_mt_saugiklius.php" method="post">
            <input type="hidden" name="gaminio_id" value="<?= intval($gaminio_id) ?>">
            <input type="hidden" name="sekcija" value="3.6">
            <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
            <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">

           <table class="mt-lentele">

                <tr>
                    <td style="width: 25%; vertical-align: middle; padding-left: 10px; text-align: left;">
                        Š2-0,4 (ir Š4-0,4 pagal schemą) sekcijos komplektuojamų saugiklių–lydžiųjų įdėklų gabaritas, nominalas:
                    </td>
                    <td style="width: 75%;">
                       <table border="1" class="vienodi-stulpeliai">
                            <tr>
                                <?php foreach ($standartines_pozicijos as $poz_n): ?>
                                    <td>
                                        <input type="hidden" name="pozicijos_numeris[]" value="<?= $poz_n ?>">
                                        <input type="text" name="pozicijos[]" value="<?= htmlspecialchars($reiksmes[$poz_n]['pozicija'] ?? '') ?>" placeholder="Pozicija" style="width: 90%; text-align: center;">
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

            <div style="text-align: right; margin-top: ">
                <button type="submit" class="btn btn-primary">Išsaugoti 3.6 duomenis</button>
            </div>
        </form>
    </td>
</tr>
</tr>