<?php
function register($params, $body) {
    $username = is_string($body['username'] ?? null) ? trim($body['username']) : '';
    $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        Response::error('Username non valido (3-50 caratteri, lettere/numeri/underscore)', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email non valida', 422);
    }
    if ($password === '') {
        Response::error('Password obbligatoria', 422);
    }

    $pdo = getPDO();

    $st = $pdo->prepare('SELECT id FROM utenti WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$username, $email]);
    if ($st->fetch()) {
        Response::error('Username o email gia\' in uso', 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $pdo->prepare('INSERT INTO utenti (username, email, password, ruolo) VALUES (?, ?, ?, ?)');
    $st->execute([$username, $email, $hash, 'user']);
    $id = (int) $pdo->lastInsertId();

    Auth::login($id, 'user');

    $st = $pdo->prepare('SELECT id, username, email, ruolo, created_at FROM utenti WHERE id = ?');
    $st->execute([$id]);
    Response::created($st->fetch());
}

function login_user($params, $body) {
    $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';

    if ($email === '' || $password === '') {
        Response::error('Email e password obbligatorie', 422);
    }

    $pdo = getPDO();
    $st = $pdo->prepare(
        'SELECT id, username, email, password, ruolo, created_at FROM utenti WHERE email = ? LIMIT 1'
    );
    $st->execute([$email]);
    $row = $st->fetch();

    $ok = false;
    if ($row) {
        $stored = (string) $row['password'];
        $info = password_get_info($stored);
        if (!empty($info['algo'])) {
            $ok = password_verify($password, $stored);
        } 
    }

    if (!$ok) {
        Response::error('Credenziali non valide', 401);
    }

    Auth::login((int) $row['id'], $row['ruolo']);
    unset($row['password']);
    Response::json($row);
}

function logout_user($params, $body) {
    Auth::logout();
    Response::json(['ok' => true]);
}

function me($params, $body) {
    Auth::requireLogin();
    Response::json(Auth::currentUser());
}
