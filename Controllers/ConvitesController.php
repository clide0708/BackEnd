<?php

require_once __DIR__ . '/../Config/db.connect.php';
require_once __DIR__ . '/../Config/jwt.config.php'; // Para autenticação, se necessário

class ConvitesController
{
    private $db;
    private $idPersonal; // Pode ser nulo se não for um personal logado

    public function __construct()
    {
        $this->db = DB::connectDB();
        // O idPersonal só será definido se a requisição for feita por um personal autenticado
        // Para rotas públicas como getConvite, não haverá $_SERVER['user'] ou será outro tipo
        if (isset($_SERVER['user']) && $_SERVER['user']['tipo'] === 'personal') {
            $this->idPersonal = $_SERVER['user']['sub'];
        }
    }

    /**
     * Cria um convite para um Aluno (Personal envia).
     * Recebe: email ou idAluno do aluno.
     * Gera token único e link.
     */
    public function criarConvite($data)
    {
        header('Content-Type: application/json');
        // Verifica se quem está criando o convite é um personal
        if (!isset($this->idPersonal)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas Personais podem criar convites.']);
            return;
        }

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
                // Verifica se ID existe e aluno não está associado a outro Personal
                $stmt = $this->db->prepare("SELECT idAluno FROM alunos WHERE idAluno = ? AND idPersonal IS NULL AND status_conta = 'Ativa'");
                $stmt->execute([$idAluno]);
                $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$aluno) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Aluno já associado a outro Personal ou inativo.']);
                    return;
                }
            }

            // Verifica se já existe solicitação pendente para este par
            $stmt = $this->db->prepare("SELECT idConvite FROM convites WHERE idPersonal = ? AND idAluno = ? AND status = 'pendente'");
            $stmt->execute([$this->idPersonal, $idAluno]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Convite já pendente para este aluno.']);
                return;
            }

            // Gera token único
            $token = bin2hex(random_bytes(32)); // 64 chars seguros

            // Insere convite na nova tabela `convites`
            $stmt = $this->db->prepare("
                    INSERT INTO convites (token, idPersonal, idAluno, email_aluno, status, data_criacao) 
                    VALUES (?, ?, ?, ?, 'pendente', NOW())
                ");
            $success = $stmt->execute([$token, $this->idPersonal, $idAluno, $emailAluno]);

            if ($success) {
                // Gera link (ajuste a base URL da sua API)
                $baseUrl = $_ENV['APP_URL'] ?? 'https://api.clidefit.com'; // Defina no .env ou hardcode
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
     * Acessa o convite via link (Aluno visualiza).
     * Retorna detalhes para o frontend mostrar opções aceitar/negar.
     */
    public function getConvites($parametro)
    {
        header('Content-Type: application/json');
        try {
            // Determina se o parâmetro é email ou token
            if (filter_var($parametro, FILTER_VALIDATE_EMAIL)) {
                // É um email - busca por email do aluno
                $stmt = $this->db->prepare("
                    SELECT 
                        c.idConvite,
                        c.status,
                        c.data_criacao,
                        p.nome AS nomePersonal,
                        p.email AS emailPersonal,
                        a.nome AS nomeAluno,
                        a.email AS emailAluno,
                        a.idAluno,
                        p.idPersonal
                    FROM convites c
                    JOIN personal p ON c.idPersonal = p.idPersonal
                    LEFT JOIN alunos a ON c.idAluno = a.idAluno
                    WHERE a.email = ? AND c.status = 'pendente'
                    ORDER BY c.data_criacao DESC
                ");
                $stmt->execute([$parametro]);
            } else {
                // É um token - busca por token
                $stmt = $this->db->prepare("
                    SELECT 
                        c.idConvite,
                        c.status,
                        c.data_criacao,
                        p.nome AS nomePersonal,
                        p.email AS emailPersonal,
                        a.nome AS nomeAluno,
                        a.email AS emailAluno,
                        a.idAluno,
                        p.idPersonal
                    FROM convites c
                    JOIN personal p ON c.idPersonal = p.idPersonal
                    LEFT JOIN alunos a ON c.idAluno = a.idAluno
                    WHERE c.token = ? AND c.status = 'pendente'
                    ORDER BY c.data_criacao DESC
                ");
                $stmt->execute([$parametro]);
            }
            
            $convites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($convites)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Nenhum convite pendente encontrado.'
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
    // public function getConvites($emailAluno)
    // {
    //     header('Content-Type: application/json');
    //     try {
    //         // busca todos os convites pendentes pelo email do aluno
    //         $stmt = $this->db->prepare("
    //         SELECT 
    //             c.idConvite,
    //             c.status,
    //             c.data_criacao,
    //             p.nome AS nomePersonal,
    //             p.email AS emailPersonal,
    //             a.nome AS nomeAluno,
    //             a.email AS emailAluno,
    //             a.idAluno,
    //             p.idPersonal
    //         FROM convites c
    //         JOIN personal p ON c.idPersonal = p.idPersonal
    //         LEFT JOIN alunos a ON c.idAluno = a.idAluno
    //         WHERE a.email = ? AND c.status = 'pendente'
    //         ORDER BY c.data_criacao DESC
    //     ");
    //         $stmt->execute([$emailAluno]);
    //         $convites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //         if (empty($convites)) {
    //             http_response_code(404);
    //             echo json_encode([
    //                 'success' => false,
    //                 'error' => 'Nenhum convite pendente encontrado para este aluno.'
    //             ]);
    //             return;
    //         }

    //         http_response_code(200);
    //         echo json_encode([
    //             'success' => true,
    //             'message' => 'Convites encontrados.',
    //             'data' => $convites
    //         ]);
    //     } catch (PDOException $e) {
    //         http_response_code(500);
    //         echo json_encode([
    //             'success' => false,
    //             'error' => 'Erro no banco: ' . $e->getMessage()
    //         ]);
    //     }
    // }

    /**
     * Busca convites por email do aluno
     */
    public function getConvitesByEmail($email)
    {
        header('Content-Type: application/json');
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.idConvite,
                    c.status,
                    c.data_criacao,
                    p.nome AS nomePersonal,
                    p.email AS emailPersonal,
                    a.nome AS nomeAluno,
                    a.email AS emailAluno,
                    a.idAluno,
                    p.idPersonal
                FROM convites c
                JOIN personal p ON c.idPersonal = p.idPersonal
                LEFT JOIN alunos a ON c.idAluno = a.idAluno
                WHERE a.email = ? AND c.status = 'pendente'
                ORDER BY c.data_criacao DESC
            ");
            $stmt->execute([$email]);
            $convites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($convites)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Nenhum convite pendente encontrado para este email.'
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
     * Aceita o convite (Aluno aceita).
     * Associa aluno ao personal.
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

            // busca e trava o convite
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
