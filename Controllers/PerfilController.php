<?php

    namespace App\Controllers;

    use Services\PerfilService;

    class PerfilController {
        private $perfilService;

        public function __construct() {
            $this->perfilService = new PerfilService();
        }

        public function getPerfilAluno($idAluno) {
            // Lógica para obter perfil do aluno
            // Necessita de autenticação e autorização
            $perfil = $this->perfilService->getPerfilAluno($idAluno);
            echo json_encode($perfil);
        }

        public function postPerfilAluno() {
            // Lógica para criar perfil do aluno
            // Necessita de autenticação
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->createPerfilAluno($data);
            echo json_encode($result);
        }

        public function putPerfilAluno($idAluno) {
            // Lógica para atualizar perfil do aluno
            // Necessita de autenticação e autorização
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->updatePerfilAluno($idAluno, $data);
            echo json_encode($result);
        }

        public function getPerfilPersonal($idPersonal) {
            // Lógica para obter perfil do personal
            // Necessita de autenticação e autorização
            $perfil = $this->perfilService->getPerfilPersonal($idPersonal);
            echo json_encode($perfil);
        }

        public function postPerfilPersonal() {
            // Lógica para criar perfil do personal
            // Necessita de autenticação
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->createPerfilPersonal($data);
            echo json_encode($result);
        }

        public function putPerfilPersonal($idPersonal) {
            // Lógica para atualizar perfil do personal
            // Necessita de autenticação e autorização
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->updatePerfilPersonal($idPersonal, $data);
            echo json_encode($result);
        }
    }

?>