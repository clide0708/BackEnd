<?php

    namespace App\Repositories;

    use PDO;
    use App\Config\Database;

    class PlanosRepository {
        private $conn;

        public function __construct() {
            require_once __DIR__ . 
    '/../Config/db.connect.php'
    ;
            $this->conn = DB::connectDB();
        }

        public function getAllPlanos() {
            $query = "SELECT * FROM planos WHERE ativo = TRUE";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function getPlanoById($idPlano) {
            $query = "SELECT * FROM planos WHERE idPlano = :idPlano AND ativo = TRUE";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idPlano", $idPlano);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function createPlano($data) {
            $query = "INSERT INTO planos (nome, descricao, valor_mensal, tipo_usuario, caracteristicas) VALUES (:nome, :descricao, :valor_mensal, :tipo_usuario, :caracteristicas)";
            $stmt = $this->conn->prepare($query);
            
            $caracteristicasJson = isset($data["caracteristicas"]) ? json_encode($data["caracteristicas"]) : null;

            $stmt->bindParam(":nome", $data["nome"]);
            $stmt->bindParam(":descricao", $data["descricao"] ?? null);
            $stmt->bindParam(":valor_mensal", $data["valor_mensal"]);
            $stmt->bindParam(":tipo_usuario", $data["tipo_usuario"]);
            $stmt->bindParam(":caracteristicas", $caracteristicasJson);

            $success = $stmt->execute();
            return ["success" => $success, "idPlano" => $this->conn->lastInsertId()];
        }

        public function updatePlano($idPlano, $data) {
            $updates = [];
            $params = [":idPlano" => $idPlano];

            if (isset($data["nome"])) { $updates[] = "nome = :nome"; $params[":nome"] = $data["nome"]; }
            if (isset($data["descricao"])) { $updates[] = "descricao = :descricao"; $params[":descricao"] = $data["descricao"]; }
            if (isset($data["valor_mensal"])) { $updates[] = "valor_mensal = :valor_mensal"; $params[":valor_mensal"] = $data["valor_mensal"]; }
            if (isset($data["tipo_usuario"])) { $updates[] = "tipo_usuario = :tipo_usuario"; $params[":tipo_usuario"] = $data["tipo_usuario"]; }
            if (isset($data["caracteristicas"])) { $updates[] = "caracteristicas = :caracteristicas"; $params[":caracteristicas"] = json_encode($data["caracteristicas"]); }
            if (isset($data["ativo"])) { $updates[] = "ativo = :ativo"; $params[":ativo"] = $data["ativo"]; }

            if (empty($updates)) {
                return ["success" => false, "message" => "Nenhum dado para atualizar."];
            }

            $query = "UPDATE planos SET " . implode(", ", $updates) . " WHERE idPlano = :idPlano";
            $stmt = $this->conn->prepare($query);
            $success = $stmt->execute($params);

            return ["success" => $success, "rows_affected" => $stmt->rowCount()];
        }

        public function deletePlano($idPlano) {
            // Soft delete: marcar como inativo
            $query = "UPDATE planos SET ativo = FALSE WHERE idPlano = :idPlano";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idPlano", $idPlano);
            $success = $stmt->execute();
            return ["success" => $success, "rows_affected" => $stmt->rowCount()];
        }
    }

?>