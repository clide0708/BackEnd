<?php
require_once __DIR__ . '/../Config/db.connect.php';
require_once __DIR__ . '/../Config/jwt.config.php';

class AuthController
{
    private $db;

    public function __construct()
    {
        $this->db = DB::connectDB();
    }

    public function login($data)
    {
        try {
            if (!isset($data['email']) || !isset($data['senha'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email e senha são obrigatórios']);
                return;
            }

            $email = $data['email'];
            $senha = $data['senha'];
            $lembrar = isset($data['lembrar']) && $data['lembrar'] === true;

            // busca aluno
            $stmt = $this->db->prepare("SELECT * FROM alunos WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            $tipo = 'aluno';

            // se não encontrou, busca personal
            if (!$usuario) {
                $stmt = $this->db->prepare("SELECT * FROM personal WHERE email = ?");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                $tipo = 'personal';
            }

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                session_start();

                $userData = [
                    'id' => $tipo === 'aluno' ? $usuario['idAluno'] : $usuario['idPersonal'],
                    'tipo' => $tipo,
                    'nome' => $usuario['nome'],
                    'cpf' => $usuario['cpf'],
                    'rg' => $usuario['rg'] ?? null,
                    'email' => $usuario['email'],
                    'status_conta' => $usuario['status_conta'],
                    'numTel' => $usuario['numTel'],
                    'data_cadastro' => $usuario['data_cadastro']
                ];

                if ($tipo === 'personal') {
                    $userData['cref_numero'] = $usuario['cref_numero'];
                    $userData['cref_categoria'] = $usuario['cref_categoria'];
                    $userData['cref_regional'] = $usuario['cref_regional'];
                }

                $_SESSION['usuario'] = $userData;

                $payload = [
                    'sub' => $userData['id'],
                    'tipo' => $tipo,
                    'nome' => $userData['nome'],
                    'email' => $userData['email']
                ];

                $expira = $lembrar ? null : time() + (2 * 60 * 60);
                $token = criarToken($payload, $expira);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'token' => $token,
                    'tipo' => $tipo,
                    'usuario' => $userData,
                    'message' => 'Login realizado com sucesso'
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Email ou senha incorretos']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao realizar login: ' . $e->getMessage()]);
        }
    }

    public function logout()
    {
        session_start();
        session_unset();
        session_destroy();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Logout realizado com sucesso']);
    }

    public function verificarToken()
    {
        try {
            // sempre pega o token do header
            $token = extrairTokenHeader();

            if (!$token) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
                return;
            }

            $decoded = decodificarToken($token);

            if ($decoded) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'usuario' => $decoded,
                    'message' => 'Token válido'
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao verificar token: ' . $e->getMessage()]);
        }
    }

    public function obterUsuarioToken()
    {
        try {
            $token = extrairTokenHeader();
            if (!$token) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
                return;
            }

            $dadosUsuario = obterDadosUsuario($token);

            if ($dadosUsuario) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'usuario' => $dadosUsuario
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao obter dados do usuário: ' . $e->getMessage()]);
        }
    }

    public function verificarAutenticacao()
    {
        try {
            $token = extrairTokenHeader();

            if (tokenValido($token)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Usuário autenticado']);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Não autenticado']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao verificar autenticação: ' . $e->getMessage()]);
        }
    }
}