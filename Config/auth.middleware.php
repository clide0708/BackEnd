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

        // Salva os dados do usuário decodificados no $_SERVER para acesso posterior nos controllers
        // O objeto decodificado já contém 'sub' (ID do usuário) e 'tipo' (tipo de usuário)
        $_SERVER['user'] = (array) $decoded;
    }

?>
