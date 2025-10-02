<?php

    namespace App\Repositories;

    use PDO;
    use App\Config\Database;

    class PagamentosRepository {
        private $conn;

        public function __construct() {
            require_once __DIR__ . 
    '/../Config/db.connect.php'
    ;
            $this->conn = DB::connectDB();
        }

        public function getAssinaturaById($idAssinatura) {
            $query = "SELECT * FROM assinaturas WHERE idAssinatura = :idAssinatura";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idAssinatura", $idAssinatura);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function registrarPagamento($idAssinatura, $valor, $status, $metodoPagamento, $transacaoId) {
            $query = "INSERT INTO pagamentos (idAssinatura, valor, status, metodo_pagamento, id_gateway_transacao) 
                    VALUES (:idAssinatura, :valor, :status, :metodoPagamento, :transacaoId)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idAssinatura", $idAssinatura);
            $stmt->bindParam(":valor", $valor);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":metodoPagamento", $metodoPagamento);
            $stmt->bindParam(":transacaoId", $transacaoId);
            $success = $stmt->execute();
            return ["success" => $success, "idPagamento" => $this->conn->lastInsertId()];
        }

        public function updateStatusAssinatura($idAssinatura, $status) {
            $query = "UPDATE assinaturas SET status = :status WHERE idAssinatura = :idAssinatura";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":idAssinatura", $idAssinatura);
            return $stmt->execute();
        }

        public function getPagamentoByTransacaoId($transacaoId, $idAssinatura) {
            $query = "SELECT * FROM pagamentos WHERE id_gateway_transacao = :transacaoId AND idAssinatura = :idAssinatura";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":transacaoId", $transacaoId);
            $stmt->bindParam(":idAssinatura", $idAssinatura);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function updateStatusPagamento($idPagamento, $status) {
            $query = "UPDATE pagamentos SET status = :status WHERE idPagamento = :idPagamento";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":idPagamento", $idPagamento);
            $success = $stmt->execute();
            return ["success" => $success, "rows_affected" => $stmt->rowCount()];
        }

        public function getHistoricoPagamentos($idUsuario, $tipoUsuario) {
            $query = "SELECT p.idPagamento, p.valor, p.data_pagamento, p.status, p.metodo_pagamento, 
                            a.idAssinatura, pl.nome as plano_nome
                    FROM pagamentos p
                    JOIN assinaturas a ON p.idAssinatura = a.idAssinatura
                    JOIN planos pl ON a.idPlano = pl.idPlano
                    WHERE a.idUsuario = :idUsuario AND a.tipo_usuario = :tipoUsuario
                    ORDER BY p.data_pagamento DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idUsuario", $idUsuario);
            $stmt->bindParam(":tipoUsuario", $tipoUsuario);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

?>