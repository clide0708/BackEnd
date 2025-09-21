<?php
    // Configurações de cabeçalho HTTP
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");

    // Define o fuso horário para São Paulo
    date_default_timezone_set('America/Sao_Paulo');
   
    // CARREGAR COMPOSER - ADICIONAR NO TOPO DO ARQUIVO
    require_once __DIR__ . '/vendor/autoload.php';

    // Obtém a base do acesso da requisição
    $script_name = $_SERVER['SCRIPT_NAME']; 

    // Remove 'index.php' do final para obter o caminho base da API
    $base_path = str_replace('index.php', '', $script_name); 

    // Obtém a URI da requisição
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Remove o prefixo base da URI para obter apenas a rota limpa
    if (strpos($request_uri, $base_path) === 0) {
        $path = substr($request_uri, strlen($base_path));
    } else {
        $path = $request_uri;
    }
    
    // Obtém o método HTTP da requisição
    $method_http = $_SERVER['REQUEST_METHOD'];

    // Remove query string, se existir
    $path = parse_url($path, PHP_URL_PATH);
    
    // Remove barras extras do início e fim
    $path = trim($path, '/');

    // Inclui o arquivo de rotas
    require_once __DIR__ . '/Routes/routes.php';
    
    // Despacha a requisição
    dispatch($path, $routes, $controller_paths, $method_http);
?>