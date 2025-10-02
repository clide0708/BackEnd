<?php

    namespace App\Controllers;

    use App\Services\PlanosService;

    class PlanosController {
        private $planosService;
        private $tipoUsuarioLogado;

        public function __construct() {
            $this->planosService = new PlanosService();
            if (isset($_SERVER['user'])) {
                $this->tipoUsuarioLogado = $_SERVER['user']->tipo;
            }
        }

        public function getAllPlanos() {
            header('Content-Type: application/json');
            $planos = $this->planosService->getAllPlanos();
            http_response_code(200);
            echo json_encode(['success'=> true, 'data'=> $planos]);
        }

        public function getPlanoById($idPlano) {
            header('Content-Type: application/json');
            $plano = $this->planosService->getPlanoById($idPlano);
            if ($plano) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $plano]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Plano não encontrado.']);
            }
        }

        public function createPlano() {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'dev') {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Apenas desenvolvedores podem criar planos.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->planosService->createPlano($data);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function updatePlano($idPlano) {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'dev') {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Apenas desenvolvedores podem atualizar planos.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->planosService->updatePlano($idPlano, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function deletePlano($idPlano) {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'dev') {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Apenas desenvolvedores podem deletar planos.']);
                return;
            }
            $result = $this->planosService->deletePlano($idPlano);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }
    }

?>