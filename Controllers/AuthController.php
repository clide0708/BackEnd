<?php
    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Config/jwt.config.php';

    class AuthController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        public function login($data) {
            try {
                // Validação básica
                if (!isset($data['email']) || !isset($data['senha'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email e senha são obrigatórios']);
                    return;
                }

                $email = $data['email'];
                $senha = $data['senha'];
                $lembrar = isset($data['lembrar']) && $data['lembrar'] === true;

                // Verifica se é aluno
                $stmt = $this->db->prepare("SELECT * FROM alunos WHERE email = ?");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                $tipo = 'aluno';

                // Se não encontrou aluno, verifica se é personal
                if (!$usuario) {
                    $stmt = $this->db->prepare("SELECT * FROM personal WHERE email = ?");
                    $stmt->execute([$email]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    $tipo = 'personal';
                }

                // Verifica se encontrou usuário e se a senha está correta
                if ($usuario && password_verify($senha, $usuario['senha'])) {
                    // Inicia a sessão
                    session_start();
                    
                    // Prepara dados do usuário para a sessão
                    $userData = [
                        'id' => $tipo === 'aluno' ? $usuario['idAluno'] : $usuario['idPersonal'],
                        'tipo' => $tipo,
                        'nome' => $usuario['nome'],
                        'cpf' => $usuario['cpf'],
                        'rg' => $usuario['rg'] ?? null,
                        'email' => $usuario['email'],
                        'statusPlano' => $usuario['statusPlano'],
                        'numTel' => $usuario['numTel'],
                        'data_cadastro' => $usuario['data_cadastro']
                    ];

                    // Adiciona campos específicos para personal
                    if ($tipo === 'personal') {
                        $userData['cref_numero'] = $usuario['cref_numero'];
                        $userData['cref_categoria'] = $usuario['cref_categoria'];
                        $userData['cref_regional'] = $usuario['cref_regional'];
                    }

                    // Armazena na sessão
                    $_SESSION['usuario'] = $userData;

                    // Prepara payload para o token JWT usando a nova função
                    $payload = [
                        'sub' => $userData['id'],
                        'tipo' => $tipo,
                        'nome' => $userData['nome'],
                        'email' => $userData['email']
                    ];

                    // Gera token JWT usando a nova função criarToken()
                    $expira = $lembrar ? null : time() + (2 * 60 * 60); // 2 horas ou null se lembrar
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

        public function logout() {
            session_start();
            session_unset();
            session_destroy();
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Logout realizado com sucesso']);
        }

        public function verificarToken($data) {
            try {
                // Extrai token do parâmetro ou do header
                $token = $data['token'] ?? extrairTokenHeader();
                
                if (!$token) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
                    return;
                }

                // Usa a nova função decodificarToken()
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

        /**
         * Nova função para obter dados do usuário a partir do token
         */
        public function obterUsuarioToken($data) {
            try {
                $token = $data['token'] ?? extrairTokenHeader();
                
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

        /**
         * Função para verificar se o usuário está autenticado
         */
        public function verificarAutenticacao() {
            try {
                $token = extrairTokenHeader();
                
                if (tokenValido($token)) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Usuário autenticado'
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Não autenticado'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao verificar autenticação: ' . $e->getMessage()]);
            }
        }
    }
?>