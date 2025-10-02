<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class ExerciciosController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        public function buscarTodosExercicios() {
            try {
                $stmt = $this->db->query("SELECT * FROM exercicios");
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode($result);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["error" => "Erro ao buscar exercícios: " . $e->getMessage()]);
            }
        }

        // Buscar exercício por ID, exemplo: /exercicios/buscarPorId=1
        public function buscarPorID($id) {
            try {
                // Se o ID veio como array (pode acontecer com alguns tipos de parâmetros)
                if (is_array($id)) {
                    $id = $id['id'] ?? $id[0] ?? null;
                }
                
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(["error" => "ID não fornecido"]);
                    return;
                }

                $stmt = $this->db->prepare("SELECT * FROM exercicios WHERE idExercicio = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    http_response_code(200);
                    echo json_encode($result);
                } else {
                    http_response_code(404);
                    echo json_encode(["error" => "Exercício não encontrado"]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["error" => "Erro ao buscar exercício: " . $e->getMessage()]);
            }
        }

        public function buscarPorNome($nome) {
            try {
                $stmt = $this->db->prepare("SELECT * FROM exercicios WHERE nome LIKE ?");
                $stmt->execute(["%$nome%"]);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($result) {
                    http_response_code(200);
                    echo json_encode($result);
                } else {
                    http_response_code(404);
                    echo json_encode(["error" => "Nenhum exercício encontrado com esse nome"]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["error" => "Erro ao buscar exercício: " . $e->getMessage()]);
            }
        }

        public function cadastrarExercicio($data) {
            try {
                // Validação básica
                if (!isset($data['nome']) || !isset($data['descricao']) || !isset($data['grupoMuscular']) || !isset($data['cadastradoPor'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                    return;
                }

                // CORREÇÃO: Adicionar o quarto placeholder para cadastradoPor
                $stmt = $this->db->prepare("INSERT INTO exercicios (nome, grupoMuscular, descricao, cadastradoPor) VALUES (?, ?, ?, ?)");
                $success = $stmt->execute([
                    $data['nome'], 
                    $data['grupoMuscular'], 
                    $data['descricao'], 
                    $data['cadastradoPor']
                ]);
                
                if ($success) {
                    http_response_code(201);
                    echo json_encode(['success' => true, 'idExercicio' => $this->db->lastInsertId()]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Falha ao cadastrar exercício']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar exercício: ' . $e->getMessage()]);
            }
        }

        public function atualizarExercicio($id, $data) {
            try {
                // Se o ID veio como array (pode acontecer com alguns tipos de parâmetros)
                if (is_array($id)) {
                    $id = $id['id'] ?? $id[0] ?? null;
                }
                
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
                    return;
                }

                $stmt = $this->db->prepare("UPDATE exercicios SET nome = ?, grupoMuscular = ?, descricao = ?, cadastradoPor = ? WHERE idExercicio = ?");
                $success = $stmt->execute([
                    $data['nome'], 
                    $data['grupoMuscular'], 
                    $data['descricao'], 
                    $data['cadastradoPor'],
                    $id // CORREÇÃO: Adicionar o ID como quinto parâmetro
                ]);
                
                if ($success && $stmt->rowCount() > 0) {
                    http_response_code(200);
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Exercício não encontrado ou dados idênticos']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar exercício: ' . $e->getMessage()]);
            }
        }

        public function deletarExercicio($id) {
            try {
                // Se o ID veio como array (pode acontecer com alguns tipos de parâmetros)
                if (is_array($id)) {
                    $id = $id['id'] ?? $id[0] ?? null;
                }
                
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
                    return;
                }

                $stmt = $this->db->prepare("DELETE FROM exercicios WHERE idExercicio = ?");
                $success = $stmt->execute([$id]);
                
                if ($success && $stmt->rowCount() > 0) {
                    http_response_code(200);
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Exercício não encontrado']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao deletar exercício: ' . $e->getMessage()]);
            }
        }

        // Novo método para buscar exercício com vídeos
        public function buscarExercicioComVideos($tipo, $id) {
            /*
            $tipo: 'normal' ou 'adaptado'
            $id: idExercicio ou idExercAdaptado
            Retorna dados do exercício + array de vídeos associados
            */

            try {
                if ($tipo === 'normal') {
                    // Busca exercício normal
                    $stmtEx = $this->db->prepare("SELECT * FROM exercicios WHERE idExercicio = ?");
                    $stmtEx->execute([$id]);
                    $exercicio = $stmtEx->fetch(PDO::FETCH_ASSOC);

                    if (!$exercicio) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Exercício não encontrado']);
                        return;
                    }

                    // Busca vídeos associados
                    $stmtVid = $this->db->prepare("SELECT * FROM videos WHERE idExercicio = ?");
                    $stmtVid->execute([$id]);
                    $videos = $stmtVid->fetchAll(PDO::FETCH_ASSOC);

                } elseif ($tipo === 'adaptado') {
                    // Busca exercício adaptado
                    $stmtEx = $this->db->prepare("SELECT * FROM exercadaptados WHERE idExercAdaptado = ?");
                    $stmtEx->execute([$id]);
                    $exercicio = $stmtEx->fetch(PDO::FETCH_ASSOC);

                    if (!$exercicio) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Exercício adaptado não encontrado']);
                        return;
                    }

                    // Busca vídeos associados
                    $stmtVid = $this->db->prepare("SELECT * FROM videos WHERE idExercAdaptado = ?");
                    $stmtVid->execute([$id]);
                    $videos = $stmtVid->fetchAll(PDO::FETCH_ASSOC);

                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Tipo inválido. Use "normal" ou "adaptado".']);
                    return;
                }

                // Retorna exercício + vídeos
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'exercicio' => $exercicio,
                    'videos' => $videos
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao buscar exercício com vídeos: ' . $e->getMessage()]);
            }
        }
    }

?>