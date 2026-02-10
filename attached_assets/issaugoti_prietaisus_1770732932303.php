require_once '../klases/Database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("INSERT INTO bandymai_prietaisai 
(gaminio_id, prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr) 
VALUES (?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $_POST['gaminio_id'],
    $_POST['prietaiso_tipas'],
    $_POST['prietaiso_nr'],
    $_POST['patikra_data'],
    $_POST['galioja_iki'],
    $_POST['sertifikato_nr']
]);

header("Location: MTpasas.php?gaminio_id=".$_POST['gaminio_id']."&issaugota=taip");
exit;
