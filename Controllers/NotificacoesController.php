<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class NotificacoesController
    {
        private $db;

        public function __construct()
        {
            $this->db = DB::connectDB();
        }

        private function buscarConvitesPendentesComoNotificacoes($usuario)
        {
            try {
                if ($usuario['tipo'] === 'aluno') {
                    $sql = "
                        SELECT 
                            c.idConvite as idNotificacao,
                            c.idPersonal as idUsuario,
                            'personal' as tipoUsuario,
                            'Novo Convite' as titulo,
                            CONCAT('Personal ', p.nome, ' enviou um convite para você!') as mensagem,
                            'novo_convite' as tipo,
                            0 as lida,
                            c.data_criacao as data_criacao,
                            c.idConvite,
                            c.tipo_remetente,
                            c.idPersonal,
                            c.idAluno,
                            'convite' as origem,
                            p.nome as nome_remetente,
                            p.foto_url as foto_remetente
                        FROM convites c
                        JOIN personal p ON c.idPersonal = p.idPersonal
                        WHERE c.idAluno = ? AND c.status = 'pendente'
                    ";
                    $params = [$usuario['id']];
                } else {
                    $sql = "
                        SELECT 
                            c.idConvite as idNotificacao,
                            c.idAluno as idUsuario,
                            'aluno' as tipoUsuario,
                            'Novo Convite' as titulo,
                            CONCAT('Aluno ', a.nome, ' enviou um convite para você!') as mensagem,
                            'novo_convite' as tipo,
                            0 as lida,
                            c.data_criacao as data_criacao,
                            c.idConvite,
                            c.tipo_remetente,
                            c.idPersonal,
                            c.idAluno,
                            'convite' as origem,
                            a.nome as nome_remetente,
                            a.foto_url as foto_remetente
                        FROM convites c
                        JOIN alunos a ON c.idAluno = a.idAluno
                        WHERE c.idPersonal = ? AND c.status = 'pendente'
                    ";
                    $params = [$usuario['id']];
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                error_log("Erro ao buscar convites como notificações: " . $e->getMessage());
                return [];
            }
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

                // Buscar notificações tradicionais
                $stmt = $this->db->prepare("
                    SELECT 
                        idNotificacao,
                        idUsuario,
                        tipoUsuario,
                        titulo,
                        mensagem,
                        tipo,
                        lida,
                        data_criacao,
                        NULL as idConvite,
                        NULL as tipo_remetente,
                        NULL as idPersonal,
                        NULL as idAluno,
                        'notificacao' as origem
                    FROM notificacoes 
                    WHERE idUsuario = ? AND tipoUsuario = ?
                    ORDER BY data_criacao DESC
                    LIMIT 50
                ");
                $stmt->execute([$usuario['id'], $usuario['tipo']]);
                $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Buscar convites pendentes como notificações
                $convitesComoNotificacoes = $this->buscarConvitesPendentesComoNotificacoes($usuario);
                
                // Combinar notificações
                $todasNotificacoes = array_merge($convitesComoNotificacoes, $notificacoes);
                
                // Ordenar por data (mais recente primeiro)
                usort($todasNotificacoes, function($a, $b) {
                    return strtotime($b['data_criacao']) - strtotime($a['data_criacao']);
                });

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $todasNotificacoes
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

                // Verificar se é uma notificação normal ou convite
                $stmt = $this->db->prepare("
                    SELECT idNotificacao FROM notificacoes 
                    WHERE idNotificacao = ? AND idUsuario = ? AND tipoUsuario = ?
                ");
                $stmt->execute([$idNotificacao, $usuario['id'], $usuario['tipo']]);
                $notificacao = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($notificacao) {
                    // É uma notificação normal - marcar como lida
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
                } else {
                    // Pode ser um convite - não fazemos nada pois convites são "lidos" quando respondidos
                    echo json_encode([
                        'success' => true,
                        'message' => 'Convite processado'
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

                // Contar notificações tradicionais não lidas
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total 
                    FROM notificacoes 
                    WHERE idUsuario = ? AND tipoUsuario = ? AND lida = 0
                ");
                $stmt->execute([$usuario['id'], $usuario['tipo']]);
                $notificacoesCount = $stmt->fetch(PDO::FETCH_ASSOC);

                // Contar convites pendentes
                if ($usuario['tipo'] === 'aluno') {
                    $sql = "SELECT COUNT(*) as total FROM convites WHERE idAluno = ? AND status = 'pendente'";
                } else {
                    $sql = "SELECT COUNT(*) as total FROM convites WHERE idPersonal = ? AND status = 'pendente'";
                }
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$usuario['id']]);
                $convitesCount = $stmt->fetch(PDO::FETCH_ASSOC);

                $total = (int)$notificacoesCount['total'] + (int)$convitesCount['total'];

                echo json_encode([
                    'success' => true,
                    'total' => $total,
                    'detalhes' => [
                        'notificacoes' => (int)$notificacoesCount['total'],
                        'convites' => (int)$convitesCount['total']
                    ]
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