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
                        $campoDataNascimento = 'data_nascimento';
                        break;
                    case 'personal':
                        $tabela = 'personal';
                        $campoId = 'idPersonal';
                        $campoDataNascimento = 'data_nascimento';
                        break;
                    case 'academia':
                        $tabela = 'academias';
                        $campoId = 'idAcademia';
                        $campoDataNascimento = null; // Academia não tem data_nascimento
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                        return;
                }

                // 🔥 CORREÇÃO: Construir query dinamicamente
                if ($campoDataNascimento) {
                    $sql = "SELECT *, DATE_FORMAT($campoDataNascimento, '%Y-%m-%d') as data_nascimento_corrigida FROM {$tabela} WHERE {$campoId} = ? AND status_conta = 'Ativa'";
                } else {
                    $sql = "SELECT * FROM {$tabela} WHERE {$campoId} = ? AND status_conta = 'Ativa'";
                }
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$idUsuario]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$usuario) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                    return;
                }

                // 🔥 CORREÇÃO: Usar a data corrigida apenas se existir
                if (isset($usuario['data_nascimento_corrigida'])) {
                    $usuario['data_nascimento'] = $usuario['data_nascimento_corrigida'];
                    unset($usuario['data_nascimento_corrigida']);
                }

                // 🔥 CORREÇÃO: Buscar modalidades completas (com nomes)
                $modalidades = $this->buscarModalidadesUsuarioComNomes($tipoUsuario, $idUsuario);

                // Buscar endereço
                $endereco = $this->buscarEnderecoUsuario($idUsuario, $tipoUsuario);

                // 🔥 NOVO: Buscar horários da academia (se for academia)
                $horarios = [];
                if ($tipoUsuario === 'academia') {
                    $horarios = $this->buscarHorariosAcademia($idUsuario);
                }

                // Preparar resposta
                $perfilCompleto = array_merge($usuario, [
                    'modalidades' => $modalidades,
                    'endereco' => $endereco,
                    'horarios' => $horarios
                ]);

                error_log("✅ Perfil carregado com sucesso - Modalidades: " . count($modalidades));

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

        // 🔥 NOVO: Método para buscar horários da academia
        private function buscarHorariosAcademia($idAcademia) {
            try {
                $stmt = $this->db->prepare("
                    SELECT dia_semana, aberto_24h, horario_abertura, horario_fechamento, fechado 
                    FROM academia_horarios 
                    WHERE idAcademia = ?
                    ORDER BY FIELD(dia_semana, 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo')
                ");
                $stmt->execute([$idAcademia]);
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("❌ Erro ao buscar horários da academia: " . $e->getMessage());
                return [];
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

            try {
                $stmt = $this->db->prepare("
                    SELECT m.idModalidade 
                    FROM {$tabelaModalidades} um
                    JOIN modalidades m ON um.idModalidade = m.idModalidade
                    WHERE um.{$campoId} = ?
                ");
                $stmt->execute([$idUsuario]);
                
                $modalidades = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                
                error_log("✅ Modalidades encontradas para $tipoUsuario $idUsuario: " . json_encode($modalidades));
                
                return $modalidades;
                
            } catch (PDOException $e) {
                error_log("❌ Erro ao buscar modalidades: " . $e->getMessage());
                return [];
            }
        }

        private function buscarModalidadesUsuarioComNomes($tipoUsuario, $idUsuario) {
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

            try {
                error_log("🔍 Buscando modalidades: tabela={$tabelaModalidades}, id={$idUsuario}");
                
                $stmt = $this->db->prepare("
                    SELECT m.idModalidade, m.nome 
                    FROM {$tabelaModalidades} um
                    JOIN modalidades m ON um.idModalidade = m.idModalidade
                    WHERE um.{$campoId} = ?
                ");
                $stmt->execute([$idUsuario]);
                
                $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("🎯 Modalidades encontradas para $tipoUsuario $idUsuario: " . json_encode($modalidades));
                
                // 🔥 CORREÇÃO: Retornar array de IDs para compatibilidade
                $modalidadesIds = array_map(function($modalidade) {
                    return (int)$modalidade['idModalidade'];
                }, $modalidades);
                
                error_log("🎯 Modalidades IDs: " . json_encode($modalidadesIds));
                
                return $modalidadesIds;
                
            } catch (PDOException $e) {
                error_log("❌ Erro ao buscar modalidades: " . $e->getMessage());
                return [];
            }
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
                $camposPermitidos = ['nome', 'data_nascimento', 'genero', 'altura', 'meta', 'sobre', 'numTel', 'foto_url', 'telefone'];
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

        public function atualizarPerfilCompleto() {
            header('Content-Type: application/json');
            
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['email']) || !isset($data['tipoUsuario'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email e tipo de usuário são obrigatórios']);
                    return;
                }

                $email = $data['email'];
                $tipoUsuario = $data['tipoUsuario'];
                
                // Buscar usuário para obter ID
                $queries = [
                    'aluno' => "SELECT idAluno as id FROM alunos WHERE email = ?",
                    'personal' => "SELECT idPersonal as id FROM personal WHERE email = ?",
                    'academia' => "SELECT idAcademia as id FROM academias WHERE email = ?"
                ];

                $usuario = null;
                $idUsuario = null;

                foreach ($queries as $tipo => $sql) {
                    if ($tipo === $tipoUsuario) {
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([$email]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result) {
                            $usuario = $result;
                            $idUsuario = $result['id'];
                            break;
                        }
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
                        $campoTelefone = 'numTel'; // 🔥 CORREÇÃO: alunos usa numTel
                        break;
                    case 'personal':
                        $tabela = 'personal';
                        $campoId = 'idPersonal';
                        $campoTelefone = 'numTel'; // 🔥 CORREÇÃO: personal usa numTel
                        break;
                    case 'academia':
                        $tabela = 'academias';
                        $campoId = 'idAcademia';
                        $campoTelefone = 'telefone'; // 🔥 CORREÇÃO: academia usa telefone
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                        return;
                }

                // 🔥 ATUALIZAÇÃO DOS DADOS PRINCIPAIS
                $camposAtualizacao = [];
                $valores = [];

                // Campos comuns a todos os tipos
                $camposComuns = ['nome'];
                
                // 🔥 CORREÇÃO: Campos específicos por tipo
                $camposEspecificos = [
                    'aluno' => ['data_nascimento', 'genero', 'altura', 'peso', 'meta', 'treinoTipo', 'treinos_adaptados'],
                    'personal' => ['data_nascimento', 'genero', 'sobre', 'treinos_adaptados', 'cref_numero', 'cref_categoria', 'cref_regional'],
                    'academia' => ['nome_fantasia', 'razao_social', 'sobre', 'tamanho_estrutura', 'capacidade_maxima', 'ano_fundacao', 
                                'estacionamento', 'vestiario', 'ar_condicionado', 'wifi', 
                                'totem_de_carregamento_usb', 'area_descanso', 'avaliacao_fisica', 'treinos_adaptados']
                ];

                $camposPermitidos = array_merge($camposComuns, $camposEspecificos[$tipoUsuario] ?? []);

                // 🔥 CORREÇÃO: Adicionar telefone com o nome correto
                if (isset($data['numTel']) || isset($data['telefone'])) {
                    $camposAtualizacao[] = "{$campoTelefone} = ?";
                    
                    // Usar o campo correto baseado no que foi enviado
                    if (isset($data['numTel'])) {
                        $valores[] = $data['numTel'];
                    } else {
                        $valores[] = $data['telefone'];
                    }
                }

                foreach ($camposPermitidos as $campo) {
                    if (isset($data[$campo])) {
                        $camposAtualizacao[] = "$campo = ?";
                        
                        // Converter booleanos para inteiros
                        if (is_bool($data[$campo])) {
                            $valores[] = $data[$campo] ? 1 : 0;
                        } else {
                            $valores[] = $data[$campo];
                        }
                    }
                }

                // Adicionar foto_url se existir
                if (isset($data['foto_url'])) {
                    $camposAtualizacao[] = "foto_url = ?";
                    $valores[] = $data['foto_url'];
                }

                // Atualizar dados principais
                if (!empty($camposAtualizacao)) {
                    $valores[] = $idUsuario;
                    $sql = "UPDATE $tabela SET " . implode(', ', $camposAtualizacao) . " WHERE $campoId = ?";
                    
                    error_log("🎯 Executando SQL: $sql");
                    error_log("🎯 Valores: " . json_encode($valores));
                    
                    $stmt = $this->db->prepare($sql);
                    $success = $stmt->execute($valores);
                    
                    if (!$success) {
                        throw new Exception("Erro ao atualizar dados principais");
                    }
                }

                // 🔥 ATUALIZAR MODALIDADES
                if (isset($data['modalidades']) && is_array($data['modalidades'])) {
                    $this->atualizarModalidadesUsuario($tipoUsuario, $idUsuario, $data['modalidades']);
                }

                // 🔥 ATUALIZAR ENDEREÇO
                if (isset($data['endereco']) && is_array($data['endereco'])) {
                    $this->atualizarEnderecoUsuario($idUsuario, $tipoUsuario, $data['endereco']);
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Perfil atualizado com sucesso',
                    'idUsuario' => $idUsuario
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro ao atualizar perfil completo: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            } catch (Exception $e) {
                error_log("❌ Erro geral ao atualizar perfil: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
            }
        }

        private function atualizarModalidadesUsuario($tipoUsuario, $idUsuario, $modalidades) {
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
                    return false;
            }

            try {
                // 🔥 CORREÇÃO: Limpar modalidades existentes
                $stmt = $this->db->prepare("DELETE FROM {$tabelaModalidades} WHERE {$campoId} = ?");
                $stmt->execute([$idUsuario]);

                // 🔥 CORREÇÃO: Inserir novas modalidades apenas se não estiver vazio
                if (!empty($modalidades)) {
                    $stmt = $this->db->prepare("INSERT INTO {$tabelaModalidades} ({$campoId}, idModalidade) VALUES (?, ?)");
                    
                    foreach ($modalidades as $idModalidade) {
                        // 🔥 CORREÇÃO: Garantir que é numérico
                        $idModalidade = (int)$idModalidade;
                        if ($idModalidade > 0) {
                            $stmt->execute([$idUsuario, $idModalidade]);
                            error_log("✅ Inserindo modalidade: $idModalidade para $tipoUsuario $idUsuario");
                        }
                    }
                }
                
                error_log("✅ Modalidades atualizadas: " . count($modalidades) . " para $tipoUsuario $idUsuario");
                return true;
            } catch (PDOException $e) {
                error_log("❌ Erro ao atualizar modalidades: " . $e->getMessage());
                return false;
            }
        }

        private function atualizarEnderecoUsuario($idUsuario, $tipoUsuario, $endereco) {
            try {
                // Verificar se endereço já existe
                $stmt = $this->db->prepare("
                    SELECT idEndereco FROM enderecos_usuarios 
                    WHERE idUsuario = ? AND tipoUsuario = ?
                ");
                $stmt->execute([$idUsuario, $tipoUsuario]);
                $enderecoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($enderecoExistente) {
                    // Atualizar endereço existente
                    $stmt = $this->db->prepare("
                        UPDATE enderecos_usuarios 
                        SET cep = ?, logradouro = ?, numero = ?, complemento = ?, 
                            bairro = ?, cidade = ?, estado = ?, pais = ?,
                            data_atualizacao = NOW()
                        WHERE idEndereco = ?
                    ");
                    
                    $stmt->execute([
                        $endereco['cep'] ?? '',
                        $endereco['logradouro'] ?? '',
                        $endereco['numero'] ?? '',
                        $endereco['complemento'] ?? '',
                        $endereco['bairro'] ?? '',
                        $endereco['cidade'] ?? '',
                        $endereco['estado'] ?? '',
                        $endereco['pais'] ?? 'Brasil',
                        $enderecoExistente['idEndereco']
                    ]);
                } else {
                    // Inserir novo endereço
                    $stmt = $this->db->prepare("
                        INSERT INTO enderecos_usuarios 
                        (idUsuario, tipoUsuario, cep, logradouro, numero, complemento, 
                        bairro, cidade, estado, pais, data_criacao)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $idUsuario,
                        $tipoUsuario,
                        $endereco['cep'] ?? '',
                        $endereco['logradouro'] ?? '',
                        $endereco['numero'] ?? '',
                        $endereco['complemento'] ?? '',
                        $endereco['bairro'] ?? '',
                        $endereco['cidade'] ?? '',
                        $endereco['estado'] ?? '',
                        $endereco['pais'] ?? 'Brasil'
                    ]);
                }
                
                return true;
            } catch (PDOException $e) {
                error_log("❌ Erro ao atualizar endereço: " . $e->getMessage());
                return false;
            }
        }

        private function atualizarAcademiaUsuario($tipoUsuario, $idUsuario, $idAcademia) {
            try {
                $tabela = $tipoUsuario === 'aluno' ? 'alunos' : 'personal';
                $campoId = $tipoUsuario === 'aluno' ? 'idAluno' : 'idPersonal';
                
                $stmt = $this->db->prepare("UPDATE {$tabela} SET idAcademia = ? WHERE {$campoId} = ?");
                $stmt->execute([$idAcademia, $idUsuario]);
                
                return true;
            } catch (PDOException $e) {
                error_log("❌ Erro ao atualizar academia: " . $e->getMessage());
                return false;
            }
        }

        private function atualizarAcademiaEEnviarSolicitacao($tipoUsuario, $idUsuario, $idAcademia, $idAcademiaOriginal = null) {
            try {
                $tabela = $tipoUsuario === 'aluno' ? 'alunos' : 'personal';
                $campoId = $tipoUsuario === 'aluno' ? 'idAluno' : 'idPersonal';
                
                // Atualizar academia no usuário
                $stmt = $this->db->prepare("UPDATE {$tabela} SET idAcademia = ? WHERE {$campoId} = ?");
                $stmt->execute([$idAcademia, $idUsuario]);
                
                // Se a academia mudou, enviar solicitação
                if ($idAcademia != $idAcademiaOriginal) {
                    $this->enviarSolicitacaoAcademia($idUsuario, $tipoUsuario, $idAcademia);
                }
                
                return true;
            } catch (PDOException $e) {
                error_log("❌ Erro ao atualizar academia: " . $e->getMessage());
                return false;
            }
        }

        private function enviarSolicitacaoAcademia($idUsuario, $tipoUsuario, $idAcademia) {
            try {
                // Verificar se academia existe e está ativa
                $stmt = $this->db->prepare("SELECT idAcademia FROM academias WHERE idAcademia = ? AND status_conta = 'Ativa'");
                $stmt->execute([$idAcademia]);
                
                if (!$stmt->fetch()) {
                    error_log("⚠️ Academia ID $idAcademia não encontrada ou inativa");
                    return false;
                }

                // Gerar token único
                $token = bin2hex(random_bytes(32));

                // Inserir solicitação
                $stmt = $this->db->prepare("
                    INSERT INTO solicitacoes_academia 
                    (token, idAcademia, idUsuario, tipo_usuario, data_criacao) 
                    VALUES (?, ?, ?, ?, NOW())
                ");

                $success = $stmt->execute([
                    $token,
                    $idAcademia,
                    $idUsuario,
                    $tipoUsuario
                ]);

                if ($success) {
                    error_log("✅ Solicitação de vinculação enviada para academia ID: $idAcademia");
                    return true;
                } else {
                    error_log("❌ Erro ao enviar solicitação de vinculação");
                    return false;
                }
            } catch (PDOException $e) {
                error_log("❌ PDOException ao enviar solicitação: " . $e->getMessage());
                return false;
            }
        }
    }
