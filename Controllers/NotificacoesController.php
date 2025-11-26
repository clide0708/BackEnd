<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class NotificacoesController
    {
        private $db;

        public function __construct()
        {
            $this->db = DB::connectDB();
        }

        /**
         * Listar notificações do usuário
         */
        public function listarNotificacoes()
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                $stmt = $this->db->prepare("
                    SELECT * FROM notificacoes 
                    WHERE idUsuario = ? AND tipoUsuario = ?
                    ORDER BY data_criacao DESC
                    LIMIT 50
                ");
                $stmt->execute([$usuario['id'], $usuario['tipo']]);
                $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $notificacoes
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
         * Marcar notificação como lida
         */
        public function marcarComoLida($idNotificacao)
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                $stmt = $this->db->prepare("
                    UPDATE notificacoes 
                    SET lida = 1 
                    WHERE idNotificacao = ? AND idUsuario = ? AND tipoUsuario = ?
                ");
                $stmt->execute([$idNotificacao, $usuario['id'], $usuario['tipo']]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificação marcada como lida'
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Notificação não encontrada'
                    ]);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Marcar todas as notificações como lidas
         */
        public function marcarTodasComoLidas()
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                $stmt = $this->db->prepare("
                    UPDATE notificacoes 
                    SET lida = 1 
                    WHERE idUsuario = ? AND tipoUsuario = ? AND lida = 0
                ");
                $stmt->execute([$usuario['id'], $usuario['tipo']]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Todas as notificações marcadas como lidas'
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
         * Obter contador de notificações não lidas
         */
        public function contadorNotificacoes()
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total 
                    FROM notificacoes 
                    WHERE idUsuario = ? AND tipoUsuario = ? AND lida = 0
                ");
                $stmt->execute([$usuario['id'], $usuario['tipo']]);
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'total' => (int)$resultado['total']
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

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