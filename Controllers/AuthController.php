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
                if (!isset($data['email']) || !isset($data['senha'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email e senha são obrigatórios']);
                    return;
                }

                $email = $data['email'];
                $senha = $data['senha'];
                $lembrar = isset($data['lembrar']) && $data['lembrar'] === true;

                $usuario = null;
                $tipo = null;

                // Busca em alunos
                $stmt = $this->db->prepare("SELECT * FROM alunos WHERE email = ? AND status_conta = 'Ativa'");
                $stmt->execute([$email]);
                if ($foundUser = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $usuario = $foundUser;
                    $tipo = 'aluno';
                }

                // Se não encontrou, busca em personal
                if (!$usuario) {
                    $stmt = $this->db->prepare("SELECT * FROM personal WHERE email = ? AND status_conta = 'Ativa'");
                    $stmt->execute([$email]);
                    if ($foundUser = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $usuario = $foundUser;
                        $tipo = 'personal';
                    }
                }

                // Se não encontrou, busca em academias
                if (!$usuario) {
                    $stmt = $this->db->prepare("SELECT * FROM academias WHERE email = ? AND status_conta = 'Ativa'");
                    $stmt->execute([$email]);
                    if ($foundUser = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $usuario = $foundUser;
                        $tipo = 'academia';
                    }
                }

                // Se não encontrou, busca em devs
                if (!$usuario) {
                    $stmt = $this->db->prepare("SELECT * FROM devs WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($foundUser = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $usuario = $foundUser;
                        $tipo = 'dev';
                    }
                }

                if ($usuario && password_verify($senha, $usuario['senha'])) {
                    session_start();

                    $userData = [];
                    $payload = [];

                    // Construir userData e payload com base no tipo de usuário
                    switch ($tipo) {
                        case 'aluno':
                            $userData = [
                                'id' => $usuario['idAluno'],
                                'tipo' => $tipo,
                                'nome' => $usuario['nome'],
                                'cpf' => $usuario['cpf'],
                                'rg' => $usuario['rg'] ?? null,
                                'email' => $usuario['email'],
                                'numTel' => $usuario['numTel'],
                                'data_cadastro' => $usuario['data_cadastro'],
                                'idPlano' => $usuario['idPlano'],
                                'status_conta' => $usuario['status_conta']
                            ];
                            $payload = [
                                'sub' => $userData['id'],
                                'tipo' => $tipo,
                                'nome' => $userData['nome'],
                                'email' => $userData['email'],
                                'idPlano' => $userData['idPlano']
                            ];
                            break;
                        case 'personal':
                            $userData = [
                                'id' => $usuario['idPersonal'],
                                'tipo' => $tipo,
                                'nome' => $usuario['nome'],
                                'cpf' => $usuario['cpf'],
                                'rg' => $usuario['rg'] ?? null,
                                'cref_numero' => $usuario['cref_numero'],
                                'cref_categoria' => $usuario['cref_categoria'],
                                'cref_regional' => $usuario['cref_regional'],
                                'email' => $usuario['email'],
                                'numTel' => $usuario['numTel'],
                                'data_cadastro' => $usuario['data_cadastro'],
                                'idPlano' => $usuario['idPlano'],
                                'status_conta' => $usuario['status_conta']
                            ];
                            $payload = [
                                'sub' => $userData['id'],
                                'tipo' => $tipo,
                                'nome' => $userData['nome'],
                                'email' => $userData['email'],
                                'cref_numero' => $userData['cref_numero'],
                                'idPlano' => $userData['idPlano']
                            ];
                            break;
                        case 'academia':
                            $userData = [
                                'id' => $usuario['idAcademia'],
                                'tipo' => $tipo,
                                'nome' => $usuario['nome'],
                                'cnpj' => $usuario['cnpj'],
                                'email' => $usuario['email'],
                                'telefone' => $usuario['telefone'] ?? null,
                                'endereco' => $usuario['endereco'] ?? null,
                                'data_cadastro' => $usuario['data_cadastro'],
                                'idPlano' => $usuario['idPlano'],
                                'status_conta' => $usuario['status_conta']
                            ];
                            $payload = [
                                'sub' => $userData['id'],
                                'tipo' => $tipo,
                                'nome' => $userData['nome'],
                                'email' => $userData['email'],
                                'cnpj' => $userData['cnpj'],
                                'idPlano' => $userData['idPlano']
                            ];
                            break;
                        case 'dev':
                            $userData = [
                                'id' => $usuario['idDev'],
                                'tipo' => $tipo,
                                'nome' => $usuario['nome'],
                                'email' => $usuario['email'],
                                'data_cadastro' => $usuario['data_cadastro'],
                                'nivel_acesso' => $usuario['nivel_acesso']
                            ];
                            $payload = [
                                'sub' => $userData['id'],
                                'tipo' => $tipo,
                                'nome' => $userData['nome'],
                                'email' => $userData['email'],
                                'nivel_acesso' => $userData['nivel_acesso']
                            ];
                            break;
                        default:
                            http_response_code(401);
                            echo json_encode(['success' => false, 'error' => 'Tipo de usuário desconhecido']);
                            return;
                    }

                    $_SESSION['usuario'] = $userData;

                    // Passa o parâmetro $lembrar para a função criarToken
                    $token = criarToken($payload, $lembrar);

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
                    echo json_encode(['success' => false, 'error' => 'Email ou senha incorretos ou conta inativa']);
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

        public function verificarToken() {
            try {
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

        public function obterUsuarioToken() {
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

        public function verificarAutenticacao() {
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

?>