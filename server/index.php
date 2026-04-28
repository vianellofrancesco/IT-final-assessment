<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

$basePath = dirname($_SERVER['SCRIPT_NAME']);
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = '/' . trim(substr($requestUri, strlen($basePath)), '/');

$segments = array_values(array_filter(explode('/', $path)));

if (empty($segments) || $segments[0] !== 'api') {
    echo json_encode(['message' => 'GreenGrid API v1.0']);
    exit;
}

$resource = $segments[1] ?? null;
$id = $segments[2] ?? null;

switch ($resource) {
    case 'zones':
        require __DIR__ . '/api/ZonesController.php';
        $controller = new ZonesController();
        $controller->handle($method, $id);
        break;

    case 'readings':
        require __DIR__ . '/api/ReadingsController.php';
        $controller = new ReadingsController();
        $controller->handle($method, $id);
        break;

    case 'proxy':
        require __DIR__ . '/api/ProxyController.php';
        $controller = new ProxyController();
        $controller->handle($method, $id);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Risorsa non trovata']);
        break;
}
