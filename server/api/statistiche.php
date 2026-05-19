<?php
function top_co2($params, $body) {
    $lim = isset($_GET['limite']) ? max(min((int) $_GET['limite'], 100), 1) : 10;
    $g = isset($_GET['giorni']) ? max(min((int) $_GET['giorni'], 365), 1) : 7;

    $st = getPDO()->prepare('CALL sp_top_co2(?, ?)');
    $st->bindValue(1, $lim, PDO::PARAM_INT);
    $st->bindValue(2, $g, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    $st->closeCursor();

    Response::json(['limite' => $lim, 'giorni' => $g, 'zone' => $rows]);
}

function stats_cat($params, $body) {
    $st = getPDO()->query(
        'SELECT zona_id, nome_paese, categorie_codici, categorie_nomi
         FROM v_zone_categorizzate ORDER BY nome_paese'
    );
    Response::json($st->fetchAll());
}

function riepilogo($params, $body) {
    $pdo = getPDO();

    $totZone = (int) $pdo->query('SELECT COUNT(*) FROM zone')->fetchColumn();
    $totLetture = (int) $pdo->query('SELECT COUNT(*) FROM letture')->fetchColumn();

    $media = $pdo->query(
        'SELECT AVG(carbon_intensity) FROM letture
         WHERE rilevata_il > NOW() - INTERVAL 24 HOUR'
    )->fetchColumn();
    $media = ($media !== null && $media !== false) ? round((float) $media, 2) : null;

    $green = $pdo->query(
        'SELECT z.id, z.codice_zona, z.nome_paese, AVG(l.carbon_intensity) AS media_co2
         FROM zone z JOIN letture l ON l.zona_id = z.id
         WHERE l.rilevata_il > NOW() - INTERVAL 7 DAY
         GROUP BY z.id, z.codice_zona, z.nome_paese
         ORDER BY media_co2 ASC LIMIT 3'
    )->fetchAll();

    $heavy = $pdo->query(
        'SELECT z.id, z.codice_zona, z.nome_paese, AVG(l.carbon_intensity) AS media_co2
         FROM zone z JOIN letture l ON l.zona_id = z.id
         WHERE l.rilevata_il > NOW() - INTERVAL 7 DAY
         GROUP BY z.id, z.codice_zona, z.nome_paese
         ORDER BY media_co2 DESC LIMIT 3'
    )->fetchAll();

    Response::json([
        'totale_zone' => $totZone,
        'totale_letture' => $totLetture,
        'media_co2_24h' => $media,
        'top3_green' => $green,
        'top3_carbon_heavy' => $heavy,
    ]);
}
