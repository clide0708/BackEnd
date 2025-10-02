<?php 

    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Firebase\JWT\ExpiredException;
    use Firebase\JWT\SignatureInvalidException;

    // ==== Carregar .env (.env.production > .env.development) ====
    $envFile = null;
    if (file_exists(__DIR__ . '/.env.production')) {
        $envFile = '.env.production';
    } elseif (file_exists(__DIR__ . '/.env.development')) {
        $envFile = '.env.development';
    } elseif (file_exists(__DIR__ . '/.env')) {
        $envFile = '.env';
    }

    if ($envFile) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, $envFile);
        $dotenv->load();
    }

    // ==== JWT_SECRET ====
    if (!defined('JWT_SECRET')) {
        define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'fallback_' . bin2hex(random_bytes(16)));
    }

    // Em produção, garantir que tenha JWT_SECRET configurada
    if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
        if (empty($_ENV['JWT_SECRET'])) {
            throw new Exception('❌ JWT_SECRET não configurada no .env para produção!');
        }
    }

    // Criar token
    function criarToken($payload, $rememberMe = false) {
        $expiracaoPadrao = time() + (2 * 60 * 60); // 2 horas
        $expiracaoLonga = time() + (10 * 365 * 24 * 60 * 60); // Aproximadamente 10 anos (efetivamente nunca expira)

        if ($rememberMe) {
            $payload['exp'] = $expiracaoLonga;
        } else {
            $payload['exp'] = $expiracaoPadrao;
        }

        $payload['iat'] = time(); 
        $payload['iss'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

    // Decodificar token
    function decodificarToken($token) {
        try {
            return JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        } catch (ExpiredException $e) {
            error_log("Token expirado: " . $e->getMessage());
            return null;
        } catch (SignatureInvalidException $e) {
            error_log("Assinatura inválida: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("Erro ao decodificar token: " . $e->getMessage());
            return null;
        }
    }

    // Extrair token do header
    function extrairTokenHeader() {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx ou fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Procura por Authorization case-insensitive
            foreach ($requestHeaders as $key => $value) {
                if (strcasecmp($key, 'Authorization') == 0) {
                    $headers = trim($value);
                    break;
                }
            }
        }
        // Extrai o token do formato "Bearer token"
        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
        return null;
    }

    // Verificar token válido
    function tokenValido($token) {
        return decodificarToken($token) !== null;
    }

    // Obter dados do token
    function obterDadosUsuario($token) {
        $decoded = decodificarToken($token);
        return $decoded ? (array) $decoded : null;
    }

?>