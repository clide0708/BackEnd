<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Services/TreinosService.php';

    class TreinosController {
        private $db;
        private $treinosService;

        public function __construct() {
            $this->db = DB::connectDB();
            $this->treinosService = new TreinosService();
        }

        // Método auxiliar para obter usuário do token
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

        // Treinos
        public function criarTreino($data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $idTreino = $this->treinosService->criarTreino($data);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true, 
                    'idTreino' => $idTreino, 
                    'message' => 'Treino criado com sucesso'
                ]);

            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function atualizarTreino($idTreino, $data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $this->treinosService->atualizarTreino($idTreino, $data, $usuario);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Treino atualizado com sucesso']);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function excluirTreino($idTreino) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $this->treinosService->excluirTreino($idTreino, $usuario);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Treino excluído com sucesso']);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Exercícios no Treino
        public function adicionarExercicioAoTreino($idTreino, $exercicioData) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $this->treinosService->adicionarExercicioAoTreino($idTreino, $exercicioData, $usuario);
                
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Exercício adicionado ao treino']);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function atualizarExercicioNoTreino($idTreinoExercicio, $data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $this->treinosService->atualizarExercicioNoTreino($idTreinoExercicio, $data, $usuario);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Exercício atualizado com sucesso']);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function removerExercicioDoTreino($idTreinoExercicio) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $this->treinosService->removerExercicioDoTreino($idTreinoExercicio, $usuario);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Exercício removido do treino com sucesso']);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Listagens
        public function listarExerciciosDoTreino($idTreino) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                // CORREÇÃO: Query melhorada para garantir que os vídeos sejam buscados
                $stmt = $this->db->prepare("
                    SELECT 
                        te.idTreino_Exercicio,
                        te.idTreino,
                        te.idExercicio,
                        te.series,
                        te.repeticoes,
                        te.carga,
                        te.descanso,
                        te.ordem,
                        te.observacoes,
                        te.data_criacao,
                        te.data_ultima_modificacao,
                        -- Dados do exercício
                        e.nome,
                        e.grupoMuscular,
                        e.descricao,
                        e.tipo_exercicio,
                        e.visibilidade,
                        e.idPersonal,
                        e.cadastradoPor,
                        -- Vídeos - CORREÇÃO: Buscar vídeo corretamente
                        v.url as video_url
                    FROM treino_exercicio te
                    INNER JOIN exercicios e ON te.idExercicio = e.idExercicio
                    LEFT JOIN videos v ON te.idExercicio = v.idExercicio
                    WHERE te.idTreino = ?
                    ORDER BY te.ordem, te.idTreino_Exercicio
                ");
                
                $stmt->execute([$idTreino]);
                $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // CORREÇÃO: Formatar os dados para o frontend garantindo os vídeos
                $exerciciosFormatados = array_map(function($ex) {
                    return [
                        'id' => $ex['idTreino_Exercicio'],
                        'idTreino' => $ex['idTreino'],
                        'idExercicio' => $ex['idExercicio'],
                        'nome' => $ex['nome'],
                        'grupoMuscular' => $ex['grupoMuscular'],
                        'descricao' => $ex['descricao'],
                        'series' => $ex['series'],
                        'repeticoes' => $ex['repeticoes'],
                        'carga' => $ex['carga'],
                        'descanso' => $ex['descanso'],
                        'ordem' => $ex['ordem'],
                        'observacoes' => $ex['observacoes'],
                        // CORREÇÃO: Garantir que o vídeo seja passado de múltiplas formas
                        'video_url' => $ex['video_url'],
                        'url' => $ex['video_url'], // Para compatibilidade
                        'tipo_exercicio' => $ex['tipo_exercicio'],
                        'visibilidade' => $ex['visibilidade'],
                        'idPersonal' => $ex['idPersonal'],
                        'cadastradoPor' => $ex['cadastradoPor'],
                        'informacoes' => $ex['observacoes'] ?: $ex['descricao']
                    ];
                }, $exercicios);

                http_response_code(200);
                echo json_encode(['success' => true, 'exercicios' => $exerciciosFormatados]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarTreinosAluno($idAluno = null) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                // Se não passou idAluno, pega do token
                $idAluno = $idAluno ?? ($usuario['idAluno'] ?? null);
                
                if (!$idAluno) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                    return;
                }

                $treinos = $this->treinosService->listarTreinosAluno($idAluno, $usuario);
                
                http_response_code(200);
                echo json_encode(['success' => true, ...$treinos]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarTreinosPersonal($idPersonal) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                    return;
                }

                $treinos = $this->treinosService->listarTreinosPersonal($idPersonal);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'meusTreinos' => $treinos]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarTreinosAlunoComPersonal($idAluno) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['sub'] != $idAluno || $usuario['tipo'] !== 'aluno') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                    return;
                }

                $treinos = $this->treinosService->listarTreinosAlunoComPersonal($idAluno);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'treinosAtribuidos' => $treinos]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Vínculos e Atribuições
        public function atribuirTreinoAluno($data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Apenas personais podem atribuir treinos']);
                    return;
                }

                $idTreino = (int)($data['idTreino'] ?? 0);
                $idAluno = (int)($data['idAluno'] ?? 0);

                if ($idTreino === 0 || $idAluno === 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'idTreino e idAluno são obrigatórios']);
                    return;
                }

                $idPersonal = $usuario['sub'];
                $novoIdTreino = $this->treinosService->atribuirTreinoAluno($idTreino, $idAluno, $idPersonal);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Treino e exercícios duplicados e atribuídos ao aluno com sucesso',
                    'idNovoTreino' => $novoIdTreino
                ]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function desvincularAluno($idPersonal, $idAluno) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['tipo'] !== 'personal' || $usuario['sub'] != $idPersonal) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Apenas o personal dono do aluno pode desvincular']);
                    return;
                }

                $this->treinosService->desvincularAluno($idAluno, $idPersonal);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Aluno desvinculado e treinos atribuídos apagados com sucesso'
                ]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Treino Completo
        public function buscarTreinoCompleto($idTreino) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $treino = $this->treinosService->buscarTreinoCompleto($idTreino, $usuario);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'treino' => $treino['treino'],
                    'exercicios' => $treino['exercicios']
                ]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Métodos adicionais do controller antigo
        public function listarMeusTreinosPersonal($idPersonal) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                    return;
                }

                $treinos = $this->treinosService->listarMeusTreinosPersonal($idPersonal);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'meusTreinos' => $treinos]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarAlunosDoPersonal($idPersonal) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes alunos']);
                    return;
                }

                $alunos = $this->treinosService->listarAlunosDoPersonal($idPersonal);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'alunos' => $alunos]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarTreinosDoAlunoAtribuidos($idPersonal, $idAluno) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                    return;
                }

                $treinos = $this->treinosService->listarTreinosDoAlunoAtribuidos($idPersonal, $idAluno);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'treinos' => $treinos]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function buscarExercicios($data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $exercicios = $this->treinosService->buscarExercicios($data);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'exercicios' => $exercicios]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarTreinosUsuario($data) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                    return;
                }

                $treinos = $this->treinosService->listarTreinosUsuario($data, $usuario);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'treinos' => $treinos]);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function desatribuirTreinoDoAluno($idTreino) {
            try {
                $usuario = $this->obterUsuarioDoToken();
                if (!$usuario || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Apenas personais podem desatribuir treinos']);
                    return;
                }

                $this->treinosService->desatribuirTreinoDoAluno($idTreino, $usuario);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Treino desatribuído do aluno com sucesso']);

            } catch (Exception $e) {
                $statusCode = $e->getCode() ?: 400;
                http_response_code($statusCode);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
    }

?>

ExerciciosRepository.php:

<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class ExerciciosRepository {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        /**
         * Busca todos os exercícios globais + exercícios pessoais do personal (se fornecido)
         */
        public function buscarTodosExercicios($idPersonal = null) {
            $sql = "SELECT * FROM exercicios WHERE visibilidade = 'global'";
            $params = [];
            
            if ($idPersonal) {
                $sql .= " OR (visibilidade = 'personal' AND idPersonal = ?)";
                $params[] = $idPersonal;
            }
            
            $sql .= " ORDER BY 
                        CASE 
                            WHEN visibilidade = 'global' THEN 1 
                            WHEN visibilidade = 'personal' THEN 2 
                        END,
                        nome ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Busca APENAS exercícios globais (para alunos)
         */
        public function buscarExerciciosGlobais() {
            $sql = "SELECT * FROM exercicios WHERE visibilidade = 'global' ORDER BY nome";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Busca exercícios por tipo (normal/adaptado)
         */
        public function buscarExerciciosPorTipo($tipo, $idPersonal = null) {
            $sql = "SELECT * FROM exercicios WHERE tipo_exercicio = ? AND (visibilidade = 'global'";
            $params = [$tipo];
            
            if ($idPersonal) {
                $sql .= " OR (visibilidade = 'personal' AND idPersonal = ?)";
                $params[] = $idPersonal;
            }
            
            $sql .= ") ORDER BY nome";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Busca exercícios globais por tipo (para alunos)
         */
        public function buscarExerciciosGlobaisPorTipo($tipo) {
            $sql = "SELECT * FROM exercicios WHERE tipo_exercicio = ? AND visibilidade = 'global' ORDER BY nome";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tipo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Busca exercícios adaptados de um personal específico
         */
        public function buscarExerciciosAdaptadosPorPersonal($idPersonal) {
            $sql = "SELECT * FROM exercicios 
                    WHERE tipo_exercicio = 'adaptado' 
                    AND visibilidade = 'personal' 
                    AND idPersonal = ?
                    ORDER BY nome";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idPersonal]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Busca exercício normal por ID
         */
        public function buscarPorID($id) {
            $stmt = $this->db->prepare("SELECT * FROM exercicios WHERE idExercicio = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        /**
         * Busca exercício adaptado por ID
         */
        public function buscarExercicioAdaptadoPorID($id) {
            $stmt = $this->db->prepare("SELECT * FROM exercicios WHERE idExercicio = ? AND tipo_exercicio = 'adaptado'");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        /**
         * Cadastra novo exercício
         */
        public function cadastrarExercicio($data) {
            $sql = "INSERT INTO exercicios (nome, grupoMuscular, descricao, cadastradoPor, tipo_exercicio, visibilidade, idPersonal) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                $data['nome'],
                $data['grupoMuscular'],
                $data['descricao'],
                $data['cadastradoPor'],
                $data['tipo_exercicio'],
                $data['visibilidade'],
                $data['idPersonal'] ?? null
            ]);
            
            return $success;
        }

        /**
         * Retorna o último ID inserido
         */
        public function getLastInsertId() {
            return $this->db->lastInsertId();
        }

        /**
         * Adiciona vídeo a um exercício
         */
        public function adicionarVideo($data) {
            $sql = "INSERT INTO videos (url, idExercicio, idExercAdaptado, cover) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['url'],
                $data['idExercicio'] ?? null,
                $data['idExercAdaptado'] ?? null,
                $data['cover'] ?? ''
            ]);
        }

        /**
         * Busca vídeos por exercício
         */
        public function buscarVideosPorExercicio($idExercicio, $tipo = 'normal') {
            if ($tipo === 'normal') {
                $stmt = $this->db->prepare("SELECT * FROM videos WHERE idExercicio = ?");
            } else {
                $stmt = $this->db->prepare("SELECT * FROM videos WHERE idExercAdaptado = ?");
            }
            $stmt->execute([$idExercicio]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Métodos de transação para operações atômicas
         */
        public function beginTransaction() {
            return $this->db->beginTransaction();
        }

        public function commit() {
            return $this->db->commit();
        }

        public function rollBack() {
            return $this->db->rollBack();
        }

        /**
         * Verifica se exercício pertence ao personal
         */
        public function exercicioPertenceAoPersonal($idExercicio, $idPersonal) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM exercicios 
                WHERE idExercicio = ? AND idPersonal = ? AND visibilidade = 'personal'
            ");
            $stmt->execute([$idExercicio, $idPersonal]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        }

        /**
         * Busca exercícios disponíveis para um aluno (globais + do seu personal)
         */
        public function buscarExerciciosParaAluno($idAluno, $idPersonalAluno) {
            $sql = "SELECT * FROM exercicios 
                    WHERE visibilidade = 'global' 
                    OR (visibilidade = 'personal' AND idPersonal = ?)
                    ORDER BY visibilidade DESC, nome ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idPersonalAluno]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

?>

TreinosRepository.php:

<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class TreinosRepository {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        public function beginTransaction() {
            return $this->db->beginTransaction();
        }

        public function commit() {
            return $this->db->commit();
        }

        public function rollBack() {
            return $this->db->rollBack();
        }

        public function getLastInsertId() {
            return $this->db->lastInsertId();
        }

        // Treinos
        public function criarTreino($data) {
            $sql = "INSERT INTO treinos (idAluno, idPersonal, criadoPor, nome, tipo, descricao, data_criacao, data_ultima_modificacao, tipo_treino) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                $data['idAluno'],
                $data['idPersonal'],
                $data['criadoPor'],
                $data['nome'],
                $data['tipo'],
                $data['descricao'],
                $data['data_criacao'],
                $data['data_ultima_modificacao'],
                $data['tipo_treino']
            ]);

            return $success ? $this->db->lastInsertId() : false;
        }

        public function buscarTreinoPorId($idTreino) {
            $stmt = $this->db->prepare("
                SELECT t.*, p.nome as nomePersonal, a.nome as nomeAluno
                FROM treinos t
                LEFT JOIN personal p ON t.idPersonal = p.idPersonal
                LEFT JOIN alunos a ON t.idAluno = a.idAluno
                WHERE t.idTreino = ?
            ");
            $stmt->execute([$idTreino]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function atualizarTreino($idTreino, $data) {
            $sql = "UPDATE treinos SET nome = ?, tipo = ?, descricao = ?, data_ultima_modificacao = ?, tipo_treino = ? WHERE idTreino = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['nome'],
                $data['tipo'],
                $data['descricao'],
                $data['data_ultima_modificacao'],
                $data['tipo_treino'],
                $idTreino
            ]);
        }

        public function excluirTreino($idTreino) {
            $stmt = $this->db->prepare("DELETE FROM treinos WHERE idTreino = ?");
            return $stmt->execute([$idTreino]);
        }

        public function listarTreinosAluno($idAluno) {
            // Treinos do próprio aluno
            $stmt1 = $this->db->prepare("
                SELECT * FROM treinos 
                WHERE idAluno = ? AND idPersonal IS NULL 
                ORDER BY data_ultima_modificacao DESC
            ");
            $stmt1->execute([$idAluno]);
            $meusTreinos = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            // Treinos criados pelo personal
            $stmt2 = $this->db->prepare("
                SELECT t.*, p.nome as nomePersonal 
                FROM treinos t 
                LEFT JOIN personal p ON t.idPersonal = p.idPersonal 
                WHERE t.idAluno = ? AND t.idPersonal IS NOT NULL 
                ORDER BY t.data_ultima_modificacao DESC
            ");
            $stmt2->execute([$idAluno]);
            $treinosPersonal = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            return [
                'meusTreinos' => $meusTreinos,
                'treinosPersonal' => $treinosPersonal
            ];
        }

        public function listarTreinosPersonal($idPersonal) {
            $stmt = $this->db->prepare("
                SELECT t.*, a.nome AS nomeAluno
                FROM treinos t
                LEFT JOIN alunos a ON t.idAluno = a.idAluno
                WHERE t.idPersonal = ? AND t.idAluno IS NULL
                ORDER BY t.data_ultima_modificacao DESC
            ");
            $stmt->execute([$idPersonal]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function listarTreinosAlunoComPersonal($idAluno) {
            $stmt = $this->db->prepare("
                SELECT t.*, p.nome AS nomePersonal
                FROM treinos t
                LEFT JOIN personal p ON t.idPersonal = p.idPersonal
                WHERE t.idAluno = ? AND t.idPersonal IS NOT NULL
                ORDER BY t.data_ultima_modificacao DESC
            ");
            $stmt->execute([$idAluno]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function verificarPermissaoTreino($idTreino, $usuario) {
            $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
            $stmt->execute([$idTreino]);
            $treino = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$treino) {
                return false;
            }

            $emailUsuario = strtolower(trim($usuario['email']));
            $emailCriador = strtolower(trim($treino['criadoPor']));

            // Verificar se o usuário é o criador do treino
            if ($usuario['tipo'] === 'aluno' && !is_null($treino['idAluno']) && 
                $usuario['sub'] == $treino['idAluno'] && $emailUsuario === $emailCriador) {
                return true;
            }

            if ($usuario['tipo'] === 'personal' && !is_null($treino['idPersonal']) && 
                $usuario['sub'] == $treino['idPersonal'] && $emailUsuario === $emailCriador) {
                return true;
            }

            return false;
        }

        // Exercícios do Treino
        public function adicionarExercicioAoTreino($data) {
            // CORREÇÃO: Remover idExercAdaptado da query
            $sql = "INSERT INTO treino_exercicio (idTreino, idExercicio, data_criacao, data_ultima_modificacao, series, repeticoes, carga, descanso, ordem, observacoes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['idTreino'],
                $data['idExercicio'],
                $data['data_criacao'],
                $data['data_ultima_modificacao'],
                $data['series'],
                $data['repeticoes'],
                $data['carga'],
                $data['descanso'],
                $data['ordem'],
                $data['observacoes']
            ]);
        }

        // CORREÇÃO: Adicionar método para buscar exercício por ID
        public function buscarExercicioPorId($idExercicio) {
            $stmt = $this->db->prepare("SELECT * FROM exercicios WHERE idExercicio = ?");
            $stmt->execute([$idExercicio]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }


        public function buscarExerciciosDoTreino($idTreino) {
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function atualizarExercicioNoTreino($idTreinoExercicio, $data) {
            $sql = "UPDATE treino_exercicio SET series = ?, repeticoes = ?, carga = ?, descanso = ?, ordem = ?, observacoes = ?, data_ultima_modificacao = ? 
                    WHERE idTreino_Exercicio = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['series'],
                $data['repeticoes'],
                $data['carga'],
                $data['descanso'],
                $data['ordem'],
                $data['observacoes'],
                $data['data_ultima_modificacao'],
                $idTreinoExercicio
            ]);
        }

        public function removerExercicioDoTreino($idTreinoExercicio) {
            $stmt = $this->db->prepare("DELETE FROM treino_exercicio WHERE idTreino_Exercicio = ?");
            return $stmt->execute([$idTreinoExercicio]);
        }

        public function buscarExercicioTreinoPorId($idTreinoExercicio) {
            $stmt = $this->db->prepare("
                SELECT te.*, t.idAluno, t.idPersonal, t.criadoPor 
                FROM treino_exercicio te 
                INNER JOIN treinos t ON te.idTreino = t.idTreino 
                WHERE te.idTreino_Exercicio = ?
            ");
            $stmt->execute([$idTreinoExercicio]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function atualizarDataModificacaoTreino($idTreino, $data) {
            $stmt = $this->db->prepare("UPDATE treinos SET data_ultima_modificacao = ? WHERE idTreino = ?");
            return $stmt->execute([$data, $idTreino]);
        }

        // Alunos e Vínculos
        public function listarAlunosDoPersonal($idPersonal) {
            $stmt = $this->db->prepare("SELECT idAluno, nome, email FROM alunos WHERE idPersonal = ?");
            $stmt->execute([$idPersonal]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function verificarVinculoAlunoPersonal($idAluno, $idPersonal) {
            $stmt = $this->db->prepare("SELECT * FROM alunos WHERE idAluno = ? AND idPersonal = ?");
            $stmt->execute([$idAluno, $idPersonal]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function desvincularAluno($idAluno) {
            $stmt = $this->db->prepare("UPDATE alunos SET idPersonal = NULL WHERE idAluno = ?");
            return $stmt->execute([$idAluno]);
        }

        public function buscarTreinosAtribuidosAluno($idAluno, $idPersonal) {
            $stmt = $this->db->prepare("SELECT idTreino FROM treinos WHERE idAluno = ? AND idPersonal = ?");
            $stmt->execute([$idAluno, $idPersonal]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Duplicação de Treinos
        public function duplicarTreino($treinoOriginal, $novosDados) {
            $campos = array_keys($treinoOriginal);
            $placeholders = implode(',', array_fill(0, count($campos), '?'));
            
            $stmt = $this->db->prepare("INSERT INTO treinos (" . implode(',', $campos) . ") VALUES ($placeholders)");
            return $stmt->execute(array_values($treinoOriginal));
        }

        public function duplicarExerciciosTreino($idTreinoOrigem, $idTreinoDestino) {
            // Buscar exercícios do treino original
            $stmtEx = $this->db->prepare("SELECT * FROM treino_exercicio WHERE idTreino = ?");
            $stmtEx->execute([$idTreinoOrigem]);
            $exercicios = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

            // Inserir exercícios no novo treino
            $stmtInsert = $this->db->prepare("
                INSERT INTO treino_exercicio (idTreino, idExercicio, idExercAdaptado, series, repeticoes, carga, descanso, ordem, observacoes, data_criacao, data_ultima_modificacao) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $now = date('Y-m-d H:i:s');
            foreach ($exercicios as $ex) {
                $stmtInsert->execute([
                    $idTreinoDestino,
                    $ex['idExercicio'],
                    $ex['idExercAdaptado'],
                    $ex['series'],
                    $ex['repeticoes'],
                    $ex['carga'],
                    $ex['descanso'],
                    $ex['ordem'],
                    $ex['observacoes'],
                    $now,
                    $now
                ]);
            }

            return true;
        }
    }

?>

ExerciciosService.php:

<?php

    class ExerciciosService {
        private $db;

        public function __construct() {
            require_once __DIR__ . '/../Config/db.connect.php';
            $this->db = DB::connectDB();
        }

        /**
         * Lista exercícios para um usuário baseado no seu tipo
         */
        public function listarExerciciosParaUsuario($usuario) {
            if ($usuario['tipo'] === 'aluno') {
                return $this->listarExerciciosParaAluno($usuario['sub']);
            } else if ($usuario['tipo'] === 'personal') {
                return $this->listarExerciciosParaPersonal($usuario['sub']);
            } else {
                return $this->listarExerciciosGlobais();
            }
        }

        /**
         * Lista exercícios para um aluno (globais + do seu personal)
         */
        private function listarExerciciosParaAluno($idAluno) {
            $idPersonalAluno = $this->obterPersonalDoAluno($idAluno);
            
            $sql = "
                SELECT 
                    idExercicio as id,
                    nome,
                    grupoMuscular,
                    descricao,
                    tipo_exercicio,
                    visibilidade,
                    idPersonal,
                    cadastradoPor
                FROM exercicios 
                WHERE visibilidade = 'global' 
                OR (visibilidade = 'personal' AND idPersonal = ?)
                ORDER BY 
                    CASE 
                        WHEN visibilidade = 'global' THEN 1 
                        WHEN visibilidade = 'personal' THEN 2 
                    END,
                    nome ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idPersonalAluno]);
            $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->adicionarUrlsDeVideo($exercicios);
        }

        /**
         * Lista exercícios para um personal (globais + seus pessoais)
         */
        private function listarExerciciosParaPersonal($idPersonal) {
            $sql = "
                SELECT 
                    idExercicio as id,
                    nome,
                    grupoMuscular,
                    descricao,
                    tipo_exercicio,
                    visibilidade,
                    idPersonal,
                    cadastradoPor
                FROM exercicios 
                WHERE visibilidade = 'global' 
                OR (visibilidade = 'personal' AND idPersonal = ?)
                ORDER BY 
                    CASE 
                        WHEN visibilidade = 'global' THEN 1 
                        WHEN visibilidade = 'personal' THEN 2 
                    END,
                    nome ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idPersonal]);
            $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->adicionarUrlsDeVideo($exercicios);
        }

        /**
         * Busca exercícios por tipo específico
         */
        public function buscarExerciciosPorTipo($tipo, $usuario) {
            if ($usuario['tipo'] === 'aluno') {
                $idPersonalAluno = $this->obterPersonalDoAluno($usuario['sub']);
                
                $sql = "
                    SELECT 
                        idExercicio as id,
                        nome,
                        grupoMuscular,
                        descricao,
                        tipo_exercicio,
                        visibilidade,
                        idPersonal,
                        cadastradoPor
                    FROM exercicios 
                    WHERE tipo_exercicio = ?
                    AND (visibilidade = 'global' OR (visibilidade = 'personal' AND idPersonal = ?))
                    ORDER BY 
                        CASE 
                            WHEN visibilidade = 'global' THEN 1 
                            WHEN visibilidade = 'personal' THEN 2 
                        END,
                        nome ASC
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$tipo, $idPersonalAluno]);
                
            } else if ($usuario['tipo'] === 'personal') {
                $sql = "
                    SELECT 
                        idExercicio as id,
                        nome,
                        grupoMuscular,
                        descricao,
                        tipo_exercicio,
                        visibilidade,
                        idPersonal,
                        cadastradoPor
                    FROM exercicios 
                    WHERE tipo_exercicio = ?
                    AND (visibilidade = 'global' OR (visibilidade = 'personal' AND idPersonal = ?))
                    ORDER BY 
                        CASE 
                            WHEN visibilidade = 'global' THEN 1 
                            WHEN visibilidade = 'personal' THEN 2 
                        END,
                        nome ASC
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$tipo, $usuario['sub']]);
                
            } else {
                $sql = "
                    SELECT 
                        idExercicio as id,
                        nome,
                        grupoMuscular,
                        descricao,
                        tipo_exercicio,
                        visibilidade,
                        idPersonal,
                        cadastradoPor
                    FROM exercicios 
                    WHERE tipo_exercicio = ? AND visibilidade = 'global'
                    ORDER BY nome ASC
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$tipo]);
            }
            
            $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->adicionarUrlsDeVideo($exercicios);
        }

        /**
         * Lista apenas exercícios globais
         */
        private function listarExerciciosGlobais() {
            $sql = "
                SELECT 
                    idExercicio as id,
                    nome,
                    grupoMuscular,
                    descricao,
                    tipo_exercicio,
                    visibilidade,
                    idPersonal,
                    cadastradoPor
                FROM exercicios 
                WHERE visibilidade = 'global'
                ORDER BY nome ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->adicionarUrlsDeVideo($exercicios);
        }

        /**
         * Adiciona URLs de vídeo aos exercícios
         */
        private function adicionarUrlsDeVideo($exercicios) {
            foreach ($exercicios as &$exercicio) {
                $stmt = $this->db->prepare("
                    SELECT url FROM videos 
                    WHERE idExercicio = ? 
                    LIMIT 1
                ");
                $stmt->execute([$exercicio['id']]);
                $video = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // CORREÇÃO: Garantir que a URL do vídeo seja passada corretamente
                $exercicio['video_url'] = $video ? $video['url'] : null;
                $exercicio['url'] = $video ? $video['url'] : null; // Para compatibilidade com o frontend
            }
            
            return $exercicios;
        }

        /**
         * Método auxiliar para obter o personal de um aluno
         */
        private function obterPersonalDoAluno($idAluno) {
            try {
                $stmt = $this->db->prepare("SELECT idPersonal FROM alunos WHERE idAluno = ?");
                $stmt->execute([$idAluno]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['idPersonal'] : null;
            } catch (Exception $e) {
                error_log("Erro ao obter personal do aluno: " . $e->getMessage());
                return null;
            }
        }
    }

?>