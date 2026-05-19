<?php


function news_zona($params, $body) {
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT id, codice_zona, codice_iso, nome_paese FROM zone WHERE id = ?');
    $st->execute([$id]);
    $z = $st->fetch();
    if (!$z) Response::notFound('Zona non trovata');

   
    $ttl = (int) NEWS_CACHE_ORE;
    $st = $pdo->prepare(
        "SELECT id, titolo, descrizione, url, fonte_nome, pubblicata_il, recuperata_il
         FROM news_cache
         WHERE zona_id = ? AND recuperata_il > NOW() - INTERVAL $ttl HOUR
         ORDER BY pubblicata_il DESC
         LIMIT 50"
    );
    $st->execute([$id]);
    $fresh = $st->fetchAll();
    if (count($fresh) >= 5) {
        Response::json(['fonte' => 'cache', 'news' => $fresh]);
    }

    $iso = is_string($z['codice_iso'] ?? null) ? $z['codice_iso'] : '';
    $resp = $iso !== '' ? NewsApiClient::topHeadlines($iso, 'business') : null;

    if (!$resp || empty($resp['articles'])) {
        $q = trim('energia ' . ((string) ($z['nome_paese'] ?? '')));
        $resp = NewsApiClient::everything($q, 'it');
    }

    if ($resp && !empty($resp['articles'])) {
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO news_cache (zona_id, titolo, descrizione, url, fonte_nome, pubblicata_il)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($resp['articles'] as $a) {
            if (!isset($a['url']) || !is_string($a['url']) || $a['url'] === '') continue;

            $pub = null;
            if (isset($a['publishedAt'])) {
                $ts = strtotime((string) $a['publishedAt']);
                if ($ts !== false) $pub = date('Y-m-d H:i:s', $ts);
            }

            $ins->execute([
                $id,
                isset($a['title']) ? mb_substr((string) $a['title'], 0, 300) : null,
                $a['description'] ?? null,
                mb_substr((string) $a['url'], 0, 500),
                isset($a['source']['name']) ? mb_substr((string) $a['source']['name'], 0, 100) : null,
                $pub,
            ]);
        }

        $st = $pdo->prepare(
            'SELECT id, titolo, descrizione, url, fonte_nome, pubblicata_il, recuperata_il
             FROM news_cache WHERE zona_id = ?
             ORDER BY pubblicata_il DESC LIMIT 50'
        );
        $st->execute([$id]);
        Response::json(['fonte' => 'api', 'news' => $st->fetchAll()]);
    }

    $st = $pdo->prepare(
        'SELECT id, titolo, descrizione, url, fonte_nome, pubblicata_il, recuperata_il
         FROM news_cache WHERE zona_id = ?
         ORDER BY pubblicata_il DESC LIMIT 50'
    );
    $st->execute([$id]);
    Response::json(['fonte' => 'cache_stale', 'news' => $st->fetchAll()]);
}
