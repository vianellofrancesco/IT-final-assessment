<?php
function admin_stats($params, $body) {
    Auth::requireAdmin();
    $pdo = getPDO();

    $perRuolo = $pdo->query(
        'SELECT ruolo, COUNT(*) AS totale FROM utenti GROUP BY ruolo ORDER BY ruolo'
    )->fetchAll();

    Response::json([
        'utenti_per_ruolo' => $perRuolo,
        'totale_zone' => (int) $pdo->query('SELECT COUNT(*) FROM zone')->fetchColumn(),
        'totale_commenti' => (int) $pdo->query('SELECT COUNT(*) FROM commenti')->fetchColumn(),
        'totale_news' => (int) $pdo->query('SELECT COUNT(*) FROM news_cache')->fetchColumn(),
        'totale_letture' => (int) $pdo->query('SELECT COUNT(*) FROM letture')->fetchColumn(),
        'totale_confronti' => (int) $pdo->query('SELECT COUNT(*) FROM confronti')->fetchColumn(),
    ]);
}

function admin_news($params, $body) {
    Auth::requireAdmin();
    $lim = isset($_GET['limit']) ? min(max((int) $_GET['limit'], 1), 200) : 50;

    $st = getPDO()->prepare(
        'SELECT n.id, n.titolo, n.url, n.fonte_nome, n.pubblicata_il, n.recuperata_il,
                z.codice_zona, z.nome_paese
         FROM news_cache n
         JOIN zone z ON z.id = n.zona_id
         ORDER BY n.recuperata_il DESC
         LIMIT ?'
    );
    $st->bindValue(1, $lim, PDO::PARAM_INT);
    $st->execute();
    Response::json($st->fetchAll());
}

function admin_del_news($params, $body) {
    Auth::requireAdmin();
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    $st = getPDO()->prepare('DELETE FROM news_cache WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() === 0) Response::notFound('News non trovata');
    Response::json(['ok' => true, 'deleted_id' => $id]);
}

function admin_del_zone($params, $body) {
    Auth::requireAdmin();
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    // FK CASCADE puliscono il resto (letture/mix/news/commenti/preferiti/categorie/confronto_zone)
    $st = getPDO()->prepare('DELETE FROM zone WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() === 0) Response::notFound('Zona non trovata');
    Response::json(['ok' => true, 'deleted_id' => $id]);
}
