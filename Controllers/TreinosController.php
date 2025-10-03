<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Config/jwt.config.php';

    class TreinosController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        // Criar treino - obrigatório: nome, tipo, criadoPor, idAluno ou idPersonal
        public function criarTreino($data) {
            $nome = trim($data['nome'] ?? '');
            $descricao = $data['descricao'] ?? null;
            $criadoPor = trim($data['criadoPor'] ?? '');
            $idAluno = isset($data['idAluno']) ? (int)$data['idAluno'] : null;
            $idPersonal = isset($data['idPersonal']) ? (int)$data['idPersonal'] : null;
            $tipo = $data['tipo'] ?? null;

            if (empty($nome) || empty($criadoPor) || empty($tipo)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nome, tipo e criadoPor são obrigatórios']);
                return;
            }

            if (is_null($idAluno) && is_null($idPersonal)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Informe idAluno ou idPersonal']);
                return;
            }

            // Validar se tipo está na lista permitida
            $tiposValidos = ['Musculação','CrossFit','Calistenia','Pilates','Aquecimento','Treino Específico','Outros'];
            if (!in_array($tipo, $tiposValidos)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Tipo inválido. Use: ' . implode(', ', $tiposValidos)]);
                return;
            }

            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $this->db->prepare("INSERT INTO treinos (idAluno, idPersonal, criadoPor, nome, tipo, descricao, data_criacao, data_ultima_modificacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$idAluno, $idPersonal, $criadoPor, $nome, $tipo, $descricao, $now, $now]);
                $idTreino = $this->db->lastInsertId();

                http_response_code(201);
                echo json_encode(['success' => true, 'idTreino' => $idTreino, 'message' => 'Treino criado com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao criar treino: ' . $e->getMessage()]);
            }
        }

        // Adicionar exercício ao treino
        public function adicionarExercicioAoTreino($idTreino, $exercicioData) {
            $idTreino = (int)$idTreino;
            
            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            // Verificar se treino existe
            $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
            $stmt->execute([$idTreino]);
            $treino = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$treino) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                return;
            }

            // Verificar permissão: o usuário deve ser o criador (idAluno ou idPersonal) e email deve bater com criadoPor
            $usuarioValido = false;
            
            // Se o treino pertence a um aluno
            if (!is_null($treino['idAluno']) && isset($usuario['idAluno']) && $usuario['idAluno'] == $treino['idAluno'] && $usuario['email'] == $treino['criadoPor']) {
                $usuarioValido = true;
            }
            
            // Se o treino pertence a um personal
            if (!is_null($treino['idPersonal']) && isset($usuario['idPersonal']) && $usuario['idPersonal'] == $treino['idPersonal'] && $usuario['email'] == $treino['criadoPor']) {
                $usuarioValido = true;
            }

            if (!$usuarioValido) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para modificar este treino']);
                return;
            }

            // Validar que pelo menos um entre idExercicio ou idExercAdaptado seja informado
            if (empty($exercicioData['idExercicio']) && empty($exercicioData['idExercAdaptado'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Informe idExercicio ou idExercAdaptado']);
                return;
            }

            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $this->db->prepare("INSERT INTO treino_exercicio (idTreino, idExercicio, idExercAdaptado, data_criacao, data_ultima_modificacao, series, repeticoes, carga, ordem, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $idTreino,
                    $exercicioData['idExercicio'] ?? null,
                    $exercicioData['idExercAdaptado'] ?? null,
                    $now,
                    $now,
                    $exercicioData['series'] ?? null,
                    $exercicioData['repeticoes'] ?? null,
                    $exercicioData['carga'] ?? null,
                    $exercicioData['ordem'] ?? null,
                    $exercicioData['observacoes'] ?? null
                ]);

                // Atualiza data_ultima_modificacao do treino
                $stmtUpdate = $this->db->prepare("UPDATE treinos SET data_ultima_modificacao = ? WHERE idTreino = ?");
                $stmtUpdate->execute([$now, $idTreino]);

                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Exercício adicionado ao treino']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao adicionar exercício: ' . $e->getMessage()]);
            }
        }

        // Listar exercícios de um treino
        public function listarExerciciosDoTreino($idTreino) {
            $idTreino = (int)$idTreino;
            
            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            try {
                $stmt = $this->db->prepare("
                    SELECT te.*,
                        e.nome as nomeExercicio,
                        e.grupoMuscular as grupoMuscularExercicio,
                        e.descricao as descricaoExercicio,
                        ea.nome as nomeExercAdaptado,
                        ea.grupoMuscular as grupoMuscularExercAdaptado,
                        ea.descricao as descricaoExercAdaptado,
                        v.url as video_url
                    FROM treino_exercicio te
                    LEFT JOIN exercicios e ON te.idExercicio = e.idExercicio
                    LEFT JOIN exercadaptados ea ON te.idExercAdaptado = ea.idExercAdaptado
                    LEFT JOIN videos v ON (te.idExercicio = v.idExercicio OR te.idExercAdaptado = v.idExercAdaptado)
                    WHERE te.idTreino = ?
                    ORDER BY te.ordem
                ");
                $stmt->execute([$idTreino]);
                $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success' => true, 'exercicios' => $exercicios]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao listar exercícios: ' . $e->getMessage()]);
            }
        }

        // Atualizar exercício no treino
        public function atualizarExercicioNoTreino($idTreinoExercicio, $data) {
            $idTreinoExercicio = (int)$idTreinoExercicio;
            
            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            try {
                // Verificar se o exercício pertence a um treino do usuário
                $stmt = $this->db->prepare("
                    SELECT t.* 
                    FROM treino_exercicio te 
                    JOIN treinos t ON te.idTreino = t.idTreino 
                    WHERE te.idTreino_Exercicio = ?
                ");
                $stmt->execute([$idTreinoExercicio]);
                $treinoExercicio = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$treinoExercicio) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Exercício não encontrado no treino']);
                    return;
                }

                // Verificar permissão
                $usuarioValido = false;
                if (!is_null($treinoExercicio['idAluno']) && $usuario['idAluno'] == $treinoExercicio['idAluno'] && $usuario['email'] == $treinoExercicio['criadoPor']) {
                    $usuarioValido = true;
                }
                if (!is_null($treinoExercicio['idPersonal']) && $usuario['idPersonal'] == $treinoExercicio['idPersonal'] && $usuario['email'] == $treinoExercicio['criadoPor']) {
                    $usuarioValido = true;
                }

                if (!$usuarioValido) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para editar este exercício']);
                    return;
                }

                // Preparar campos para atualização
                $campos = [];
                $valores = [];

                if (isset($data['series'])) {
                    $campos[] = "series = ?";
                    $valores[] = $data['series'];
                }

                if (isset($data['repeticoes'])) {
                    $campos[] = "repeticoes = ?";
                    $valores[] = $data['repeticoes'];
                }

                if (isset($data['carga'])) {
                    $campos[] = "carga = ?";
                    $valores[] = $data['carga'];
                }

                if (isset($data['ordem'])) {
                    $campos[] = "ordem = ?";
                    $valores[] = $data['ordem'];
                }

                if (isset($data['observacoes'])) {
                    $campos[] = "observacoes = ?";
                    $valores[] = $data['observacoes'];
                }

                if (empty($campos)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nenhum campo para atualizar']);
                    return;
                }

                $campos[] = "data_ultima_modificacao = ?";
                $valores[] = date('Y-m-d H:i:s');

                $valores[] = $idTreinoExercicio;

                $sql = "UPDATE treino_exercicio SET " . implode(', ', $campos) . " WHERE idTreino_Exercicio = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);

                // Atualizar data_ultima_modificacao do treino
                $stmtUpdate = $this->db->prepare("UPDATE treinos SET data_ultima_modificacao = ? WHERE idTreino = ?");
                $stmtUpdate->execute([date('Y-m-d H:i:s'), $treinoExercicio['idTreino']]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Exercício atualizado com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar exercício: ' . $e->getMessage()]);
            }
        }

        // Remover exercício do treino
        public function removerExercicioDoTreino($idTreinoExercicio) {
            $idTreinoExercicio = (int)$idTreinoExercicio;
            
            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            try {
                // Verificar se o exercício pertence a um treino do usuário
                $stmt = $this->db->prepare("
                    SELECT t.* 
                    FROM treino_exercicio te 
                    JOIN treinos t ON te.idTreino = t.idTreino 
                    WHERE te.idTreino_Exercicio = ?
                ");
                $stmt->execute([$idTreinoExercicio]);
                $treinoExercicio = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$treinoExercicio) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Exercício não encontrado no treino']);
                    return;
                }

                // Verificar permissão
                $usuarioValido = false;
                if (!is_null($treinoExercicio['idAluno']) && $usuario['idAluno'] == $treinoExercicio['idAluno'] && $usuario['email'] == $treinoExercicio['criadoPor']) {
                    $usuarioValido = true;
                }
                if (!is_null($treinoExercicio['idPersonal']) && $usuario['idPersonal'] == $treinoExercicio['idPersonal'] && $usuario['email'] == $treinoExercicio['criadoPor']) {
                    $usuarioValido = true;
                }

                if (!$usuarioValido) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para remover este exercício']);
                    return;
                }

                // Remover exercício
                $stmt = $this->db->prepare("DELETE FROM treino_exercicio WHERE idTreino_Exercicio = ?");
                $stmt->execute([$idTreinoExercicio]);

                // Atualizar data_ultima_modificacao do treino
                $stmtUpdate = $this->db->prepare("UPDATE treinos SET data_ultima_modificacao = ? WHERE idTreino = ?");
                $stmtUpdate->execute([date('Y-m-d H:i:s'), $treinoExercicio['idTreino']]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Exercício removido do treino com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao remover exercício: ' . $e->getMessage()]);
            }
        }

        // Buscar treino completo com exercícios e vídeos
        public function buscarTreinoCompleto($idTreino) {
            $idTreino = (int)$idTreino;

            try {
                // Buscar informações básicas do treino
                $stmtTreino = $this->db->prepare("
                    SELECT t.*, 
                        p.nome as nomePersonal,
                        a.nome as nomeAluno
                    FROM treinos t
                    LEFT JOIN personal p ON t.idPersonal = p.idPersonal
                    LEFT JOIN alunos a ON t.idAluno = a.idAluno
                    WHERE t.idTreino = ?
                ");
                $stmtTreino->execute([$idTreino]);
                $treino = $stmtTreino->fetch(PDO::FETCH_ASSOC);

                if (!$treino) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                    return;
                }

                // Buscar exercícios do treino
                $stmtExercicios = $this->db->prepare("
                    SELECT te.*,
                        e.nome as nomeExercicio,
                        e.grupoMuscular as grupoMuscularExercicio,
                        e.descricao as descricaoExercicio,
                        ea.nome as nomeExercAdaptado,
                        ea.grupoMuscular as grupoMuscularExercAdaptado,
                        ea.descricao as descricaoExercAdaptado,
                        v.url as video_url
                    FROM treino_exercicio te
                    LEFT JOIN exercicios e ON te.idExercicio = e.idExercicio
                    LEFT JOIN exercadaptados ea ON te.idExercAdaptado = ea.idExercAdaptado
                    LEFT JOIN videos v ON (te.idExercicio = v.idExercicio OR te.idExercAdaptado = v.idExercAdaptado)
                    WHERE te.idTreino = ?
                    ORDER BY te.ordem
                ");
                $stmtExercicios->execute([$idTreino]);
                $exercicios = $stmtExercicios->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'treino' => $treino,
                    'exercicios' => $exercicios
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao buscar treino completo: ' . $e->getMessage()]);
            }
        }

        // Método auxiliar para obter usuário do token JWT
        private function obterUsuarioDoToken() {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                
                try {
                    $decoded = decodificarToken($token);
                    if ($decoded) {
                        $usuario = (array)$decoded;
                        
                        // Mapear campos do token para a estrutura esperada
                        $usuarioMapeado = [
                            'email' => $usuario['email'] ?? '',
                            'tipo' => $usuario['tipo'] ?? '',
                            'nome' => $usuario['nome'] ?? ''
                        ];
                        
                        // Mapear IDs baseado no tipo de usuário
                        if ($usuario['tipo'] === 'personal') {
                            $usuarioMapeado['idPersonal'] = $usuario['sub'] ?? null;
                        } elseif ($usuario['tipo'] === 'aluno') {
                            $usuarioMapeado['idAluno'] = $usuario['sub'] ?? null;
                        } elseif ($usuario['tipo'] === 'academia') {
                            $usuarioMapeado['idAcademia'] = $usuario['sub'] ?? null;
                        }
                        
                        error_log("Usuário mapeado do token: " . print_r($usuarioMapeado, true));
                        return $usuarioMapeado;
                    }
                } catch (Exception $e) {
                    error_log("Erro ao decodificar token: " . $e->getMessage());
                    return null;
                }
            }
            
            error_log("Token não encontrado no header");
            return null;
        }
    }

?>