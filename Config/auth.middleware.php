<?php
require_once __DIR__ . '/../Config/jwt.config.php';

function autenticar() {
    $token = extrairTokenHeader();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
        exit;
    }

    $decoded = decodificarToken($token);
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
        exit;
    }

    // salva os dados do usuário no $_SERVER
    // aqui não precisa do ->data, só usa o objeto decodificado
    $_SERVER['user'] = (array) $decoded;
}
?>
