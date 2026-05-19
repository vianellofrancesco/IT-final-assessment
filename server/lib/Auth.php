<?php
class Auth {

// controllo se loggato
    public static function requireLogin(): void {
        if (empty($_SESSION['user_id'])) Response::unauthorized();
    }


    // controllo se admin
    public static function requireAdmin(): void {
        self::requireLogin();
        if (($_SESSION['ruolo'] ?? null) !== 'admin') {
            Response::forbidden('Servono permessi di admin');
        }
    }


    // restituisce i dati dell'utente

    public static function currentUser(): ?array {
        static $cached = null;
        if ($cached !== null) return $cached;
        if (empty($_SESSION['user_id'])) return null;

        $st = getPDO()->prepare(
            'SELECT id, username, email, ruolo, created_at FROM utenti WHERE id = ?'
        );
        $st->execute([(int) $_SESSION['user_id']]);
        $cached = $st->fetch() ?: null;
        return $cached;
    }


    // imposta sessione post login
    public static function login(int $uid, string $ruolo): void {
        session_regenerate_id(true); 
        $_SESSION['user_id'] = $uid;
        $_SESSION['ruolo'] = $ruolo;
    }


    // elimina la sessione post logout
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
