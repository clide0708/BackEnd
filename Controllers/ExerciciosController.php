<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Services/ExerciciosService.php';

    class ExerciciosController {
        private $db;
        private $exerciciosService;

        public function __construct() {
            $this->db = DB::connectDB();
            $this->exerciciosService = new ExerciciosService();
        }
        
        // Buscar todos os exercícios (globais + pessoais se personal estiver logado)
        public function buscarTodosExercicios() {
            try {
                $usuario = $this->obterUsuarioDoToken();
                
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(["error" => "Usuário não autenticado"]);
                    return;
                }

                $exercicios = $this->exerciciosService->listarExerciciosParaUsuario($usuario);
                
                http_response_code(200);
                echo json_encode($exercicios);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["error" => "Erro ao buscar exercícios: " . $e->getMessage()]);
            }
        }

        public function buscarExerciciosNormais() {
            try {
                $usuario = $this->obterUsuarioDoToken();
                
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(["error" => "Usuário não autenticado"]);
                    return;
                }

                $exercicios = $this->exerciciosService->buscarExerciciosPorTipo('normal', $usuario);
                
                http_response_code(200);
                echo json_encode($exercicios);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["error" => "Erro ao buscar exercícios normais: " . $e->getMessage()]);
            }
        }

        public function buscarExerciciosAdaptados() {
            try {
                $usuario = $this->obterUsuarioDoToken();
                
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(["error" => "Usuário não autenticado"]);
                    return;
                }

                $exercicios = $this->exerciciosService->buscarExerciciosPorTipo('adaptado', $usuario);
                
                http_response_code(200);
                echo json_encode($exercicios);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["error" => "Erro ao buscar exercícios adaptados: " . $e->getMessage()]);
            }
        }

        private function obterUsuarioDoToken() {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                require_once __DIR__ . '/../Config/jwt.config.php';

                try {
                    $decoded = decodificarToken($token);
                    return $decoded ? (array)$decoded : null;
                } catch (Exception $e) {
                    return null;
                }
            }
            return null;
        }

        public function buscarMeusExercicios() {
            try {
                $usuario = $this->obterUsuarioDoToken();
                
                if (!$usuario || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(["error" => "Apenas personais podem ver seus exercícios"]);
                    return;
                }

                $idPersonal = $usuario['sub'];
                
                // Buscar apenas exercícios pessoais do personal
                $stmt = $this->db->prepare("
                    SELECT * FROM exercicios 
                    WHERE visibilidade = 'personal' AND idPersonal = ? 
                    ORDER BY nome
                ");
                $stmt->execute([$idPersonal]);
                $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode($exercicios);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["error" => "Erro ao buscar exercícios: " . $e->getMessage()]);
            }
        }

        private function gerarYouTubeThumbnail($url) {
            $videoId = '';
            
            if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $url, $matches)) {
                $videoId = $matches[1];
            } elseif (preg_match('/youtu\.be\/([^&]+)/', $url, $matches)) {
                $videoId = $matches[1];
            }
            
            if ($videoId) {
                return "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
            }
            
            return '';
        }

        public function cadastrarExercicioPersonal($data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                
                if (!$usuario || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Apenas personais podem cadastrar exercícios']);
                    return;
                }

                // Validação básica
                $camposObrigatorios = ['nome', 'grupoMuscular', 'descricao', 'tipo_exercicio'];
                foreach ($camposObrigatorios as $campo) {
                    if (empty(trim($data[$campo] ?? ''))) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Campo {$campo} é obrigatório"]);
                        return;
                    }
                }

                // Validar tipo de exercício
                $tiposValidos = ['normal', 'adaptado'];
                if (!in_array($data['tipo_exercicio'], $tiposValidos)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Tipo de exercício inválido. Use: ' . implode(', ', $tiposValidos)]);
                    return;
                }

                $tipoExercicio = $data['tipo_exercicio'];
                
                // CORREÇÃO: Usar transação para garantir consistência
                $this->db->beginTransaction();

                try {
                    // AGORA: Cadastrar apenas na tabela exercicios
                    $sql = "INSERT INTO exercicios (nome, grupoMuscular, descricao, cadastradoPor, visibilidade, idPersonal, tipo_exercicio) 
                            VALUES (?, ?, ?, ?, 'personal', ?, ?)";
                    $stmt = $this->db->prepare($sql);
                    $success = $stmt->execute([
                        trim($data['nome']),
                        trim($data['grupoMuscular']),
                        trim($data['descricao']),
                        $usuario['email'],
                        $usuario['sub'],
                        $tipoExercicio
                    ]);
                    
                    $idExercicio = $this->db->lastInsertId();

                    if ($success) {
                        // Adicionar vídeo se fornecido
                        if (!empty(trim($data['video_url'] ?? ''))) {
                            $video_url = trim($data['video_url']);
                            $cover = $this->gerarYouTubeThumbnail($video_url);
                            
                            $sqlVideo = "INSERT INTO videos (url, idExercicio, cover) VALUES (?, ?, ?)";
                            $stmtVideo = $this->db->prepare($sqlVideo);
                            $stmtVideo->execute([$video_url, $idExercicio, $cover]);
                        }
                        
                        $this->db->commit();
                        
                        http_response_code(201);
                        echo json_encode([
                            'success' => true, 
                            'idExercicio' => $idExercicio,
                            'tipo_exercicio' => $tipoExercicio,
                            'message' => 'Exercício cadastrado com sucesso'
                        ]);
                    } else {
                        $this->db->rollBack();
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Falha ao cadastrar exercício']);
                    }

                } catch (Exception $e) {
                    $this->db->rollBack();
                    throw $e;
                }

            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar exercício: ' . $e->getMessage()]);
            }
        }

        // Buscar exercício por ID
        public function buscarPorID($id) {
            try {
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
    }

?>