<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class ConvitesController
    {
        private $db;

        public function __construct()
        {
            $this->db = DB::connectDB();
        }

        /**
         * Enviar convite bidirecional (aluno para personal ou personal para aluno)
         */
        public function enviarConvite($data)
        {
            header('Content-Type: application/json');

            try {
                // Validação dos dados
                $required = ['id_remetente', 'tipo_remetente', 'id_destinatario', 'tipo_destinatario'];
                foreach ($required as $field) {
                    if (!isset($data[$field])) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => "Campo obrigatório: {$field}"
                        ]);
                        return;
                    }
                }

                $idRemetente = $data['id_remetente'];
                $tipoRemetente = $data['tipo_remetente'];
                $idDestinatario = $data['id_destinatario'];
                $tipoDestinatario = $data['tipo_destinatario'];
                $mensagem = $data['mensagem'] ?? null;

                // Validar tipos
                if (!in_array($tipoRemetente, ['aluno', 'personal']) || 
                    !in_array($tipoDestinatario, ['aluno', 'personal'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Tipo de remetente/destinatário inválido'
                    ]);
                    return;
                }

                // Verificar se é o mesmo usuário
                if ($idRemetente == $idDestinatario && $tipoRemetente == $tipoDestinatario) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Não é possível enviar convite para si mesmo'
                    ]);
                    return;
                }

                // Verificar se remetente existe e está ativo
                if (!$this->verificarUsuarioAtivo($idRemetente, $tipoRemetente)) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Remetente não encontrado ou inativo'
                    ]);
                    return;
                }

                // Verificar se destinatário existe e está ativo
                if (!$this->verificarUsuarioAtivo($idDestinatario, $tipoDestinatario)) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Destinatário não encontrado ou inativo'
                    ]);
                    return;
                }

                // Verificar se já existe convite pendente
                if ($this->verificarConvitePendente($idRemetente, $tipoRemetente, $idDestinatario, $tipoDestinatario)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Já existe um convite pendente entre estes usuários'
                    ]);
                    return;
                }

                // Preparar dados para inserção
                if ($tipoRemetente === 'personal') {
                    $idPersonal = $idRemetente;
                    $idAluno = $idDestinatario;
                } else {
                    $idPersonal = $idDestinatario;
                    $idAluno = $idRemetente;
                }

                // Gerar token único
                $token = bin2hex(random_bytes(32));

                // Inserir convite
                $stmt = $this->db->prepare("
                    INSERT INTO convites (
                        token, idPersonal, idAluno, email_aluno, status, data_criacao,
                        tipo_remetente, tipo_destinatario, mensagem
                    ) VALUES (?, ?, ?, NULL, 'pendente', NOW(), ?, ?, ?)
                ");

                $success = $stmt->execute([
                    $token,
                    $idPersonal,
                    $idAluno,
                    $tipoRemetente,
                    $tipoDestinatario,
                    $mensagem
                ]);

                if ($success) {
                    $idConvite = $this->db->lastInsertId();

                    // Buscar dados do convite criado
                    $stmt = $this->db->prepare("
                        SELECT c.*, 
                            p.nome as nome_personal,
                            a.nome as nome_aluno
                        FROM convites c
                        LEFT JOIN personal p ON c.idPersonal = p.idPersonal
                        LEFT JOIN alunos a ON c.idAluno = a.idAluno
                        WHERE c.idConvite = ?
                    ");
                    $stmt->execute([$idConvite]);
                    $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Convite enviado com sucesso',
                        'data' => [
                            'idConvite' => $idConvite,
                            'status' => 'pendente',
                            'data_criacao' => $convite['data_criacao'],
                            'mensagem' => $mensagem,
                            'nome_remetente' => $tipoRemetente === 'personal' ? $convite['nome_personal'] : $convite['nome_aluno'],
                            'nome_destinatario' => $tipoDestinatario === 'personal' ? $convite['nome_personal'] : $convite['nome_aluno']
                        ]
                    ]);
                } else {
                    throw new Exception('Erro ao inserir convite no banco');
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
        }

        public function getConvitesByEmail($email) {
            header('Content-Type: application/json');
            
            try {
                $email = urldecode($email);
                error_log("🎯 Buscando convites para email: " . $email);
                
                $stmt = $this->db->prepare("
                    SELECT c.*, 
                        p.nome as nomePersonal,
                        a.nome as nomeAluno,
                        p.foto_perfil as fotoPersonal,
                        a.foto_perfil as fotoAluno
                    FROM convites c
                    LEFT JOIN personal p ON c.idPersonal = p.idPersonal
                    LEFT JOIN alunos a ON c.idAluno = a.idAluno
                    WHERE (a.email = ? OR p.email = ?) 
                    AND c.status = 'pendente'
                    ORDER BY c.data_criacao DESC
                ");
                
                $stmt->execute([$email, $email]);
                $convites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("✅ Convites encontrados: " . count($convites));
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $convites
                ]);
                
            } catch (PDOException $e) {
                error_log("❌ Erro ao buscar convites: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro ao buscar convites: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Listar convites do usuário atual
         */
        public function meusConvites()
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                $idUsuario = $usuario['id'];
                $tipoUsuario = $usuario['tipo'];

                // Buscar convites onde o usuário é destinatário
                if ($tipoUsuario === 'aluno') {
                    $sql = "
                        SELECT c.*, 
                            p.nome as nome_personal,
                            p.foto_perfil as foto_personal,
                            'personal' as tipo_remetente
                        FROM convites c
                        JOIN personal p ON c.idPersonal = p.idPersonal
                        WHERE c.idAluno = ? AND c.status = 'pendente'
                        ORDER BY c.data_criacao DESC
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$idUsuario]);
                } else if ($tipoUsuario === 'personal') {
                    $sql = "
                        SELECT c.*, 
                            a.nome as nome_aluno,
                            a.foto_perfil as foto_aluno,
                            'aluno' as tipo_remetente
                        FROM convites c
                        JOIN alunos a ON c.idAluno = a.idAluno
                        WHERE c.idPersonal = ? AND c.status = 'pendente'
                        ORDER BY c.data_criacao DESC
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$idUsuario]);
                } else {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Tipo de usuário não permitido']);
                    return;
                }

                $convites = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $convites
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Aceitar convite
         */
        public function aceitarConvite($idConvite)
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                $this->db->beginTransaction();

                // Buscar e travar o convite
                $stmt = $this->db->prepare("
                    SELECT * FROM convites 
                    WHERE idConvite = ? AND status = 'pendente'
                    FOR UPDATE
                ");
                $stmt->execute([$idConvite]);
                $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$convite) {
                    $this->db->rollBack();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou já respondido']);
                    return;
                }

                // Verificar se o usuário tem permissão para aceitar este convite
                $podeAceitar = false;
                if ($usuario['tipo'] === 'aluno' && $convite['idAluno'] == $usuario['id']) {
                    $podeAceitar = true;
                } else if ($usuario['tipo'] === 'personal' && $convite['idPersonal'] == $usuario['id']) {
                    $podeAceitar = true;
                }

                if (!$podeAceitar) {
                    $this->db->rollBack();
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para aceitar este convite']);
                    return;
                }

                // Atualizar status do convite
                $stmt = $this->db->prepare("UPDATE convites SET status = 'aceito' WHERE idConvite = ?");
                $stmt->execute([$idConvite]);

                // Vincular aluno ao personal
                $stmt = $this->db->prepare("UPDATE alunos SET idPersonal = ? WHERE idAluno = ?");
                $stmt->execute([$convite['idPersonal'], $convite['idAluno']]);

                // Criar notificação para o remetente
                $this->criarNotificacaoConviteAceito($convite);

                $this->db->commit();

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Convite aceito com sucesso!'
                ]);

            } catch (PDOException $e) {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Recusar convite
         */
        public function recusarConvite($idConvite)
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                // Buscar convite
                $stmt = $this->db->prepare("SELECT * FROM convites WHERE idConvite = ? AND status = 'pendente'");
                $stmt->execute([$idConvite]);
                $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$convite) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou já respondido']);
                    return;
                }

                // Verificar permissão
                $podeRecusar = false;
                if ($usuario['tipo'] === 'aluno' && $convite['idAluno'] == $usuario['id']) {
                    $podeRecusar = true;
                } else if ($usuario['tipo'] === 'personal' && $convite['idPersonal'] == $usuario['id']) {
                    $podeRecusar = true;
                }

                if (!$podeRecusar) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para recusar este convite']);
                    return;
                }

                // Atualizar status
                $stmt = $this->db->prepare("UPDATE convites SET status = 'recusado' WHERE idConvite = ?");
                $stmt->execute([$idConvite]);

                // Criar notificação
                $this->criarNotificacaoConviteRecusado($convite);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Convite recusado com sucesso'
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Criar notificação quando convite é aceito
         */
        private function criarNotificacaoConviteAceito($convite)
        {
            try {
                $mensagem = "";
                $tipoRemetente = "";

                if ($convite['tipo_remetente'] === 'personal') {
                    // Personal enviou, aluno aceitou - notificar personal
                    $stmt = $this->db->prepare("SELECT nome FROM alunos WHERE idAluno = ?");
                    $stmt->execute([$convite['idAluno']]);
                    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $mensagem = "Aluno {$aluno['nome']} aceitou seu convite!";
                    $tipoRemetente = "Aluno {$aluno['nome']}";
                    $idDestinatario = $convite['idPersonal'];
                    $tipoDestinatario = 'personal';
                } else {
                    // Aluno enviou, personal aceitou - notificar aluno
                    $stmt = $this->db->prepare("SELECT nome FROM personal WHERE idPersonal = ?");
                    $stmt->execute([$convite['idPersonal']]);
                    $personal = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $mensagem = "Personal {$personal['nome']} aceitou seu convite!";
                    $tipoRemetente = "Personal {$personal['nome']}";
                    $idDestinatario = $convite['idAluno'];
                    $tipoDestinatario = 'aluno';
                }

                $stmt = $this->db->prepare("
                    INSERT INTO notificacoes 
                    (idUsuario, tipoUsuario, titulo, mensagem, tipo, lida, data_criacao)
                    VALUES (?, ?, 'Convite Aceito', ?, 'convite_aceito', 0, NOW())
                ");
                $stmt->execute([$idDestinatario, $tipoDestinatario, $mensagem]);

            } catch (Exception $e) {
                error_log("Erro ao criar notificação: " . $e->getMessage());
            }
        }

        /**
         * Criar notificação quando convite é recusado
         */
        private function criarNotificacaoConviteRecusado($convite)
        {
            try {
                $mensagem = "";
                $tipoRemetente = "";

                if ($convite['tipo_remetente'] === 'personal') {
                    // Personal enviou, aluno recusou - notificar personal
                    $stmt = $this->db->prepare("SELECT nome FROM alunos WHERE idAluno = ?");
                    $stmt->execute([$convite['idAluno']]);
                    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $mensagem = "Aluno {$aluno['nome']} recusou seu convite.";
                    $tipoRemetente = "Aluno {$aluno['nome']}";
                    $idDestinatario = $convite['idPersonal'];
                    $tipoDestinatario = 'personal';
                } else {
                    // Aluno enviou, personal recusou - notificar aluno
                    $stmt = $this->db->prepare("SELECT nome FROM personal WHERE idPersonal = ?");
                    $stmt->execute([$convite['idPersonal']]);
                    $personal = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $mensagem = "Personal {$personal['nome']} recusou seu convite.";
                    $tipoRemetente = "Personal {$personal['nome']}";
                    $idDestinatario = $convite['idAluno'];
                    $tipoDestinatario = 'aluno';
                }

                $stmt = $this->db->prepare("
                    INSERT INTO notificacoes 
                    (idUsuario, tipoUsuario, titulo, mensagem, tipo, lida, data_criacao)
                    VALUES (?, ?, 'Convite Recusado', ?, 'convite_recusado', 0, NOW())
                ");
                $stmt->execute([$idDestinatario, $tipoDestinatario, $mensagem]);

            } catch (Exception $e) {
                error_log("Erro ao criar notificação: " . $e->getMessage());
            }
        }

        /**
         * Helper para verificar se usuário está ativo
         */
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

        /**
         * Helper para verificar convite pendente
         */
        private function verificarConvitePendente($idRemetente, $tipoRemetente, $idDestinatario, $tipoDestinatario)
        {
            try {
                $stmt = $this->db->prepare("
                    SELECT idConvite FROM convites 
                    WHERE (
                        (idPersonal = ? AND idAluno = ?) OR 
                        (idPersonal = ? AND idAluno = ?)
                    ) AND status = 'pendente'
                    LIMIT 1
                ");
                
                if ($tipoRemetente === 'personal') {
                    $stmt->execute([$idRemetente, $idDestinatario, $idDestinatario, $idRemetente]);
                } else {
                    $stmt->execute([$idDestinatario, $idRemetente, $idRemetente, $idDestinatario]);
                }

                return $stmt->fetch() !== false;
            } catch (PDOException $e) {
                error_log("Erro ao verificar convite pendente: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Helper para obter usuário do token JWT
         */
        private function getUsuarioFromToken()
        {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (strpos($authHeader, 'Bearer ') === 0) {
                require_once __DIR__ . '/../Config/jwt.config.php';
                $token = str_replace('Bearer ', '', $authHeader);
                
                try {
                    $decoded = decodificarToken($token);
                    return [
                        'id' => $decoded->sub,
                        'tipo' => $decoded->tipo,
                        'email' => $decoded->email
                    ];
                } catch (Exception $e) {
                    error_log("Erro ao decodificar token: " . $e->getMessage());
                    return null;
                }
            }
            
            return null;
        }
    }

?>