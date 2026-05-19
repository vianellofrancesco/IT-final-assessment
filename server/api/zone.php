<?php

function zone_list($params, $body) {
    $full = isset($_GET['con_ultima']) && $_GET['con_ultima'] === '1';
    $sql = $full
        ? 'SELECT id, codice_zona, nome_paese, codice_iso, lat, lng,
                  ultima_carbon_intensity, ultima_renewable_pct, ultima_rilevazione
           FROM v_zone_con_ultima_lettura ORDER BY nome_paese'
        : 'SELECT id, codice_zona, nome_paese, codice_iso, latitudine, longitudine
           FROM zone ORDER BY nome_paese';
    Response::json(getPDO()->query($sql)->fetchAll());
}

function zona_get($params, $body) {
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT id, codice_zona, nome_paese, codice_iso, latitudine, longitudine FROM zone WHERE id = ?');
    $st->execute([$id]);
    $z = $st->fetch();
    if (!$z) Response::notFound('Zona non trovata');

    $st = $pdo->prepare(
        'SELECT id, rilevata_il, carbon_intensity, fossil_free_pct, renewable_pct
         FROM letture WHERE zona_id = ? ORDER BY rilevata_il DESC LIMIT 1'
    );
    $st->execute([$id]);
    $ult = $st->fetch() ?: null;

    $mix = $ult ? fetch_mix($pdo, (int) $ult['id']) : [];

    $st = $pdo->prepare(
        'SELECT c.id, c.codice, c.nome, c.descrizione, c.colore_hex, zc.assegnata_il
         FROM zona_categoria zc JOIN categorie c ON c.id = zc.categoria_id
         WHERE zc.zona_id = ? ORDER BY c.codice'
    );
    $st->execute([$id]);

    Response::json([
        'zona' => $z,
        'ultima_lettura' => $ult,
        'mix' => $mix,
        'categorie' => $st->fetchAll(),
    ]);
}

function lettura_live($params, $body) {
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT id, codice_zona FROM zone WHERE id = ?');
    $st->execute([$id]);
    $z = $st->fetch();
    if (!$z) Response::notFound('Zona non trovata');

    // 1) cache (TTL costante non utente, interpolato ok)
    $ttl = (int) LETTURA_CACHE_MINUTI;
    $st = $pdo->prepare(
        "SELECT id, rilevata_il, carbon_intensity, fossil_free_pct, renewable_pct
         FROM letture WHERE zona_id = ?
           AND rilevata_il > NOW() - INTERVAL $ttl MINUTE
         ORDER BY rilevata_il DESC LIMIT 1"
    );
    $st->execute([$id]);
    $cache = $st->fetch();
    if ($cache) {
        Response::json([
            'lettura' => $cache,
            'mix' => fetch_mix($pdo, (int) $cache['id']),
            'fonte' => 'cache_db',
            'stale' => false,
        ]);
    }

    // 2) chiamata API esterna
    $intensity = ElectricityMapsClient::carbonIntensityLatest($z['codice_zona']);
    $breakdown = ElectricityMapsClient::powerBreakdownLatest($z['codice_zona']);

    if ($intensity !== null && $breakdown !== null) {
        $mix = mw_to_pct($breakdown);

        $st = $pdo->prepare('CALL sp_save_lettura(?, ?, ?, ?, ?)');
        $st->execute([
            $id,
            isset($intensity['carbonIntensity']) ? (float) $intensity['carbonIntensity'] : null,
            isset($breakdown['fossilFreePercentage']) ? (float) $breakdown['fossilFreePercentage'] : null,
            isset($breakdown['renewablePercentage']) ? (float) $breakdown['renewablePercentage'] : null,
            json_encode($mix, JSON_UNESCAPED_UNICODE),
        ]);
        $st->closeCursor();

        // Aggiorno le categorie dopo ogni nuova lettura
        try {
            $cl = $pdo->prepare('CALL sp_classifica()');
            $cl->execute();
            $cl->closeCursor();
        } catch (Throwable $e) {
            // non bloccante: la lettura e' gia' stata salvata
        }

        $st = $pdo->prepare(
            'SELECT id, rilevata_il, carbon_intensity, fossil_free_pct, renewable_pct
             FROM letture WHERE zona_id = ? ORDER BY rilevata_il DESC LIMIT 1'
        );
        $st->execute([$id]);
        $lett = $st->fetch();

        Response::json([
            'lettura' => $lett,
            'mix' => fetch_mix($pdo, (int) $lett['id']),
            'fonte' => 'electricity_maps',
            'stale' => false,
        ]);
    }

    // 3) API down -> ultima lettura disponibile (anche stale)
    $st = $pdo->prepare(
        'SELECT id, rilevata_il, carbon_intensity, fossil_free_pct, renewable_pct
         FROM letture WHERE zona_id = ? ORDER BY rilevata_il DESC LIMIT 1'
    );
    $st->execute([$id]);
    $last = $st->fetch();
    if (!$last) Response::error('API esterna non raggiungibile e cache vuota', 503);

    Response::json([
        'lettura' => $last,
        'mix' => fetch_mix($pdo, (int) $last['id']),
        'fonte' => 'cache_db',
        'stale' => true,
    ]);
}

function storico($params, $body) {
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('id non valido', 422);

    // giorni: input utente, ma castato a int + clamp -> safe nell'INTERVAL
    $g = isset($_GET['giorni']) ? max(min((int) $_GET['giorni'], 365), 1) : 7;

    $sql = "SELECT l.id, l.rilevata_il, l.carbon_intensity, l.fossil_free_pct, l.renewable_pct,
                   f.codice AS fonte_codice,
                   f.nome_visualizzato AS fonte_nome,
                   f.categoria AS fonte_categoria,
                   lm.percentuale
            FROM letture l
            LEFT JOIN lettura_mix lm ON lm.lettura_id = l.id
            LEFT JOIN fonti_energetiche f ON f.id = lm.fonte_id
            WHERE l.zona_id = ?
              AND l.rilevata_il > NOW() - INTERVAL $g DAY
            ORDER BY l.rilevata_il DESC, f.codice";
    $st = getPDO()->prepare($sql);
    $st->execute([$id]);
    $rows = $st->fetchAll();

    // raggruppo lato PHP: una riga per (lettura, fonte) -> {lettura, mix[]}
    $out = [];
    foreach ($rows as $r) {
        $lid = (int) $r['id'];
        if (!isset($out[$lid])) {
            $out[$lid] = [
                'id' => $lid,
                'rilevata_il' => $r['rilevata_il'],
                'carbon_intensity' => $r['carbon_intensity'],
                'fossil_free_pct' => $r['fossil_free_pct'],
                'renewable_pct' => $r['renewable_pct'],
                'mix' => [],
            ];
        }
        if ($r['fonte_codice'] !== null) {
            $out[$lid]['mix'][] = [
                'codice' => $r['fonte_codice'],
                'nome' => $r['fonte_nome'],
                'categoria' => $r['fonte_categoria'],
                'percentuale' => $r['percentuale'],
            ];
        }
    }

    Response::json(['giorni' => $g, 'letture' => array_values($out)]);
}

// --- helper ---

function fetch_mix(PDO $pdo, int $lid) {
    $st = $pdo->prepare(
        'SELECT f.codice, f.nome_visualizzato AS nome, f.categoria, lm.percentuale
         FROM lettura_mix lm
         JOIN fonti_energetiche f ON f.id = lm.fonte_id
         WHERE lm.lettura_id = ?
         ORDER BY lm.percentuale DESC'
    );
    $st->execute([$lid]);
    return $st->fetchAll();
}

// EM ritorna kW assoluti -> percentuali sul totale prodotto
function mw_to_pct($breakdown) {
    $bd = $breakdown['powerProductionBreakdown']
       ?? $breakdown['powerConsumptionBreakdown']
       ?? [];
    $tot = 0.0;
    foreach ($bd as $v) {
        if (is_numeric($v) && $v > 0) $tot += (float) $v;
    }
    if ($tot <= 0) return [];

    $mix = [];
    foreach ($bd as $k => $v) {
        if (is_numeric($v) && $v > 0) {
            $mix[$k] = round(((float) $v / $tot) * 100, 2);
        }
    }
    return $mix;
}
