<?php

    namespace App\Services;

    use App\Repositories\PlanosRepository;

    class PlanosService {
        private $planosRepository;

        public function __construct() {
            $this->planosRepository = new PlanosRepository();
        }

        public function getAllPlanos() {
            return $this->planosRepository->getAllPlanos();
        }

        public function getPlanoById($idPlano) {
            return $this->planosRepository->getPlanoById($idPlano);
        }

        public function createPlano($data) {
            // Validações de negócio para criação de plano
            if (!isset($data["nome"]) || empty($data["nome"])) {
                return ["success" => false, "error" => "Nome do plano é obrigatório."];
            }
            if (!isset($data["valor_mensal"]) || !is_numeric($data["valor_mensal"]) || $data["valor_mensal"] < 0) {
                return ["success" => false, "error" => "Valor mensal inválido."];
            }
            if (!isset($data["tipo_usuario"]) || !in_array($data["tipo_usuario"], ["aluno", "personal", "academia"])) {
                return ["success" => false, "error" => "Tipo de usuário inválido."];
            }

            return $this->planosRepository->createPlano($data);
        }

        public function updatePlano($idPlano, $data) {
            // Validações de negócio para atualização de plano
            if (isset($data["valor_mensal"]) && (!is_numeric($data["valor_mensal"]) || $data["valor_mensal"] < 0)) {
                return ["success" => false, "error" => "Valor mensal inválido."];
            }
            if (isset($data["tipo_usuario"]) && !in_array($data["tipo_usuario"], ["aluno", "personal", "academia"])) {
                return ["success" => false, "error" => "Tipo de usuário inválido."];
            }

            return $this->planosRepository->updatePlano($idPlano, $data);
        }

        public function deletePlano($idPlano) {
            // Lógica de negócio para exclusão (pode ser soft delete ou verificar dependências)
            return $this->planosRepository->deletePlano($idPlano);
        }
    }

?>