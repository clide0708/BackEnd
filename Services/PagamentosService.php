<?php

    namespace App\Services;

    use App\Repositories\PagamentosRepository;
    use App\Repositories\PerfilRepository; // Para atualizar o plano do usuário

    class PagamentosService {
        private $pagamentosRepository;
        private $perfilRepository;

        public function __construct() {
            $this->pagamentosRepository = new PagamentosRepository();
            $this->perfilRepository = new PerfilRepository();
        }

        public function iniciarPagamento($idUsuario, $tipoUsuario, $idAssinatura, $metodoPagamento) {
            // 1. Obter detalhes da assinatura
            $assinatura = $this->pagamentosRepository->getAssinaturaById($idAssinatura);

            if (!$assinatura || $assinatura["idUsuario"] != $idUsuario || $assinatura["tipo_usuario"] != $tipoUsuario) {
                return ["success" => false, "error" => "Assinatura inválida ou não pertence ao usuário."];
            }

            // 2. Obter detalhes do plano
            $plano = $this->perfilRepository->getPlanoById($assinatura["idPlano"]);
            if (!$plano) {
                return ["success" => false, "error" => "Plano associado à assinatura não encontrado."];
            }

            // Lógica para integrar com um gateway de pagamento real
            // Por enquanto, vamos simular um pagamento
            $valor = $plano["valor_mensal"];
            $transacaoId = "TRANS_" . uniqid(); // Simula um ID de transação do gateway

            // Registrar o pagamento como pendente
            $result = $this->pagamentosRepository->registrarPagamento(
                $idAssinatura, $valor, "pendente", $metodoPagamento, $transacaoId
            );

            if ($result["success"]) {
                // Atualizar status da assinatura para pendente (se já não estiver)
                $this->pagamentosRepository->updateStatusAssinatura($idAssinatura, "pendente");
                return ["success" => true, "message" => "Pagamento iniciado com sucesso. Aguardando confirmação.", "transacaoId" => $transacaoId, "valor" => $valor];
            } else {
                return ["success" => false, "error" => "Erro ao registrar pagamento."];
            }
        }

        public function confirmarPagamento($idAssinatura, $transacaoId, $statusGateway) {
            // 1. Obter o pagamento pelo ID da transação e assinatura
            $pagamento = $this->pagamentosRepository->getPagamentoByTransacaoId($transacaoId, $idAssinatura);

            if (!$pagamento) {
                return ["success" => false, "error" => "Pagamento não encontrado."];
            }

            // Mapear status do gateway para o status interno
            $statusInterno = "pendente";
            if ($statusGateway === "approved") {
                $statusInterno = "aprovado";
            } elseif ($statusGateway === "declined") {
                $statusInterno = "recusado";
            } elseif ($statusGateway === "refunded") {
                $statusInterno = "estornado";
            }

            // 2. Atualizar o status do pagamento
            $resultPagamento = $this->pagamentosRepository->updateStatusPagamento($pagamento["idPagamento"], $statusInterno);

            if ($resultPagamento["success"] && $statusInterno === "aprovado") {
                // 3. Atualizar o status da assinatura para ativa
                $resultAssinatura = $this->pagamentosRepository->updateStatusAssinatura($idAssinatura, "ativa");
                
                // 4. Atualizar o idPlano na tabela do usuário (se for uma nova assinatura ou troca)
                $assinatura = $this->pagamentosRepository->getAssinaturaById($idAssinatura);
                if ($assinatura) {
                    $this->perfilRepository->updateUsuarioPlano($assinatura["idUsuario"], $assinatura["tipo_usuario"], $assinatura["idPlano"]);
                }

                return ["success" => true, "message" => "Pagamento confirmado e assinatura ativada."];
            } else if ($resultPagamento["success"]) {
                return ["success" => true, "message" => "Status do pagamento atualizado para " . $statusInterno . "."];
            } else {
                return ["success" => false, "error" => "Erro ao confirmar pagamento."];
            }
        }

        public function getHistoricoPagamentos($idUsuario, $tipoUsuario) {
            return $this->pagamentosRepository->getHistoricoPagamentos($idUsuario, $tipoUsuario);
        }
    }

?>