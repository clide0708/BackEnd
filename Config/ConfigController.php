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
    }

?>