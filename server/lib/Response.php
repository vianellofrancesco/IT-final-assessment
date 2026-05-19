<?php
class Response {


// struttura standard della risposta json
    public static function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function created($data): void{ self::json($data, 201); }
    public static function error(string $m, int $s = 400) { self::json(['error' => $m], $s); }
    public static function notFound(string $m = 'Risorsa non trovata'){ self::error($m, 404); }
    public static function unauthorized(string $m = 'Autenticazione richiesta'){ self::error($m, 401); }
    public static function forbidden(string $m = 'Operazione non consentita'){ self::error($m, 403); }
}
