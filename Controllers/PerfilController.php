<?php

    namespace App\Controllers;

    use App\Services\PerfilService;

    class PerfilController {
        private $perfilService;
        private $idUsuarioLogado;
        private $tipoUsuarioLogado;

        public function __construct() {
            $this->perfilService = new PerfilService();
            // Obter informações do usuário logado do token JWT
            if (isset($_SERVER['user'])) {
                $this->idUsuarioLogado = $_SERVER['user']->sub;
                $this->tipoUsuarioLogado = $_SERVER['user']->tipo;
            } else {
                // Para rotas que não exigem autenticação ou para testes
                $this->idUsuarioLogado = null;
                $this->tipoUsuarioLogado = null;
            }
        }

        // Métodos GET para perfis
        public function getPerfilAluno($idAluno) {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'aluno'&& $this->tipoUsuarioLogado !== 'personal'&& $this->tipoUsuarioLogado !== 'dev'&& $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error' => 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            // Um aluno só pode ver o próprio perfil
            // Um personal pode ver o perfil de seus alunos vinculados
            // Um dev/academia pode ver qualquer perfil de aluno
            if ($this->tipoUsuarioLogado === 'aluno'&& $this->idUsuarioLogado != $idAluno) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode ver seu próprio perfil.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilAluno($idAluno, $this->idUsuarioLogado, $this->tipoUsuarioLogado);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Perfil de aluno não encontrado ou acesso negado.']);
            }
        }

        public function getPerfilPersonal($idPersonal) {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'personal'&& $this->tipoUsuarioLogado !== 'dev'&& $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            // Um personal só pode ver o próprio perfil
            // Um dev/academia pode ver qualquer perfil de personal
            if ($this->tipoUsuarioLogado === 'personal'&& $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode ver seu próprio perfil.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilPersonal($idPersonal);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Perfil de personal não encontrado.']);
            }
        }

        public function getPerfilAcademia($idAcademia) {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'academia'&& $this->tipoUsuarioLogado !== 'dev')) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            if ($this->tipoUsuarioLogado === 'academia'&& $this->idUsuarioLogado != $idAcademia) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode ver seu próprio perfil.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilAcademia($idAcademia);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Perfil de academia não encontrado.']);
            }
        }

        public function getPerfilDev($idDev) {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || $this->tipoUsuarioLogado !== 'dev') {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Apenas desenvolvedores podem ver perfis de dev.']);
                return;
            }

            if ($this->idUsuarioLogado != $idDev) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode ver seu próprio perfil de dev.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilDev($idDev);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Perfil de desenvolvedor não encontrado.']);
            }
        }

        // Métodos POST para perfis (apenas para criação inicial, depois PUT)
        public function postPerfilAluno() {
            header('Content-Type: application/json');
            // A criação inicial de perfil de aluno é feita no cadastro, este método pode ser para completar informações
            if ($this->tipoUsuarioLogado !== 'aluno'|| $this->idUsuarioLogado === null) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Apenas alunos autenticados podem completar seu perfil.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->createOrUpdatePerfilAluno($this->idUsuarioLogado, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function postPerfilPersonal() {
            header('Content-Type: application/json');
            // A criação inicial de perfil de personal é feita no cadastro, este método pode ser para completar informações
            if ($this->tipoUsuarioLogado !== 'personal'|| $this->idUsuarioLogado === null) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Apenas personais autenticados podem completar seu perfil.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->createOrUpdatePerfilPersonal($this->idUsuarioLogado, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function postPerfilAcademia() {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'academia'|| $this->idUsuarioLogado === null) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Apenas academias autenticadas podem completar seu perfil.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->createOrUpdatePerfilAcademia($this->idUsuarioLogado, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        // Métodos PUT para perfis
        public function putPerfilAluno($idAluno) {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'aluno'|| $this->idUsuarioLogado != $idAluno) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode atualizar seu próprio perfil.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->updatePerfilAluno($idAluno, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function putPerfilPersonal($idPersonal) {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'personal'|| $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode atualizar seu próprio perfil.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->updatePerfilPersonal($idPersonal, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function putPerfilAcademia($idAcademia) {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'academia'|| $this->idUsuarioLogado != $idAcademia) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode atualizar seu próprio perfil.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->updatePerfilAcademia($idAcademia, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function putPerfilDev($idDev) {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'dev'|| $this->idUsuarioLogado != $idDev) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode atualizar seu próprio perfil de dev.']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->perfilService->updatePerfilDev($idDev, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        // Métodos para gerenciamento de planos
        public function getPlanoUsuario() {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success'=> false, 'error'=> 'Usuário não autenticado.']);
                return;
            }

            $plano = $this->perfilService->getPlanoUsuario($this->idUsuarioLogado, $this->tipoUsuarioLogado);
            if ($plano) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $plano]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Plano não encontrado para o usuário logado.']);
            }
        }

        public function trocarPlano() {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success'=> false, 'error'=> 'Usuário não autenticado.']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $idNovoPlano = $data['idNovoPlano'] ?? null;

            if (!$idNovoPlano) {
                http_response_code(400);
                echo json_encode(['success'=> false, 'error'=> 'ID do novo plano é obrigatório.']);
                return;
            }

            $result = $this->perfilService->trocarPlano($this->idUsuarioLogado, $this->tipoUsuarioLogado, $idNovoPlano);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function cancelarPlano() {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success'=> false, 'error'=> 'Usuário não autenticado.']);
                return;
            }

            $result = $this->perfilService->cancelarPlano($this->idUsuarioLogado, $this->tipoUsuarioLogado);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        public function excluirConta() {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success'=> false, 'error'=> 'Usuário não autenticado.']);
                return;
            }

            $result = $this->perfilService->excluirConta($this->idUsuarioLogado, $this->tipoUsuarioLogado);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        }

        // Métodos para listar alunos de um personal
        public function getAlunosDoPersonal($idPersonal) {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'personal'&& $this->tipoUsuarioLogado !== 'dev'&& $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            // Um personal só pode ver seus próprios alunos
            if ($this->tipoUsuarioLogado === 'personal'&& $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode ver seus próprios alunos.']);
                return;
            }

            $alunos = $this->perfilService->getAlunosDoPersonal($idPersonal);
            if ($alunos) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $alunos]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Nenhum aluno encontrado para este personal ou acesso negado.']);
            }
        }

        // Métodos para listar treinos criados por um personal
        public function getTreinosCriadosPorPersonal($idPersonal) {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'personal'&& $this->tipoUsuarioLogado !== 'dev'&& $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            // Um personal só pode ver seus próprios treinos
            if ($this->tipoUsuarioLogado === 'personal'&& $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success'=> false, 'error'=> 'Acesso negado. Você só pode ver seus próprios treinos.']);
                return;
            }

            $treinos = $this->perfilService->getTreinosCriadosPorPersonal($idPersonal);
            if ($treinos) {
                http_response_code(200);
                echo json_encode(['success'=> true, 'data'=> $treinos]);
            } else {
                http_response_code(404);
                echo json_encode(['success'=> false, 'error'=> 'Nenhum treino encontrado para este personal ou acesso negado.']);
            }
        }
    }

?>