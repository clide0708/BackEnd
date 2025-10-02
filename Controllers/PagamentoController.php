<?php

    namespace App\Controllers;

    use App\Services\PagamentosService;

    class PagamentosController {
        private $pagamentosService;
        private $idUsuarioLogado;
        private $tipoUsuarioLogado;

        public function __construct() {
            $this->pagamentosService = new PagamentosService();
            if (isset($_SERVER['user'])) {
                $this->idUsuarioLogado = $_SERVER['user']->sub;
                $this->tipoUsuarioLogado = $_SERVER['user']->tipo;
            }
        }

        public function iniciarPagamento() {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success'=> false, 'error'=> 'Usuário não autenticado.']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $idAssinatura = $data['idAssinatura'] ?? null;
            $metodoPagamento = $data['metodoPagamento'] ?? null;

            if (!$idAssinatura || !$metodoPagamento) {
                http_response_code(400);
                echo json_encode(['success'=> false, 'error'=> 'ID da assinatura e método de pagamento são obrigatórios.']);
                return;
            }

            $result = $this->pagamentosService->iniciarPagamento($this->idUsuarioLogado, $this->tipoUsuarioLogado, $idAssinatura, $metodoPagamento);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function confirmarPagamento() {
            header('Content-Type: application/json');
            // Este endpoint seria chamado por um webhook do gateway de pagamento ou após o retorno do usuário
            $data = json_decode(file_get_contents('php://input'), true);
            $transacaoId = $data['transacaoId'] ?? null;
            $status = $data['status'] ?? null;
            $idAssinatura = $data['idAssinatura'] ?? null; // Pode ser necessário para verificar

            if (!$transacaoId || !$status || !$idAssinatura) {
                http_response_code(400);
                echo json_encode(['success'=> false, 'error'=> 'ID da transação, status e ID da assinatura são obrigatórios.']);
                return;
            }

            $result = $this->pagamentosService->confirmarPagamento($idAssinatura, $transacaoId, $status);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function getHistoricoPagamentos() {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success'=> false, 'error'=> 'Usuário não autenticado.']);
                return;
            }

            $historico = $this->pagamentosService->getHistoricoPagamentos($this->idUsuarioLogado, $this->tipoUsuarioLogado);
            if ($historico) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $historico]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Nenhum histórico de pagamento encontrado.']);
            }
        }
    }

?>