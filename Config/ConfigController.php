<?php

require_once __DIR__ . '/db.connect.php';

class ConfigController {
    public function testarConexaoDB() {
        try {
            $pdo = DB::connectDB();
            echo json_encode(["success" => true, "message" => "Conexão com o banco de dados estabelecida com sucesso!"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Erro ao conectar ao banco de dados: " . $e->getMessage()]);
        }
    }

    public function bemVindo(){
        // Informações da API
        $apiStatus = "200 OK";
        $apiMessage = "API CLIDE Fit está funcionando!";
        $timestamp = date('Y-m-d H:i:s');
        
        // Testar conexão com o banco
        $dbStatus = "Conectado";
        $dbMessage = "Conexão com o banco de dados estabelecida com sucesso!";
        
        try {
            $pdo = DB::connectDB();
            
            // Testar consulta simples no banco
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['test'] == 1) {
                $dbStatus = "Conectado";
                $dbMessage = "Banco de dados respondendo corretamente";
            } else {
                $dbStatus = "Aviso";
                $dbMessage = "Conexão OK mas consulta de teste falhou";
            }
            
        } catch (PDOException $e) {
            $dbStatus = "Erro";
            $dbMessage = "Erro ao conectar ao banco de dados: " . $e->getMessage();
        }

        // Informações do servidor
        $serverInfo = [
            "servidor" => $_SERVER['SERVER_NAME'] ?? 'N/A',
            "php_version" => PHP_VERSION,
            "timestamp" => $timestamp,
            "endpoint" => $_SERVER['REQUEST_URI'] ?? '/'
        ];

        http_response_code(200);
        echo json_encode([
            "success" => true, 
            "message" => "Bem-vindo à API CLIDE Fit",
            "status" => [
                "api" => [
                    "codigo" => 200,
                    "status" => $apiStatus,
                    "mensagem" => $apiMessage
                ],
                "banco_dados" => [
                    "status" => $dbStatus,
                    "mensagem" => $dbMessage,
                    "timestamp" => $timestamp
                ],
                "servidor" => $serverInfo
            ],
            "endpoints" => [
                "documentacao" => "Em breve",
                "health_check" => "/health",
                "versao" => "1.0.0"
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

?>