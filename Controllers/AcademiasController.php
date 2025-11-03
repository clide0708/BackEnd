<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class AcademiaController
    {
        private $db;

        public function __construct()
        {
            $this->db = DB::connectDB();
        }

        /**
         * Painel de controle da academia
         */
        public function getPainelControle()
        {
            header('Content-Type: application/json');

            try {
                // Verificar se é academia
                $academia = $this->getAcademiaFromToken();
                if (!$academia) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas academias podem acessar este painel.']);
                    return;
                }

                $idAcademia = $academia['idAcademia'];

                // Estatísticas gerais
                $estatisticas = $this->getEstatisticasAcademia($idAcademia);

                // Solicitações pendentes
                $solicitacoesPendentes = $this->getSolicitacoesPendentes($idAcademia);

                // Usuários vinculados
                $usuariosVinculados = $this->getUsuariosVinculados($idAcademia);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'academia' => [
                            'idAcademia' => $academia['idAcademia'],
                            'nome' => $academia['nome'],
                            'email' => $academia['email']
                        ],
                        'estatisticas' => $estatisticas,
                        'solicitacoes_pendentes' => $solicitacoesPendentes,
                        'usuarios_vinculados' => $usuariosVinculados
                    ]
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        /**
         * Enviar solicitação de vinculação à academia
         */
        public function enviarSolicitacaoVinculacao($data)
        {
            header('Content-Type: application/json');

            try {
                // Validar dados
                $required = ['idAcademia', 'idUsuario', 'tipoUsuario'];
                foreach ($required as $field) {
                    if (!isset($data[$field])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Campo obrigatório: {$field}"]);
                        return;
                    }
                }

                $idAcademia = $data['idAcademia'];
                $idUsuario = $data['idUsuario'];
                $tipoUsuario = $data['tipoUsuario'];
                $mensagem = $data['mensagem'] ?? null;

                // Validar tipos
                if (!in_array($tipoUsuario, ['aluno', 'personal'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                    return;
                }

                // Verificar se academia existe e está ativa
                if (!$this->verificarAcademiaAtiva($idAcademia)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Academia não encontrada ou inativa']);
                    return;
                }

                // Verificar se usuário existe e está ativo
                if (!$this->verificarUsuarioAtivo($idUsuario, $tipoUsuario)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado ou inativo']);
                    return;
                }

                // Verificar se já está vinculado
                if ($this->verificarVinculoAtivo($idAcademia, $idUsuario, $tipoUsuario)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Usuário já está vinculado a esta academia']);
                    return;
                }

                // Verificar se já existe solicitação pendente
                if ($this->verificarSolicitacaoPendente($idAcademia, $idUsuario, $tipoUsuario)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Já existe uma solicitação pendente para esta academia']);
                    return;
                }

                // Gerar token único
                $token = bin2hex(random_bytes(32));

                // Inserir solicitação
                $stmt = $this->db->prepare("
                    INSERT INTO solicitacoes_academia 
                    (token, idAcademia, idUsuario, tipo_usuario, mensagem_solicitante, data_criacao) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $success = $stmt->execute([
                    $token,
                    $idAcademia,
                    $idUsuario,
                    $tipoUsuario,
                    $mensagem
                ]);

                if ($success) {
                    // Buscar dados da solicitação criada
                    $solicitacao = $this->getSolicitacaoPorToken($token);

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Solicitação enviada com sucesso',
                        'data' => $solicitacao
                    ]);
                } else {
                    throw new Exception('Erro ao enviar solicitação');
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        /**
         * Aceitar solicitação de vinculação
         */
        public function aceitarSolicitacao($idSolicitacao)
        {
            header('Content-Type: application/json');

            try {
                $academia = $this->getAcademiaFromToken();
                if (!$academia) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
                    return;
                }

                $this->db->beginTransaction();

                // Buscar e travar solicitação
                $stmt = $this->db->prepare("
                    SELECT * FROM solicitacoes_academia 
                    WHERE idSolicitacao = ? AND idAcademia = ? AND status = 'pendente'
                    FOR UPDATE
                ");
                $stmt->execute([$idSolicitacao, $academia['idAcademia']]);
                $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$solicitacao) {
                    $this->db->rollBack();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Solicitação não encontrada']);
                    return;
                }

                // Atualizar status da solicitação
                $stmt = $this->db->prepare("
                    UPDATE solicitacoes_academia 
                    SET status = 'aceita', data_resposta = NOW() 
                    WHERE idSolicitacao = ?
                ");
                $stmt->execute([$idSolicitacao]);

                // Criar vínculo
                $stmt = $this->db->prepare("
                    INSERT INTO usuarios_academia 
                    (idAcademia, idUsuario, tipo_usuario, data_vinculo) 
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE status = 'ativo', data_desvinculo = NULL
                ");
                $stmt->execute([
                    $solicitacao['idAcademia'],
                    $solicitacao['idUsuario'],
                    $solicitacao['tipo_usuario']
                ]);

                // Atualizar tabela do usuário se for personal
                if ($solicitacao['tipo_usuario'] === 'personal') {
                    $stmt = $this->db->prepare("
                        UPDATE personal SET idAcademia = ? WHERE idPersonal = ?
                    ");
                    $stmt->execute([$solicitacao['idAcademia'], $solicitacao['idUsuario']]);
                }

                $this->db->commit();

                // Buscar dados atualizados
                $solicitacaoAtualizada = $this->getSolicitacaoPorId($idSolicitacao);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Solicitação aceita com sucesso',
                    'data' => $solicitacaoAtualizada
                ]);

            } catch (PDOException $e) {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        /**
         * Recusar solicitação de vinculação
         */
        public function recusarSolicitacao($idSolicitacao, $data = null)
        {
            header('Content-Type: application/json');

            try {
                $academia = $this->getAcademiaFromToken();
                if (!$academia) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
                    return;
                }

                $mensagemResposta = $data['mensagem'] ?? null;

                $stmt = $this->db->prepare("
                    UPDATE solicitacoes_academia 
                    SET status = 'recusada', mensagem_resposta = ?, data_resposta = NOW() 
                    WHERE idSolicitacao = ? AND idAcademia = ? AND status = 'pendente'
                ");

                $success = $stmt->execute([
                    $mensagemResposta,
                    $idSolicitacao,
                    $academia['idAcademia']
                ]);

                if ($success && $stmt->rowCount() > 0) {
                    $solicitacao = $this->getSolicitacaoPorId($idSolicitacao);

                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Solicitação recusada',
                        'data' => $solicitacao
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Solicitação não encontrada']);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        /**
         * Desvincular usuário da academia
         */
        public function desvincularUsuario($data)
        {
            header('Content-Type: application/json');

            try {
                $academia = $this->getAcademiaFromToken();
                if (!$academia) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
                    return;
                }

                $required = ['idUsuario', 'tipoUsuario'];
                foreach ($required as $field) {
                    if (!isset($data[$field])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Campo obrigatório: {$field}"]);
                        return;
                    }
                }

                $idUsuario = $data['idUsuario'];
                $tipoUsuario = $data['tipoUsuario'];
                $motivo = $data['motivo'] ?? null;

                $this->db->beginTransaction();

                // Atualizar vínculo
                $stmt = $this->db->prepare("
                    UPDATE usuarios_academia 
                    SET status = 'inativo', data_desvinculo = NOW(), motivo_desvinculo = ?
                    WHERE idAcademia = ? AND idUsuario = ? AND tipo_usuario = ? AND status = 'ativo'
                ");
                $stmt->execute([$motivo, $academia['idAcademia'], $idUsuario, $tipoUsuario]);

                // Se for personal, remover da tabela personal também
                if ($tipoUsuario === 'personal') {
                    $stmt = $this->db->prepare("
                        UPDATE personal SET idAcademia = NULL WHERE idPersonal = ?
                    ");
                    $stmt->execute([$idUsuario]);
                }

                $this->db->commit();

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuário desvinculado com sucesso'
                ]);

            } catch (PDOException $e) {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        // ========== MÉTODOS AUXILIARES ==========

        private function getEstatisticasAcademia($idAcademia)
        {
            // Alunos vinculados
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_alunos 
                FROM usuarios_academia 
                WHERE idAcademia = ? AND tipo_usuario = 'aluno' AND status = 'ativo'
            ");
            $stmt->execute([$idAcademia]);
            $alunos = $stmt->fetch(PDO::FETCH_ASSOC);

            // Personais vinculados
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_personais 
                FROM usuarios_academia 
                WHERE idAcademia = ? AND tipo_usuario = 'personal' AND status = 'ativo'
            ");
            $stmt->execute([$idAcademia]);
            $personais = $stmt->fetch(PDO::FETCH_ASSOC);

            // Solicitações pendentes
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as solicitacoes_pendentes 
                FROM solicitacoes_academia 
                WHERE idAcademia = ? AND status = 'pendente'
            ");
            $stmt->execute([$idAcademia]);
            $solicitacoes = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'alunos_vinculados' => (int)$alunos['total_alunos'],
                'personais_vinculados' => (int)$personais['total_personais'],
                'solicitacoes_pendentes' => (int)$solicitacoes['solicitacoes_pendentes']
            ];
        }

        private function getSolicitacoesPendentes($idAcademia)
        {
            $stmt = $this->db->prepare("
                SELECT 
                    s.idSolicitacao,
                    s.token,
                    s.idUsuario,
                    s.tipo_usuario,
                    s.mensagem_solicitante,
                    s.data_criacao,
                    CASE 
                        WHEN s.tipo_usuario = 'aluno' THEN a.nome
                        WHEN s.tipo_usuario = 'personal' THEN p.nome
                    END as nome_usuario,
                    CASE 
                        WHEN s.tipo_usuario = 'aluno' THEN a.email
                        WHEN s.tipo_usuario = 'personal' THEN p.email
                    END as email_usuario
                FROM solicitacoes_academia s
                LEFT JOIN alunos a ON s.tipo_usuario = 'aluno' AND s.idUsuario = a.idAluno
                LEFT JOIN personal p ON s.tipo_usuario = 'personal' AND s.idUsuario = p.idPersonal
                WHERE s.idAcademia = ? AND s.status = 'pendente'
                ORDER BY s.data_criacao DESC
            ");
            $stmt->execute([$idAcademia]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        private function getUsuariosVinculados($idAcademia)
        {
            $stmt = $this->db->prepare("
                SELECT 
                    u.idVinculo,
                    u.idUsuario,
                    u.tipo_usuario,
                    u.data_vinculo,
                    u.status,
                    CASE 
                        WHEN u.tipo_usuario = 'aluno' THEN a.nome
                        WHEN u.tipo_usuario = 'personal' THEN p.nome
                    END as nome_usuario,
                    CASE 
                        WHEN u.tipo_usuario = 'aluno' THEN a.email
                        WHEN u.tipo_usuario = 'personal' THEN p.email
                    END as email_usuario,
                    CASE 
                        WHEN u.tipo_usuario = 'aluno' THEN a.foto_perfil
                        WHEN u.tipo_usuario = 'personal' THEN p.foto_perfil
                    END as foto_perfil
                FROM usuarios_academia u
                LEFT JOIN alunos a ON u.tipo_usuario = 'aluno' AND u.idUsuario = a.idAluno
                LEFT JOIN personal p ON u.tipo_usuario = 'personal' AND u.idUsuario = p.idPersonal
                WHERE u.idAcademia = ? AND u.status = 'ativo'
                ORDER BY u.data_vinculo DESC
            ");
            $stmt->execute([$idAcademia]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        private function verificarAcademiaAtiva($idAcademia)
        {
            $stmt = $this->db->prepare("
                SELECT idAcademia FROM academias 
                WHERE idAcademia = ? AND status_conta = 'Ativa'
            ");
            $stmt->execute([$idAcademia]);
            return $stmt->fetch() !== false;
        }

        private function verificarUsuarioAtivo($idUsuario, $tipoUsuario)
        {
            $tabela = $tipoUsuario === 'aluno' ? 'alunos' : 'personal';
            $campoId = $tipoUsuario === 'aluno' ? 'idAluno' : 'idPersonal';

            $stmt = $this->db->prepare("
                SELECT {$campoId} FROM {$tabela} 
                WHERE {$campoId} = ? AND status_conta = 'Ativa'
            ");
            $stmt->execute([$idUsuario]);
            return $stmt->fetch() !== false;
        }

        private function verificarVinculoAtivo($idAcademia, $idUsuario, $tipoUsuario)
        {
            $stmt = $this->db->prepare("
                SELECT idVinculo FROM usuarios_academia 
                WHERE idAcademia = ? AND idUsuario = ? AND tipo_usuario = ? AND status = 'ativo'
            ");
            $stmt->execute([$idAcademia, $idUsuario, $tipoUsuario]);
            return $stmt->fetch() !== false;
        }

        private function verificarSolicitacaoPendente($idAcademia, $idUsuario, $tipoUsuario)
        {
            $stmt = $this->db->prepare("
                SELECT idSolicitacao FROM solicitacoes_academia 
                WHERE idAcademia = ? AND idUsuario = ? AND tipo_usuario = ? AND status = 'pendente'
            ");
            $stmt->execute([$idAcademia, $idUsuario, $tipoUsuario]);
            return $stmt->fetch() !== false;
        }

        private function getSolicitacaoPorToken($token)
        {
            $stmt = $this->db->prepare("
                SELECT * FROM solicitacoes_academia WHERE token = ?
            ");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        private function getSolicitacaoPorId($idSolicitacao)
        {
            $stmt = $this->db->prepare("
                SELECT * FROM solicitacoes_academia WHERE idSolicitacao = ?
            ");
            $stmt->execute([$idSolicitacao]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        private function getAcademiaFromToken()
        {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (strpos($authHeader, 'Bearer ') === 0) {
                require_once __DIR__ . '/../Config/jwt.config.php';
                $token = str_replace('Bearer ', '', $authHeader);
                
                try {
                    $decoded = decodificarToken($token);
                    if ($decoded && $decoded['tipo'] === 'academia') {
                        // Buscar dados completos da academia
                        $stmt = $this->db->prepare("
                            SELECT idAcademia, nome, email, status_conta 
                            FROM academias 
                            WHERE idAcademia = ? AND status_conta = 'Ativa'
                        ");
                        $stmt->execute([$decoded['sub']]);
                        return $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) {
                    return null;
                }
            }
            
            return null;
        }

        private function verificarAcademiaLogada()
        {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (strpos($authHeader, 'Bearer ') === 0) {
                require_once __DIR__ . '/../Config/jwt.config.php';
                $token = str_replace('Bearer ', '', $authHeader);
                
                try {
                    $decoded = decodificarToken($token);
                    return $decoded && $decoded['tipo'] === 'academia';
                } catch (Exception $e) {
                    return false;
                }
            }
            
            return false;
        }
    }

?>