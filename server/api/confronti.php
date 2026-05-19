<?php
function conf_list($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();

    $st = getPDO()->prepare(
        'SELECT id, utente_id, nome, descrizione, creato_il, numero_zone, ultima_modifica
         FROM v_confronti_utente
         WHERE utente_id = ?
         ORDER BY creato_il DESC'
    );
    $st->execute([(int) $me['id']]);
    Response::json($st->fetchAll());
}

function conf_new($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();

    $nome = is_string($body['nome'] ?? null) ? trim($body['nome']) : '';
    $descr = is_string($body['descrizione'] ?? null) ? $body['descrizione'] : null;
    $ids = $body['zone_ids'] ?? null;

    if ($nome === '' || strlen($nome) > 100) Response::error('Nome obbligatorio (max 100)', 422);
    if (!is_array($ids) || count($ids) === 0) Response::error('zone_ids deve essere un array non vuoto', 422);
    if (count($ids) > 20) Response::error('Massimo 20 zone per confronto', 422);

    $clean = [];
    foreach ($ids as $i) {
        $n = (int) $i;
        if ($n <= 0) Response::error('zone_ids contiene id non validi', 422);
        $clean[] = $n;
    }
    $csv = implode(',', $clean);

    $pdo = getPDO();
    $st = $pdo->prepare('CALL sp_crea_conf(?, ?, ?, ?)');
    $st->execute([(int) $me['id'], $nome, $descr, $csv]);
    $row = $st->fetch();
    $st->closeCursor();

    $cid = (int) ($row['confronto_id'] ?? 0);

    $st = $pdo->prepare(
        'SELECT id, utente_id, nome, descrizione, creato_il, numero_zone, ultima_modifica
         FROM v_confronti_utente
         WHERE id = ? AND utente_id = ?'
    );
    $st->execute([$cid, (int) $me['id']]);
    Response::created($st->fetch());
}

function conf_get($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();
    $cid = (int) ($params['id'] ?? 0);
    if ($cid <= 0) Response::error('id non valido', 422);

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT id, utente_id, nome, descrizione, creato_il FROM confronti WHERE id = ? AND utente_id = ?');
    $st->execute([$cid, (int) $me['id']]);
    $conf = $st->fetch();
    if (!$conf) Response::notFound('Confronto non trovato'); // 404 anche se di un altro

    $st = $pdo->prepare('CALL sp_dati_conf(?)');
    $st->execute([$cid]);
    $zone = $st->fetchAll();
    $st->closeCursor();

    Response::json(['confronto' => $conf, 'zone' => $zone]);
}

function conf_del($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();
    $cid = (int) ($params['id'] ?? 0);
    if ($cid <= 0) Response::error('id non valido', 422);

    $st = getPDO()->prepare('DELETE FROM confronti WHERE id = ? AND utente_id = ?');
    $st->execute([$cid, (int) $me['id']]);
    if ($st->rowCount() === 0) Response::notFound('Confronto non trovato');

    Response::json(['ok' => true, 'deleted_id' => $cid]);
}
