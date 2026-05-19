<?php
function cat_list($params, $body) {
    $st = getPDO()->query('SELECT id, codice, nome, descrizione, colore_hex FROM categorie ORDER BY codice');
    Response::json($st->fetchAll());
}

function cat_zona($params, $body) {
    $zid = (int) ($params['id'] ?? 0);
    if ($zid <= 0) Response::error('id zona non valido', 422);

    $st = getPDO()->prepare(
        'SELECT c.id, c.codice, c.nome, c.descrizione, c.colore_hex, zc.assegnata_il
         FROM zona_categoria zc
         JOIN categorie c ON c.id = zc.categoria_id
         WHERE zc.zona_id = ?
         ORDER BY c.codice'
    );
    $st->execute([$zid]);
    Response::json($st->fetchAll());
}



function cat_recalc($params, $body) {
    Auth::requireAdmin();

    $pdo = getPDO();
    $st = $pdo->prepare('CALL sp_classifica()');
    $st->execute();
    $st->closeCursor();

    $tot = (int) $pdo->query('SELECT COUNT(*) FROM zona_categoria')->fetchColumn();
    $zone = (int) $pdo->query('SELECT COUNT(DISTINCT zona_id) FROM zona_categoria')->fetchColumn();

    Response::json([
        'ok' => true,
        'assegnazioni' => $tot,
        'zone_classificate' => $zone,
    ]);
}
