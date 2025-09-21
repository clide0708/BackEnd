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

        $_SERVER['user'] = $decoded->data;
    }
?>