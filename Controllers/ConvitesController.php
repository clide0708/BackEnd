<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Config/jwt.config.php';

    class ConvitesController
    {
        private $db;
        private $idPersonal;

        public function __construct()
        {
            $this->db = DB::connectDB();
            if (isset($_SERVER['user']) && $_SERVER['user']['tipo'] === 'personal') {
                $this->idPersonal = $_SERVER['user']['sub'];
            }
        }

        /**
         * Cria um convite para um Aluno (Personal envia)
         */
        public function criarConvite($data)
        {
            header('Content-Type: application/json');
            if (!isset($this->idPersonal)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas Personais podem criar convites.']);
                return;
            }

            try {
                if (!isset($data['email']) && !isset($data['idAluno'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email ou ID do aluno é obrigatório.']);
                    return;
                }

                $emailAluno = $data['email'] ?? null;
                $idAluno = $data['idAluno'] ?? null;

                // Busca o aluno
                if ($emailAluno) {
                    $stmt = $this->db->prepare("SELECT idAluno FROM alunos WHERE email = ? AND status_conta = 'Ativa'");
                    $stmt->execute([$emailAluno]);
                    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$aluno) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Aluno não encontrado ou inativo.']);
                        return;
                    }
                    $idAluno = $aluno['idAluno'];
                } else {
                    $stmt = $this->db->prepare("SELECT idAluno FROM alunos WHERE idAluno = ? AND idPersonal IS NULL AND status_conta = 'Ativa'");
                    $stmt->execute([$idAluno]);
                    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$aluno) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Aluno já associado a outro Personal ou inativo.']);
                        return;
                    }
                }

                // Verifica se já existe solicitação pendente
                $stmt = $this->db->prepare("SELECT idConvite FROM convites WHERE idPersonal = ? AND idAluno = ? AND status = 'pendente'");
                $stmt->execute([$this->idPersonal, $idAluno]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Convite já pendente para este aluno.']);
                    return;
                }

                // Gera token único
                $token = bin2hex(random_bytes(32));

                // Insere convite
                $stmt = $this->db->prepare("
                    INSERT INTO convites (token, idPersonal, idAluno, email_aluno, status, data_criacao) 
                    VALUES (?, ?, ?, ?, 'pendente', NOW())
                ");
                $success = $stmt->execute([$token, $this->idPersonal, $idAluno, $emailAluno]);

                if ($success) {
                    $baseUrl = $_ENV['APP_URL'] ?? 'https://api.clidefit.com';
                    $link = $baseUrl . '/convites/' . $token;

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Convite criado com sucesso.',
                        'data' => [
                            'token' => $token,
                            'link' => $link,
                            'idAluno' => $idAluno,
                            'idPersonal' => $this->idPersonal
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
         * Acessa o convite via link (Aluno visualiza um convite específico)
         */
        public function getConvite($token)
        {
            header('Content-Type: application/json');
            try {
                $stmt = $this->db->prepare("
                    SELECT c.idConvite, c.status, c.data_criacao,
                        p.nome AS nomePersonal, p.email AS emailPersonal,
                        a.nome AS nomeAluno, a.email AS emailAluno, a.idAluno, p.idPersonal
                    FROM convites c
                    JOIN personal p ON c.idPersonal = p.idPersonal
                    LEFT JOIN alunos a ON c.idAluno = a.idAluno
                    WHERE c.token = ? AND c.status = 'pendente'
                ");
                $stmt->execute([$token]);
                $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$convite) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite não encontrado ou já respondido.']);
                    return;
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Convite encontrado.',
                    'data' => $convite
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
            }
        }

        /**
         * Lista todos os convites pendentes de um aluno por email
         */
        public function getConvites($emailAluno)
        {
            header('Content-Type: application/json');
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        c.idConvite, c.token, c.status, c.data_criacao,
                        p.nome AS nomePersonal, p.email AS emailPersonal,
                        a.nome AS nomeAluno, a.email AS emailAluno,
                        a.idAluno, p.idPersonal
                    FROM convites c
                    JOIN personal p ON c.idPersonal = p.idPersonal
                    LEFT JOIN alunos a ON c.idAluno = a.idAluno
                    WHERE a.email = ? AND c.status = 'pendente'
                    ORDER BY c.data_criacao DESC
                ");
                $stmt->execute([$emailAluno]);
                $convites = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($convites)) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Nenhum convite pendente encontrado para este aluno.'
                    ]);
                    return;
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Convites encontrados.',
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
         * Aceita o convite (por token - para links)
         */
        public function aceitarConviteToken($token)
        {
            header('Content-Type: application/json');
            if (!isset($_SERVER['user']) || $_SERVER['user']['tipo'] !== 'aluno') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas Alunos podem aceitar convites.']);
                return;
            }
            $idAlunoLogado = $_SERVER['user']['sub'];

            try {
                $this->db->beginTransaction();
                $stmt = $this->db->prepare("
                    SELECT idAluno, idPersonal FROM convites 
                    WHERE token = ? AND status = 'pendente'
                    FOR UPDATE
                ");
                $stmt->execute([$token]);
                $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$convite) {
                    $this->db->rollBack();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou já respondido.']);
                    return;
                }

                if ($convite['idAluno'] != $idAlunoLogado) {
                    $this->db->rollBack();
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para aceitar este convite.']);
                    return;
                }

                $idAluno = $convite['idAluno'];
                $idPersonal = $convite['idPersonal'];

                $stmt = $this->db->prepare("UPDATE convites SET status = 'aceito' WHERE token = ?");
                $stmt->execute([$token]);

                $stmt = $this->db->prepare("UPDATE alunos SET idPersonal = ?, status_vinculo = 'Ativo' WHERE idAluno = ?");
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
         * Aceita o convite (por idConvite - para interface)
         */
        public function aceitarConvite($idConvite)
        {
            header('Content-Type: application/json');
            if (!isset($_SERVER['user']) || $_SERVER['user']['tipo'] !== 'aluno') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas Alunos podem aceitar convites.']);
                return;
            }
            $idAlunoLogado = $_SERVER['user']['sub'];

            try {
                $this->db->beginTransaction();
                $stmt = $this->db->prepare("
                    SELECT idAluno, idPersonal FROM convites 
                    WHERE idConvite = ? AND status = 'pendente'
                    FOR UPDATE
                ");
                $stmt->execute([$idConvite]);
                $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$convite) {
                    $this->db->rollBack();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou já respondido.']);
                    return;
                }

                if ($convite['idAluno'] != $idAlunoLogado) {
                    $this->db->rollBack();
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para aceitar este convite.']);
                    return;
                }

                $idAluno = $convite['idAluno'];
                $idPersonal = $convite['idPersonal'];

                $stmt = $this->db->prepare("UPDATE convites SET status = 'aceito' WHERE idConvite = ?");
                $stmt->execute([$idConvite]);

                $stmt = $this->db->prepare("UPDATE alunos SET idPersonal = ?, status_vinculo = 'Ativo' WHERE idAluno = ?");
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
         * Nega o convite (por token - para links)
         */
        public function negarConviteToken($token)
        {
            header('Content-Type: application/json');
            if (!isset($_SERVER['user']) || $_SERVER['user']['tipo'] !== 'aluno') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas Alunos podem negar convites.']);
                return;
            }
            $idAlunoLogado = $_SERVER['user']['sub'];

            try {
                $stmt = $this->db->prepare("
                    SELECT idAluno FROM convites 
                    WHERE token = ? AND status = 'pendente'
                ");
                $stmt->execute([$token]);
                $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$convite) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou já respondido.']);
                    return;
                }

                if ($convite['idAluno'] != $idAlunoLogado) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para negar este convite.']);
                    return;
                }

                $stmt = $this->db->prepare("UPDATE convites SET status = 'negado' WHERE token = ?");
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

        /**
         * Nega o convite (por idConvite - para interface)
         */
        public function negarConvite($idConvite)
        {
            header('Content-Type: application/json');
            if (!isset($_SERVER['user']) || $_SERVER['user']['tipo'] !== 'aluno') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas Alunos podem negar convites.']);
                return;
            }
            $idAlunoLogado = $_SERVER['user']['sub'];

            try {
                $stmt = $this->db->prepare("SELECT idAluno FROM convites WHERE idConvite = ? AND status = 'pendente'");
                $stmt->execute([$idConvite]);
                $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$convite) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Convite inválido ou já respondido.']);
                    return;
                }

                if ($convite['idAluno'] != $idAlunoLogado) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para negar este convite.']);
                    return;
                }

                $stmt = $this->db->prepare("UPDATE convites SET status = 'negado' WHERE idConvite = ?");
                $stmt->execute([$idConvite]);

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