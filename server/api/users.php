<?php
function list_users($params, $body) {
    Auth::requireAdmin();
    $st = getPDO()->query('SELECT id, username, email, ruolo, created_at FROM utenti ORDER BY id');
    Response::json($st->fetchAll());
}

function get_me($params, $body) {
    Auth::requireLogin();
    Response::json(Auth::currentUser());
}

function upd_me($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();

    $u = array_key_exists('username', $body ?? []) && is_string($body['username']) ? trim($body['username']) : null;
    $e = array_key_exists('email', $body ?? []) && is_string($body['email']) ? trim($body['email']) : null;

    if ($u === null && $e === null) {
        Response::error('Indicare almeno username o email', 422);
    }

    $newU = $u ?? $me['username'];
    $newE = $e ?? $me['email'];

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $newU)) {
        Response::error('Username non valido', 422);
    }
    if (!filter_var($newE, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email non valida', 422);
    }

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT id FROM utenti WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
    $st->execute([$newU, $newE, (int) $me['id']]);
    if ($st->fetch()) {
        Response::error('Username o email gia\' in uso', 409);
    }

    $st = $pdo->prepare('UPDATE utenti SET username = ?, email = ? WHERE id = ?');
    $st->execute([$newU, $newE, (int) $me['id']]);

    $st = $pdo->prepare('SELECT id, username, email, ruolo, created_at FROM utenti WHERE id = ?');
    $st->execute([(int) $me['id']]);
    Response::json($st->fetch());
}

function cambia_pw($params, $body) {
    Auth::requireLogin();
    $me = Auth::currentUser();

    $old = is_string($body['vecchia_password'] ?? null) ? $body['vecchia_password'] : '';
    $new = is_string($body['nuova_password'] ?? null) ? $body['nuova_password'] : '';

    if ($old === '' || $new === '') {
        Response::error('Entrambe le password sono obbligatorie', 422);
    }

    $pdo = getPDO();
    $st = $pdo->prepare('SELECT password FROM utenti WHERE id = ?');
    $st->execute([(int) $me['id']]);
    $row = $st->fetch();

    $ok = false;
    if ($row) {
        $stored = (string) $row['password'];
        $info = password_get_info($stored);
        if (!empty($info['algo'])) {
            $ok = password_verify($old, $stored);
        } else {
            // fallback legacy in chiaro
            $ok = hash_equals($stored, $old);
        }
    }
    if (!$ok) {
        Response::error('Password attuale errata', 401);
    }

    $st = $pdo->prepare('UPDATE utenti SET password = ? WHERE id = ?');
    $st->execute([password_hash($new, PASSWORD_BCRYPT), (int) $me['id']]);

    Response::json(['ok' => true]);
}

function del_user($params, $body) {
    Auth::requireAdmin();
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) Response::error('Id non valido', 422);

    $me = Auth::currentUser();
    if ((int) $me['id'] === $id) {
        Response::forbidden('Non puoi eliminare il tuo stesso account');
    }

    $st = getPDO()->prepare('CALL sp_del_utente(?)');
    $st->execute([$id]);
    $st->closeCursor();

    Response::json(['ok' => true, 'deleted_id' => $id]);
}
