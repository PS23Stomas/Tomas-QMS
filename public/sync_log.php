<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();
require_once __DIR__ . '/klases/TomoQMS.php';

$page_title = 'Sinchronizacijos žurnalas';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$filter_statusas = $_GET['statusas'] ?? '';
$filter_uzsakymas = trim($_GET['uzsakymas'] ?? '');

$logs = [];
$total = 0;

$conn = TomoQMS::getConnection();
if ($conn) {
    try {
        $where = [];
        $params = [];

        if ($filter_statusas === 'ok') {
            $where[] = "statusas = 'ok'";
        } elseif ($filter_statusas === 'klaida') {
            $where[] = "statusas = 'klaida'";
        }

        if ($filter_uzsakymas !== '') {
            $where[] = "uzsakymo_numeris LIKE :uzs";
            $params[':uzs'] = '%' . $filter_uzsakymas . '%';
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $cnt_sql = "SELECT COUNT(*) FROM sync_log $where_sql";
        $stmt = $conn->prepare($cnt_sql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT * FROM sync_log $where_sql ORDER BY data DESC LIMIT :lim OFFSET :off";
        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $logs = [];
    }
}

$total_pages = max(1, ceil($total / $per_page));

require_once __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="page-header">
        <h1 data-testid="text-page-title">Sinchronizacijos žurnalas</h1>
    </div>

    <div class="card">
        <div class="card-header" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: space-between;">
            <h2 data-testid="text-log-count">Viso įrašų: <?= $total ?></h2>
            <form method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <select name="statusas" class="form-control" style="width: auto; min-width: 140px;" data-testid="select-status-filter">
                    <option value="">Visi statusai</option>
                    <option value="ok" <?= $filter_statusas === 'ok' ? 'selected' : '' ?>>Sėkmingi</option>
                    <option value="klaida" <?= $filter_statusas === 'klaida' ? 'selected' : '' ?>>Klaidos</option>
                </select>
                <input type="text" name="uzsakymas" class="form-control" placeholder="Užsakymo Nr..." value="<?= h($filter_uzsakymas) ?>" style="width: 160px;" data-testid="input-order-filter">
                <button type="submit" class="btn btn-primary btn-sm" data-testid="button-filter">Filtruoti</button>
                <?php if ($filter_statusas || $filter_uzsakymas): ?>
                <a href="/sync_log.php" class="btn btn-secondary btn-sm" data-testid="link-clear-filter">Valyti</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($logs)): ?>
        <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
            <?php if ($conn): ?>
            Sinchronizacijos žurnalas tuščias. Įrašai atsiras kai bus atliekami duomenų sinchronizavimai.
            <?php else: ?>
            Nepavyko prisijungti prie Tomo QMS duomenų bazės.
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="data-table" data-testid="table-sync-log">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Veiksmas</th>
                        <th>Lentelė</th>
                        <th>Užsakymo Nr.</th>
                        <th>Įrašų</th>
                        <th>Statusas</th>
                        <th>Vartotojas</th>
                        <th>Klaida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr class="<?= $log['statusas'] === 'klaida' ? 'row-error' : '' ?>" data-testid="row-log-<?= $log['id'] ?>">
                        <td style="white-space: nowrap;"><?= h(date('Y-m-d H:i:s', strtotime($log['data']))) ?></td>
                        <td><?= h($log['veiksmas']) ?></td>
                        <td><span style="font-family: monospace; font-size: 0.85em; color: var(--text-secondary);"><?= h($log['lentele'] ?? '-') ?></span></td>
                        <td><?= h($log['uzsakymo_numeris'] ?? '-') ?></td>
                        <td style="text-align: center;"><?= (int)$log['irasu_kiekis'] ?></td>
                        <td>
                            <?php if ($log['statusas'] === 'ok'): ?>
                            <span class="badge badge-success" data-testid="badge-status-ok">OK</span>
                            <?php else: ?>
                            <span class="badge badge-danger" data-testid="badge-status-error">Klaida</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($log['vartotojas'] ?? '-') ?></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= h($log['klaida'] ?? '') ?>"><?= h($log['klaida'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination" style="display: flex; justify-content: center; gap: 8px; padding: 16px;">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&statusas=<?= urlencode($filter_statusas) ?>&uzsakymas=<?= urlencode($filter_uzsakymas) ?>" class="btn btn-secondary btn-sm" data-testid="link-prev-page">Ankstesnis</a>
            <?php endif; ?>
            <span style="padding: 6px 12px; color: var(--text-secondary);">Puslapis <?= $page ?> iš <?= $total_pages ?></span>
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&statusas=<?= urlencode($filter_statusas) ?>&uzsakymas=<?= urlencode($filter_uzsakymas) ?>" class="btn btn-secondary btn-sm" data-testid="link-next-page">Kitas</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
