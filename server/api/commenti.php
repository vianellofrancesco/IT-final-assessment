<?php
function comm_zona($params, $body) {
    $zid = (int) ($params['id'] ?? 0);
    if ($zid <= 0) Response::error('id zona non valido', 422);

    $limit = isset($_GET['limit']) ? min(max((int) $_GET['limit'], 1), 100) : 20;
    $offset = isset($_GET['offset']) ? max((int) $_GET['offset'], 0) : 0;

    $st = getPDO()->prepare(
        'SELECT id, testo, creato_il, modificato_il,
                utente_id, username, zona_id, codice_zona, nome_paese
         FROM v_commenti_arricchiti
         WHERE zona_id = ?
         ORDER BY creato_il DESC
         LIMIT ? OFFSET ?'
    );
    
    $st->bindValue(1, $zid, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->bindValue(3, $offset, PDO::PARAM_INT);
    $st->execute();

    Response::json(['limit' => $limit, 'offset' => $offset, 'commenti' => $st->fetchAll()]);
}

function comm_new($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();

    $zid = (int) ($body['zona_id'] ?? 0);
    $txt = is_string($body['testo'] ?? null) ? $body['testo'] : '';
    if ($zid <= 0) Response::error('zona_id obbligatorio', 422);

    try {
        $pdo = getPDO();
        $st = $pdo->prepare('CALL sp_nuovo_commento(?, ?, ?)');
        $st->execute([(int) $me['id'], $zid, $txt]);
        $row = $st->fetch();
        $st->closeCursor();
        Response::created($row);
    } catch (PDOException $e) {
       
        if (isset($e->errorInfo[0]) && $e->errorInfo[0] === '45000') {
            Response::error($e->errorInfo[2] ?? 'Validazione fallita', 422);
        }
        throw $e;
    }
}

function comm_edit($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    $txt = is_string($body['testo'] ?? null) ? $body['testo'] : '';
    $len = mb_strlen($txt);
    if ($len < 1 || $len > 2000) Response::error('Il testo deve essere tra 1 e 2000 caratteri', 422);

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT utente_id FROM commenti WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) Response::notFound('Commento non trovato');

    if (!comm_can_edit($row, $me)) {
        Response::forbidden('Solo l\'autore o un admin puo\' modificare');
    }

    $st = $pdo->prepare('UPDATE commenti SET testo = ? WHERE id = ?');
    $st->execute([$txt, $id]);

    $st = $pdo->prepare(
        'SELECT id, testo, creato_il, modificato_il,
                utente_id, username, zona_id, codice_zona, nome_paese
         FROM v_commenti_arricchiti WHERE id = ?'
    );
    $st->execute([$id]);
    Response::json($st->fetch());
}

function comm_del($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT utente_id FROM commenti WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) Response::notFound('Commento non trovato');

    if (!comm_can_edit($row, $me)) {
        Response::forbidden('Solo l\'autore o un admin puo\' eliminare');
    }

    $pdo->prepare('DELETE FROM commenti WHERE id = ?')->execute([$id]);
    Response::json(['ok' => true, 'deleted_id' => $id]);
}

function comm_can_edit($row, $me) {
    return (int) $row['utente_id'] === (int) $me['id']
        || ($me['ruolo'] ?? null) === 'admin';
}
