<?php
    require_once __DIR__ . '/../Config/db.connect.php';

    class PerfilController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        /**
         * Buscar perfil completo por ID e tipo de usuário
         */
        public function getPerfilCompleto($tipoUsuario, $idUsuario) {
            header('Content-Type: application/json');
            
            try {
                error_log("🎯 Buscando perfil completo - Tipo: $tipoUsuario, ID: $idUsuario");
                
                if (!$idUsuario || $idUsuario === 'undefined') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID do usuário não fornecido']);
                    return;
                }

                // Determinar tabela e campos baseado no tipo de usuário
                switch ($tipoUsuario) {
                    case 'aluno':
                        $tabela = 'alunos';
                        $campoId = 'idAluno';
                        break;
                    case 'personal':
                        $tabela = 'personal';
                        $campoId = 'idPersonal';
                        break;
                    case 'academia':
                        $tabela = 'academias';
                        $campoId = 'idAcademia';
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                        return;
                }

                // Buscar dados principais
                $stmt = $this->db->prepare("
                    SELECT * FROM {$tabela} 
                    WHERE {$campoId} = ? AND status_conta = 'Ativa'
                ");
                $stmt->execute([$idUsuario]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$usuario) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                    return;
                }

                // Buscar modalidades
                $modalidades = $this->buscarModalidadesUsuario($tipoUsuario, $idUsuario);

                // Buscar endereço
                $endereco = $this->buscarEnderecoUsuario($idUsuario, $tipoUsuario);

                // Preparar resposta
                $perfilCompleto = array_merge($usuario, [
                    'modalidades' => $modalidades,
                    'endereco' => $endereco
                ]);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $perfilCompleto
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro ao buscar perfil completo: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        /**
         * Buscar modalidades do usuário
         */
        private function buscarModalidadesUsuario($tipoUsuario, $idUsuario) {
            $tabelaModalidades = '';
            
            switch ($tipoUsuario) {
                case 'aluno':
                    $tabelaModalidades = 'modalidades_aluno';
                    $campoId = 'idAluno';
                    break;
                case 'personal':
                    $tabelaModalidades = 'modalidades_personal';
                    $campoId = 'idPersonal';
                    break;
                case 'academia':
                    $tabelaModalidades = 'modalidades_academia';
                    $campoId = 'idAcademia';
                    break;
                default:
                    return [];
            }

            $stmt = $this->db->prepare("
                SELECT m.idModalidade, m.nome 
                FROM {$tabelaModalidades} um
                JOIN modalidades m ON um.idModalidade = m.idModalidade
                WHERE um.{$campoId} = ?
            ");
            $stmt->execute([$idUsuario]);
            
            $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Retornar apenas IDs para compatibilidade
            return array_map(function($modalidade) {
                return $modalidade['idModalidade'];
            }, $modalidades);
        }

        /**
         * Buscar endereço do usuário
         */
        private function buscarEnderecoUsuario($idUsuario, $tipoUsuario) {
            $stmt = $this->db->prepare("
                SELECT * FROM enderecos_usuarios 
                WHERE idUsuario = ? AND tipoUsuario = ?
            ");
            $stmt->execute([$idUsuario, $tipoUsuario]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        public function getPerfilPorEmail($email) {
            header('Content-Type: application/json');
            
            try {
                // Decodificar email se veio codificado
                $email = urldecode($email);
                error_log("🎯 Buscando perfil por email: " . $email);
                
                if (!$email || $email === 'undefined') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email não fornecido']);
                    return;
                }

                // Buscar em todas as tabelas de usuários
                $queries = [
                    'aluno' => "SELECT idAluno as id, nome, email, numTel as telefone, foto_url, 'aluno' as tipo FROM alunos WHERE email = ? AND status_conta = 'Ativa'",
                    'personal' => "SELECT idPersonal as id, nome, email, numTel as telefone, foto_url, 'personal' as tipo FROM personal WHERE email = ? AND status_conta = 'Ativa'",
                    'academia' => "SELECT idAcademia as id, nome, email, telefone, foto_url, 'academia' as tipo FROM academias WHERE email = ? AND status_conta = 'Ativa'"
                ];

                $usuario = null;
                $tipoUsuario = null;

                foreach ($queries as $tipo => $sql) {
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$email]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result) {
                        $usuario = $result;
                        $tipoUsuario = $tipo;
                        error_log("✅ Usuário encontrado na tabela: $tipo");
                        break;
                    }
                }

                if (!$usuario) {
                    error_log("❌ Usuário não encontrado para email: " . $email);
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                    return;
                }

                error_log("✅ Dados do usuário encontrado: " . json_encode($usuario));
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $usuario
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro ao buscar perfil por email: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        // Métodos GET para perfis
        public function getPerfilAluno($idAluno)
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'aluno' && $this->tipoUsuarioLogado !== 'personal' && $this->tipoUsuarioLogado !== 'dev' && $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            // Um aluno só pode ver o próprio perfil
            // Um personal pode ver o perfil de seus alunos vinculados
            // Um dev/academia pode ver qualquer perfil de aluno
            if ($this->tipoUsuarioLogado === 'aluno' && $this->idUsuarioLogado != $idAluno) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode ver seu próprio perfil.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilAluno($idAluno, $this->idUsuarioLogado, $this->tipoUsuarioLogado);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Perfil de aluno não encontrado ou acesso negado.']);
            }
        }

        public function getPerfilPersonal($idPersonal)
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'personal' && $this->tipoUsuarioLogado !== 'dev' && $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            if ($this->tipoUsuarioLogado === 'personal' && $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode ver seu próprio perfil.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilPersonal($idPersonal);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Perfil de personal não encontrado.']);
            }
        }

        public function getPerfilAcademia($idAcademia)
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'academia' && $this->tipoUsuarioLogado !== 'dev')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            if ($this->tipoUsuarioLogado === 'academia' && $this->idUsuarioLogado != $idAcademia) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode ver seu próprio perfil.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilAcademia($idAcademia);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Perfil de academia não encontrado.']);
            }
        }

        public function getPerfilDev($idDev)
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || $this->tipoUsuarioLogado !== 'dev') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas desenvolvedores podem ver perfis de dev.']);
                return;
            }

            if ($this->idUsuarioLogado != $idDev) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode ver seu próprio perfil de dev.']);
                return;
            }

            $perfil = $this->perfilService->getPerfilDev($idDev);
            if ($perfil) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $perfil]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Perfil de desenvolvedor não encontrado.']);
            }
        }

        // Métodos POST para perfis (apenas para criação inicial, depois PUT)
        public function postPerfilAluno()
        {
            header('Content-Type: application/json');
            // A criação inicial de perfil de aluno é feita no cadastro, este método pode ser para completar informações
            if ($this->tipoUsuarioLogado !== 'aluno' || $this->idUsuarioLogado === null) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas alunos autenticados podem completar seu perfil.']);
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

        public function postPerfilPersonal()
        {
            header('Content-Type: application/json');
            // A criação inicial de perfil de personal é feita no cadastro, este método pode ser para completar informações
            if ($this->tipoUsuarioLogado !== 'personal' || $this->idUsuarioLogado === null) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas personais autenticados podem completar seu perfil.']);
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

        public function postPerfilAcademia()
        {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'academia' || $this->idUsuarioLogado === null) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas academias autenticadas podem completar seu perfil.']);
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
        public function putPerfilAluno($idAluno)
        {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'aluno' || $this->idUsuarioLogado != $idAluno) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode atualizar seu próprio perfil.']);
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

        public function putPerfilPersonal($idPersonal)
        {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'personal' || $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode atualizar seu próprio perfil.']);
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

        public function putPerfilAcademia($idAcademia)
        {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'academia' || $this->idUsuarioLogado != $idAcademia) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode atualizar seu próprio perfil.']);
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

        public function putPerfilDev($idDev)
        {
            header('Content-Type: application/json');
            if ($this->tipoUsuarioLogado !== 'dev' || $this->idUsuarioLogado != $idDev) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode atualizar seu próprio perfil de dev.']);
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
        public function getPlanoUsuario()
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
                return;
            }

            $plano = $this->perfilService->getPlanoUsuario($this->idUsuarioLogado, $this->tipoUsuarioLogado);
            if ($plano) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $plano]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Plano não encontrado para o usuário logado.']);
            }
        }

        public function trocarPlano()
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $idNovoPlano = $data['idNovoPlano'] ?? null;

            if (!$idNovoPlano) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID do novo plano é obrigatório.']);
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

        public function cancelarPlano()
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
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

        public function excluirConta()
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
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
        public function getAlunosDoPersonal($idPersonal)
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'personal' && $this->tipoUsuarioLogado !== 'dev' && $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            // Um personal só pode ver seus próprios alunos
            if ($this->tipoUsuarioLogado === 'personal' && $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode ver seus próprios alunos.']);
                return;
            }

            $alunos = $this->perfilService->getAlunosDoPersonal($idPersonal);
            if ($alunos) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $alunos]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Nenhum aluno encontrado para este personal ou acesso negado.']);
            }
        }

        // Métodos para listar treinos criados por um personal
        public function getTreinosCriadosPorPersonal($idPersonal)
        {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado || ($this->tipoUsuarioLogado !== 'personal' && $this->tipoUsuarioLogado !== 'dev' && $this->tipoUsuarioLogado !== 'academia')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Usuário não autenticado ou sem permissão.']);
                return;
            }

            // Um personal só pode ver seus próprios treinos
            if ($this->tipoUsuarioLogado === 'personal' && $this->idUsuarioLogado != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Você só pode ver seus próprios treinos.']);
                return;
            }

            $treinos = $this->perfilService->getTreinosCriadosPorPersonal($idPersonal);
            if ($treinos) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $treinos]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Nenhum treino encontrado para este personal ou acesso negado.']);
            }
        }

        public function getUsuarioPorEmail($email)
        {
            header('Content-Type: application/json');

            $result = $this->perfilService->getUsuarioPorEmail($email, $this->usuarioLogado);

            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code($result['error'] === 'Usuário não encontrado.' ? 404 : 403);
            }

            echo json_encode($result);
        }

        public function atualizarPerfil() {
            header('Content-Type: application/json');
            
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email é obrigatório']);
                    return;
                }

                $email = $data['email'];
                
                // Determinar tabela baseada no tipo de usuário
                $queries = [
                    'aluno' => "SELECT idAluno as id, 'aluno' as tipo FROM alunos WHERE email = ?",
                    'personal' => "SELECT idPersonal as id, 'personal' as tipo FROM personal WHERE email = ?", 
                    'academia' => "SELECT idAcademia as id, 'academia' as tipo FROM academias WHERE email = ?"
                ];

                $usuario = null;
                $tipoUsuario = null;

                foreach ($queries as $tipo => $sql) {
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$email]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result) {
                        $usuario = $result;
                        $tipoUsuario = $tipo;
                        break;
                    }
                }

                if (!$usuario) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                    return;
                }

                // Determinar tabela de atualização
                switch ($tipoUsuario) {
                    case 'aluno':
                        $tabela = 'alunos';
                        $campoId = 'idAluno';
                        break;
                    case 'personal':
                        $tabela = 'personal';
                        $campoId = 'idPersonal';
                        break;
                    case 'academia':
                        $tabela = 'academias';
                        $campoId = 'idAcademia';
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                        return;
                }

                // Campos permitidos para atualização
                $camposPermitidos = ['nome', 'data_nascimento', 'genero', 'altura', 'meta', 'sobre', 'numTel', 'foto_url'];
                $camposAtualizacao = [];
                $valores = [];

                foreach ($camposPermitidos as $campo) {
                    if (isset($data[$campo])) {
                        $camposAtualizacao[] = "$campo = ?";
                        $valores[] = $data[$campo];
                    }
                }

                if (empty($camposAtualizacao)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nenhum campo válido para atualização']);
                    return;
                }

                // Adicionar ID ao final dos valores
                $valores[] = $usuario['id'];

                $sql = "UPDATE $tabela SET " . implode(', ', $camposAtualizacao) . " WHERE $campoId = ?";
                
                $stmt = $this->db->prepare($sql);
                $success = $stmt->execute($valores);

                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Perfil atualizado com sucesso'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Erro ao atualizar perfil no banco de dados'
                    ]);
                }

            } catch (PDOException $e) {
                error_log("❌ Erro ao atualizar perfil: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            } catch (Exception $e) {
                error_log("❌ Erro geral ao atualizar perfil: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
            }
        }

        public function getPersonalPorId($id)
        {
            $repo = new PerfilRepository();
            $personal = $repo->findById($id);

            if ($personal) {
                echo json_encode([
                    'success' => true,
                    'data' => $personal
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Personal não encontrado'
                ]);
            }
        }
        
        public function getHistoricoTreinos() {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
                return;
            }

            try {
                require_once __DIR__ . '/../Services/TreinosService.php';
                $treinosService = new TreinosService();
                $historico = $treinosService->getHistoricoTreinos($this->usuarioLogado, 30);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'treinos' => $historico]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function retomarTreino($idSessao) {
            header('Content-Type: application/json');
            if (!$this->idUsuarioLogado) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
                return;
            }

            try {
                require_once __DIR__ . '/../Services/TreinosService.php';
                $treinosService = new TreinosService();
                $dadosRetomar = $treinosService->getSessaoParaRetomar($idSessao);
                
                // Buscar treino completo
                $treinoCompleto = $treinosService->buscarTreinoCompleto($dadosRetomar['sessao']['idTreino'], $this->usuarioLogado);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'treino' => $treinoCompleto,
                    'progresso' => $dadosRetomar['progresso'],
                    'idSessao' => $idSessao
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
    }
