<?php
    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Config/jwt.config.php'; // Para autenticação, se necessário

    class ConvitesController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
            // Verifica autenticação (deve ser Personal)
            if (!isset($_SERVER['user']) || $_SERVER['user']->tipo !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas Personais podem criar convites.']);
                exit;
            }
            $this->idPersonal = $_SERVER['user']->id; // ID do Personal logado
        }

        /**
         * Cria um convite para um Aluno (Personal envia).
         * Recebe: email ou idAluno do aluno.
         * Gera token único e link.
         */
        public function criarConvite($data) {
            header('Content-Type: application/json');
            try {
                // Validação
                if (!isset($data['email']) && !isset($data['idAluno'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email ou ID do aluno é obrigatório.']);
                    return;
                }

                $emailAluno = $data['email'] ?? null;
                $idAluno = $data['idAluno'] ?? null;

                // Busca o aluno
                if ($emailAluno) {
                    $stmt = $this->db->prepare("SELECT idAluno FROM alunos WHERE email = ? AND statusPlano != 'Desativado'");
                    $stmt->execute([$emailAluno]);
                    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$aluno) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Aluno não encontrado ou inativo.']);
                        return;
                    }
                    $idAluno = $aluno['idAluno'];
                } else {
                    // Verifica se ID existe e aluno não está associado a outro Personal
                    $stmt = $this->db->prepare("SELECT idAluno FROM alunos WHERE idAluno = ? AND idPersonal IS NULL AND statusPlano != 'Desativado'");
                    $stmt->execute([$idAluno]);
                    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$aluno) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Aluno já associado a outro Personal ou inativo.']);
                        return;
                    }
                }

                // Verifica se já existe solicitação pendente para este par
                $stmt = $this->db->prepare("SELECT idSolicitacao FROM solicitacoes WHERE idPersonal = ? AND idAluno = ? AND status = 'Pendente'");
                $stmt->execute([$this->idPersonal, $idAluno]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Convite já pendente para este aluno.']);
                    return;
                }

                // Gera token único
                $token = bin2hex(random_bytes(32)); // 64 chars seguros

                // Calcula data de expiração (7 dias a partir de agora)
                $dataExpiracao = date('Y-m-d H:i:s', strtotime('+7 days'));

                // Insere solicitação
                $stmt = $this->db->prepare("
                    INSERT INTO solicitacoes (token, data_expiracao, idPersonal, idAluno, status, data_solicitacao) 
                    VALUES (?, ?, ?, ?, 'Pendente', NOW())
                ");
                $success = $stmt->execute([$token, $dataExpiracao, $this->idPersonal, $idAluno]);

                if ($success) {
                    // Gera link (ajuste a base URL da sua API)
                    $baseUrl = $_ENV['APP_URL'] ?? 'https://sua-api.com'; // Defina no .env ou hardcode
                    $link = $baseUrl . '/convites/' . $token;

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Convite criado com sucesso.',
                        'data' => [
                            'token' => $token,
                            'link' => $link,
                            'idAluno' => $idAluno,
                            'expira_em' => $dataExpiracao
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao criar convite.']);
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
         * Acessa o convite via link (Aluno visualiza).
         * Retorna detalhes para o frontend mostrar opções aceitar/negar.
         */
        public function getConvite($token) {
            header('Content-Type: application/json');
            try {
                // Busca solicitação por token (pendente e não expirada)
                $stmt = $this->db->prepare("
                    SELECT s.idSolicitacao, s.status, s.data_solicitacao, s.data_expiracao,
                        p.nome AS nomePersonal, p.email AS emailPersonal,
                        a.nome AS nomeAluno, a.email AS emailAluno
                    FROM solicitacoes s
                    JOIN personal p ON s.idPersonal = p.idPersonal
                    JOIN alunos a ON s.idAluno = a.idAluno
                    WHERE s.token = ? AND s.status = 'Pendente' 
                    AND (s.data_expiracao IS NULL OR s.data_expiracao > NOW())
                ");
                $stmt->execute([$token]);
                $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$solicitacao) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite não encontrado, expirado ou já respondido.']);
                    return;
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Convite encontrado.',
                    'data' => $solicitacao
                ]);
                // Frontend pode mostrar: "Convite de {nomePersonal} para {nomeAluno}. Aceitar ou Negar?"
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        /**
         * Aceita o convite (Aluno aceita).
         * Associa aluno ao personal.
         */
        public function aceitarConvite($token) {
            header('Content-Type: application/json');
            try {
                // Busca e trava a solicitação (para evitar race conditions)
                $this->db->beginTransaction();
                $stmt = $this->db->prepare("
                    SELECT idAluno, idPersonal FROM solicitacoes 
                    WHERE token = ? AND status = 'Pendente' 
                    AND (data_expiracao IS NULL OR data_expiracao > NOW())
                    FOR UPDATE
                ");
                $stmt->execute([$token]);
                $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$solicitacao) {
                    $this->db->rollBack();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou expirado.']);
                    return;
                }

                $idAluno = $solicitacao['idAluno'];
                $idPersonal = $solicitacao['idPersonal'];

                // Atualiza status da solicitação
                $stmt = $this->db->prepare("UPDATE solicitacoes SET status = 'Aceita' WHERE token = ?");
                $stmt->execute([$token]);

                // Associa aluno ao personal
                $stmt = $this->db->prepare("UPDATE alunos SET idPersonal = ?, statusPlano = 'Ativo' WHERE idAluno = ?");
                $stmt->execute([$idPersonal, $idAluno]);

                $this->db->commit();

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Convite aceito! Você agora está associado ao Personal.'
                ]);
            } catch (PDOException $e) {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        /**
         * Nega o convite (Aluno nega).
         */
        public function negarConvite($token) {
            header('Content-Type: application/json');
            try {
                // Busca solicitação
                $stmt = $this->db->prepare("
                    SELECT idSolicitacao FROM solicitacoes 
                    WHERE token = ? AND status = 'Pendente'
                    AND (data_expiracao IS NULL OR data_expiracao > NOW())
                ");
                $stmt->execute([$token]);
                $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$solicitacao) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou expirado.']);
                    return;
                }

                // Atualiza status
                $stmt = $this->db->prepare("UPDATE solicitacoes SET status = 'Rejeitada' WHERE token = ?");
                $stmt->execute([$token]);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Convite negado com sucesso.'
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }
    }
?>