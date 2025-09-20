<?php
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Firebase\JWT\ExpiredException;
    use Firebase\JWT\SignatureInvalidException;

    // Carregar .env se existir
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    }

    // Usar chave do .env ou fallback para desenvolvimento
    define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'chave_fallback_desenvolvimento_' . bin2hex(random_bytes(16)));

    // Em produção, exigir que .env esteja configurado
    if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
        if (empty($_ENV['JWT_SECRET'])) {
            throw new Exception('❌ JWT_SECRET não configurada no .env para produção!');
        }
    }

    /**
     * Função para criar um token JWT
     * 
     * @param array $payload Dados do usuário (id, nome, email, tipo, etc.)
     * @param int|null $expira Timestamp de expiração (opcional)
     * @return string Token JWT
     */
    function criarToken($payload, $expira = null) {
        if ($expira) {
            $payload['exp'] = $expira;
        } elseif (!isset($payload['exp'])) {
            // Expira em 2 horas por padrão
            $payload['exp'] = time() + (2 * 60 * 60);
        }
        
        $payload['iat'] = time(); // Issued at (emitido em)
        $payload['iss'] = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Issuer (emissor)
        
        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

    /**
     * Função para decodificar e validar um token JWT
     * 
     * @param string $token Token JWT
     * @return object|null Dados decodificados ou null se inválido
     */
    function decodificarToken($token) {
        try {
            return JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        } catch (ExpiredException $e) {
            // Token expirado
            error_log("Token expirado: " . $e->getMessage());
            return null;
        } catch (SignatureInvalidException $e) {
            // Assinatura inválida
            error_log("Assinatura inválida: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            // Outros erros
            error_log("Erro ao decodificar token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Função para extrair token do header Authorization
     * 
     * @return string|null Token ou null se não encontrado
     */
    function extrairTokenHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        if ($headers && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Função para verificar se token é válido
     * 
     * @param string $token Token JWT
     * @return bool True se válido, false se inválido
     */
    function tokenValido($token) {
        return decodificarToken($token) !== null;
    }

    /**
     * Função para obter dados do usuário do token
     * 
     * @param string $token Token JWT
     * @return array|null Dados do usuário ou null se inválido
     */
    function obterDadosUsuario($token) {
        $decoded = decodificarToken($token);
        if ($decoded) {
            return (array) $decoded;
        }
        return null;
    }

?>