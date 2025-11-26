<?php
// Cabeçalhos CORS

$allowed_origins = [
    'https://www.clidefit.com.br',
    'https://clidefit.com.br',
    'http://localhost:5173',
    'https://api.clidefit.com.br', 
];

$http_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($http_origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $http_origin);
} else {
    header("Access-Control-Allow-Origin: https://www.clidefit.com.br");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");

// Responde preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Composer
require_once __DIR__ . '/vendor/autoload.php';

// Base da URI
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = str_replace('index.php', '', $script_name);
$request_uri = $_SERVER['REQUEST_URI'];
$path = (strpos($request_uri, $base_path) === 0) ? substr($request_uri, strlen($base_path)) : $request_uri;
$method_http = $_SERVER['REQUEST_METHOD'];
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');

// Rotas
require_once __DIR__ . '/Routes/routes.php';

// Despacha a requisição
dispatch($path, $routes, $controller_paths, $method_http);
?>
