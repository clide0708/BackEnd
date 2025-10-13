<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class VideosController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        /**
         * Upload de vídeo (para uso futuro)
         */
        public function uploadVideo($data) {
            try {
                // Implementação futura para upload de arquivos
                http_response_code(501);
                echo json_encode(['success' => false, 'error' => 'Upload de vídeo não implementado. Use URLs do YouTube por enquanto.']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro no upload: ' . $e->getMessage()]);
            }
        }

        /**
         * Associar vídeo a exercício
         */
        public function associarVideoExercicio($data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                
                if (!$usuario || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Apenas personais podem associar vídeos']);
                    return;
                }

                // Validações
                if (empty($data['url']) || empty($data['idExercicio'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'URL e ID do exercício são obrigatórios']);
                    return;
                }

                // Verificar se exercício pertence ao personal
                $stmt = $this->db->prepare("
                    SELECT idExercicio FROM exercicios 
                    WHERE idExercicio = ? AND idPersonal = ? AND visibilidade = 'personal'
                ");
                $stmt->execute([$data['idExercicio'], $usuario['sub']]);
                $exercicio = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$exercicio) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Exercício não encontrado ou não pertence a você']);
                    return;
                }

                // Inserir vídeo
                $stmt = $this->db->prepare("
                    INSERT INTO videos (url, idExercicio, cover) 
                    VALUES (?, ?, ?)
                ");
                
                $cover = $this->gerarYouTubeThumbnail($data['url']);
                
                $success = $stmt->execute([
                    $data['url'],
                    $data['idExercicio'],
                    $cover
                ]);

                if ($success) {
                    http_response_code(201);
                    echo json_encode(['success' => true, 'message' => 'Vídeo associado com sucesso']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Falha ao associar vídeo']);
                }

            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao associar vídeo: ' . $e->getMessage()]);
            }
        }

        /**
         * Buscar vídeos por exercício
         */
        public function buscarVideosPorExercicio($idExercicio) {
            try {
                $stmt = $this->db->prepare("SELECT * FROM videos WHERE idExercicio = ?");
                $stmt->execute([$idExercicio]);
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success' => true, 'videos' => $videos]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao buscar vídeos: ' . $e->getMessage()]);
            }
        }

        /**
         * Gerar thumbnail do YouTube
         */
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

        /**
         * Obter usuário do token
         */
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
    }

?>