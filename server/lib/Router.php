<?php
class Router {
    private static $routes = [];

    public static function get($p, $h) { self::add('GET', $p, $h); }
    public static function post($p, $h) { self::add('POST', $p, $h); }
    public static function put($p, $h) { self::add('PUT', $p, $h); }
    public static function patch($p, $h) { self::add('PATCH', $p, $h); }
    public static function delete($p, $h) { self::add('DELETE', $p, $h); }

    private static function add($method, $pattern, $handler) {
        self::$routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
    }


    //recevuta la richiesta ne analizza url tramite regex e chiama la funzione corretta
    public static function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = self::path();
        $body = self::body($method);

        foreach (self::$routes as $r) {
            if ($r['method'] !== $method) continue;
            $rx = self::compile($r['pattern']);
            if (preg_match($rx, $path, $m)) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (!is_int($k)) $params[$k] = $v;
                }
                call_user_func($r['handler'], $params, $body);
                return;
            }
        }
        Response::notFound('Endpoint non trovato: ' . $method . ' ' . $path);
    }


    // pulisce url della richiesta e a restitusce solo la parte dopo /api
    private static function path() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $pos = strpos($path, '/api');
        if ($pos !== false) $path = substr($path, $pos);
        return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
    }

    // trasformazione del body della richiesta da json a array associativo
    private static function body($method) {
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) return null;
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') return null;
        $d = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::error('JSON nel body non valido: ' . json_last_error_msg(), 400);
        }
        return $d;
    }

    // converte url della richiesta in regex
    private static function compile($pattern) {
        $p = rtrim($pattern, '/') === '' ? '/' : rtrim($pattern, '/');
        $rx = preg_replace('#:([a-zA-Z_][a-zA-Z0-9_]*)#', '(?P<$1>[^/]+)', $p);
        return '#^' . $rx . '$#';
    }
}
