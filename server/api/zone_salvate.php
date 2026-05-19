<?php
// Preferiti dell'utente. Tutti gli endpoint filtrano per utente_id dalla sessione.

function pref_list($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();

    $st = getPDO()->prepare(
        'SELECT zs.etichetta, zs.note, zs.salvata_il,
                z.id AS zona_id, z.codice_zona, z.nome_paese, z.codice_iso,
                z.latitudine, z.longitudine,
                v.ultima_carbon_intensity, v.ultima_renewable_pct, v.ultima_rilevazione
         FROM zone_salvate zs
         JOIN zone z ON z.id = zs.zona_id
         LEFT JOIN v_zone_con_ultima_lettura v ON v.id = z.id
         WHERE zs.utente_id = ?
         ORDER BY zs.salvata_il DESC'
    );
    $st->execute([(int) $me['id']]);
    Response::json($st->fetchAll());
}

function pref_save($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();

    $codice = is_string($body['codice_zona'] ?? null) ? trim($body['codice_zona']) : '';
    $etich = array_key_exists('etichetta', $body ?? []) && is_string($body['etichetta']) ? $body['etichetta'] : null;
    $note = array_key_exists('note', $body ?? []) && is_string($body['note']) ? $body['note'] : null;

    if ($codice === '' || strlen($codice) > 10) {
        Response::error('codice_zona obbligatorio (max 10 char)', 422);
    }
    if ($etich !== null && strlen($etich) > 80) {
        Response::error('Etichetta troppo lunga (max 80)', 422);
    }

    $pdo = getPDO();
    $st = $pdo->prepare('CALL sp_salva_pref(?, ?, ?, ?)');
    $st->execute([(int) $me['id'], $codice, $etich, $note]);
    $st->closeCursor();

    $st = $pdo->prepare(
        'SELECT zs.etichetta, zs.note, zs.salvata_il,
                z.id AS zona_id, z.codice_zona, z.nome_paese, z.codice_iso,
                z.latitudine, z.longitudine
         FROM zone_salvate zs
         JOIN zone z ON z.id = zs.zona_id
         WHERE zs.utente_id = ? AND z.codice_zona = ?'
    );
    $st->execute([(int) $me['id'], $codice]);
    Response::created($st->fetch());
}

function pref_put($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();
    $zid = (int) ($params['zona_id'] ?? 0);
    if ($zid <= 0) Response::error('zona_id non valido', 422);

    $etich = array_key_exists('etichetta', $body ?? []) && is_string($body['etichetta']) ? $body['etichetta'] : null;
    $note = array_key_exists('note', $body ?? []) && is_string($body['note']) ? $body['note'] : null;
    if ($etich !== null && strlen($etich) > 80) {
        Response::error('Etichetta troppo lunga', 422);
    }

    $pdo = getPDO();
    $st = $pdo->prepare('UPDATE zone_salvate SET etichetta = ?, note = ? WHERE utente_id = ? AND zona_id = ?');
    $st->execute([$etich, $note, (int) $me['id'], $zid]);

    if ($st->rowCount() === 0) {
        // 0 righe modificate puo' anche significare valori identici, quindi controllo esistenza
        $c = $pdo->prepare('SELECT 1 FROM zone_salvate WHERE utente_id = ? AND zona_id = ? LIMIT 1');
        $c->execute([(int) $me['id'], $zid]);
        if (!$c->fetch()) Response::notFound('Zona non tra i preferiti');
    }

    $st = $pdo->prepare(
        'SELECT zs.etichetta, zs.note, zs.salvata_il,
                z.id AS zona_id, z.codice_zona, z.nome_paese
         FROM zone_salvate zs JOIN zone z ON z.id = zs.zona_id
         WHERE zs.utente_id = ? AND zs.zona_id = ?'
    );
    $st->execute([(int) $me['id'], $zid]);
    Response::json($st->fetch());
}

function pref_patch($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();
    $zid = (int) ($params['zona_id'] ?? 0);
    if ($zid <= 0) Response::error('zona_id non valido', 422);

    $sets = [];
    $args = [];
    if (array_key_exists('etichetta', $body ?? [])) {
        $v = is_string($body['etichetta']) ? $body['etichetta'] : null;
        if ($v !== null && strlen($v) > 80) Response::error('Etichetta troppo lunga', 422);
        $sets[] = 'etichetta = ?';
        $args[] = $v;
    }
    if (array_key_exists('note', $body ?? [])) {
        $sets[] = 'note = ?';
        $args[] = is_string($body['note']) ? $body['note'] : null;
    }
    if (!$sets) Response::error('Nessun campo da aggiornare', 422);

    $args[] = (int) $me['id'];
    $args[] = $zid;

    $pdo = getPDO();
    $sql = 'UPDATE zone_salvate SET ' . implode(', ', $sets) . ' WHERE utente_id = ? AND zona_id = ?';
    $pdo->prepare($sql)->execute($args);

    $c = $pdo->prepare(
        'SELECT zs.etichetta, zs.note, zs.salvata_il,
                z.id AS zona_id, z.codice_zona, z.nome_paese
         FROM zone_salvate zs JOIN zone z ON z.id = zs.zona_id
         WHERE zs.utente_id = ? AND zs.zona_id = ?'
    );
    $c->execute([(int) $me['id'], $zid]);
    $row = $c->fetch();
    if (!$row) Response::notFound('Zona non tra i preferiti');
    Response::json($row);
}

function pref_del($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();
    $zid = (int) ($params['zona_id'] ?? 0);
    if ($zid <= 0) Response::error('zona_id non valido', 422);

    $st = getPDO()->prepare('DELETE FROM zone_salvate WHERE utente_id = ? AND zona_id = ?');
    $st->execute([(int) $me['id'], $zid]);

    if ($st->rowCount() === 0) Response::notFound('Zona non tra i preferiti');
    Response::json(['ok' => true, 'zona_id' => $zid]);
}
