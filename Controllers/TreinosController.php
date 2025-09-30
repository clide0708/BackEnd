<?php
    require_once __DIR__ . '/../Config/db.connect.php';

    class TreinosController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        // Criar treino
        public function criarTreino($data) {
            /*
            Espera $data com:
            - nome (string)
            - tipo (enum válido)
            - criadoPor (string, email ou identificador)
            - idAluno (int|null) - null se personal criar para si mesmo
            - idPersonal (int|null) - null se aluno criar para si mesmo
            - descricao (string|null)
            - exercicios: array de exercícios com:
                - idExercicio (int|null)
                - idExercAdaptado (int|null)
                - series (int)
                - repeticoes (int)
                - carga (decimal)
                - ordem (int)
                - observacoes (string|null)
            */

            $tiposValidos = ['Musculação','CrossFit','Calistenia','Pilates','Aquecimento','Treino Específico','Outros'];

            if (empty($data['nome']) || empty($data['tipo']) || !in_array($data['tipo'], $tiposValidos)) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'Nome e tipo válidos são obrigatórios']);
                return;
            }

            $nome = trim($data['nome']);
            $tipo = $data['tipo'];
            $criadoPor = trim($data['criadoPor'] ?? '');
            $idAluno = isset($data['idAluno']) ? (is_numeric($data['idAluno']) ? (int)$data['idAluno'] : null) : null;
            $idPersonal = isset($data['idPersonal']) ? (is_numeric($data['idPersonal']) ? (int)$data['idPersonal'] : null) : null;
            $descricao = $data['descricao'] ?? null;
            $exercicios = $data['exercicios'] ?? [];

            // Validar que pelo menos um dos dois IDs foi informado
            if (is_null($idAluno) && is_null($idPersonal)) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'Informe pelo menos idAluno ou idPersonal']);
                return;
            }

            // Validar existência do aluno, se informado
            if (!is_null($idAluno)) {
                $stmt = $this->db->prepare("SELECT 1 FROM alunos WHERE idAluno = ?");
                $stmt->execute([$idAluno]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success'=>false, 'error'=>'Aluno não encontrado']);
                    return;
                }
            }

            // Validar existência do personal, se informado
            if (!is_null($idPersonal)) {
                $stmt = $this->db->prepare("SELECT 1 FROM personal WHERE idPersonal = ?");
                $stmt->execute([$idPersonal]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success'=>false, 'error'=>'Personal não encontrado']);
                    return;
                }
            }

            $now = date('Y-m-d H:i:s');

            try {
                $this->db->beginTransaction();

                // Inserir treino, passando NULL para idAluno ou idPersonal se não informado
                $stmt = $this->db->prepare("INSERT INTO treinos (idAluno, idPersonal, criadoPor, nome, tipo, descricao, data_criacao, data_ultima_modificacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $idAluno,       // pode ser null
                    $idPersonal,    // pode ser null
                    $criadoPor,
                    $nome,
                    $tipo,
                    $descricao,
                    $now,
                    $now
                ]);
                $idTreino = $this->db->lastInsertId();

                // Inserir exercícios no treino_exercicio
                $stmtEx = $this->db->prepare("INSERT INTO treino_exercicio (idTreinosP, idExercicio, idExercAdaptado, data_criacao, data_ultima_modificacao, series, repeticoes, carga, ordem, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($exercicios as $ex) {
                    $idExercicio = $ex['idExercicio'] ?? null;
                    $idExercAdaptado = $ex['idExercAdaptado'] ?? null;
                    $series = $ex['series'] ?? null;
                    $repeticoes = $ex['repeticoes'] ?? null;
                    $carga = $ex['carga'] ?? null;
                    $ordem = $ex['ordem'] ?? null;
                    $observacoes = $ex['observacoes'] ?? null;

                    // Validação básica para pelo menos um id de exercício
                    if (empty($idExercicio) && empty($idExercAdaptado)) {
                        throw new Exception("Cada exercício deve ter idExercicio ou idExercAdaptado");
                    }

                    $stmtEx->execute([
                        $idTreino,
                        $idExercicio,
                        $idExercAdaptado,
                        $now,
                        $now,
                        $series,
                        $repeticoes,
                        $carga,
                        $ordem,
                        $observacoes
                    ]);
                }

                $this->db->commit();

                http_response_code(201);
                echo json_encode(['success'=>true, 'idTreino'=>$idTreino, 'message'=>'Treino criado com sucesso']);
            } catch (Exception $e) {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['success'=>false, 'error'=>'Erro ao criar treino: '.$e->getMessage()]);
            }
        }

        // Atualizar treino (nome, tipo, descrição, exercícios)
        public function atualizarTreino($idTreino, $data) {
            /*
            Atualiza nome, tipo, descricao, e lista de exercícios.
            Atualiza data_ultima_modificacao.
            Para atualizar exercícios, pode ser necessário deletar os antigos e inserir os novos.
            */

            $tiposValidos = ['Musculação','CrossFit','Calistenia','Pilates','Aquecimento','Treino Específico','Outros'];

            if (!$idTreino) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'ID do treino obrigatório']);
                return;
            }

            $nome = $data['nome'] ?? null;
            $tipo = $data['tipo'] ?? null;
            $descricao = $data['descricao'] ?? null;
            $exercicios = $data['exercicios'] ?? null;

            if ($tipo !== null && !in_array($tipo, $tiposValidos)) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'Tipo inválido']);
                return;
            }

            $now = date('Y-m-d H:i:s');

            try {
                $this->db->beginTransaction();

                // Atualiza dados do treino
                $fields = [];
                $params = [];

                if ($nome !== null) {
                    $fields[] = "nome = ?";
                    $params[] = $nome;
                }
                if ($tipo !== null) {
                    $fields[] = "tipo = ?";
                    $params[] = $tipo;
                }
                if ($descricao !== null) {
                    $fields[] = "descricao = ?";
                    $params[] = $descricao;
                }

                if (!empty($fields)) {
                    $fields[] = "data_ultima_modificacao = ?";
                    $params[] = $now;
                    $params[] = $idTreino;

                    $sql = "UPDATE treinos SET " . implode(", ", $fields) . " WHERE idTreino = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                }

                // Atualiza exercícios se fornecido
                if (is_array($exercicios)) {
                    // Deleta exercícios antigos
                    $stmtDel = $this->db->prepare("DELETE FROM treino_exercicio WHERE idTreinosP = ?");
                    $stmtDel->execute([$idTreino]);

                    // Insere novos exercícios
                    $stmtEx = $this->db->prepare("INSERT INTO treino_exercicio (idTreinosP, idExercicio, idExercAdaptado, data_criacao, data_ultima_modificacao, series, repeticoes, carga, ordem, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($exercicios as $ex) {
                        $idExercicio = $ex['idExercicio'] ?? null;
                        $idExercAdaptado = $ex['idExercAdaptado'] ?? null;
                        $series = $ex['series'] ?? null;
                        $repeticoes = $ex['repeticoes'] ?? null;
                        $carga = $ex['carga'] ?? null;
                        $ordem = $ex['ordem'] ?? null;
                        $observacoes = $ex['observacoes'] ?? null;

                        if (empty($idExercicio) && empty($idExercAdaptado)) {
                            throw new Exception("Cada exercício deve ter idExercicio ou idExercAdaptado");
                        }

                        $stmtEx->execute([
                            $idTreino,
                            $idExercicio,
                            $idExercAdaptado,
                            $now,
                            $now,
                            $series,
                            $repeticoes,
                            $carga,
                            $ordem,
                            $observacoes
                        ]);
                    }
                }

                $this->db->commit();

                http_response_code(200);
                echo json_encode(['success'=>true, 'message'=>'Treino atualizado com sucesso']);
            } catch (Exception $e) {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['success'=>false, 'error'=>'Erro ao atualizar treino: '.$e->getMessage()]);
            }
        }

        // Buscar exercícios (normais e adaptados) com filtro por nome, grupoMuscular ou id
        public function buscarExercicios($filtros) {
            /*
            $filtros pode conter:
            - nome (string)
            - grupoMuscular (string)
            - idExercicio (int)
            - idExercAdaptado (int)
            */

            $nome = $filtros['nome'] ?? null;
            $grupo = $filtros['grupoMuscular'] ?? null;
            $idExercicio = $filtros['idExercicio'] ?? null;
            $idExercAdaptado = $filtros['idExercAdaptado'] ?? null;

            try {
                $params = [];
                $whereExerc = [];
                $whereAdapt = [];

                // Para exercicios normais
                if ($nome !== null) {
                    $whereExerc[] = "nome LIKE ?";
                    $params[] = "%$nome%";
                }
                if ($grupo !== null) {
                    $whereExerc[] = "grupoMuscular LIKE ?";
                    $params[] = "%$grupo%";
                }
                if ($idExercicio !== null) {
                    $whereExerc[] = "idExercicio = ?";
                    $params[] = $idExercicio;
                }

                // Para exercicios adaptados
                if ($nome !== null) {
                    $whereAdapt[] = "nome LIKE ?";
                    $params[] = "%$nome%";
                }
                if ($grupo !== null) {
                    $whereAdapt[] = "grupoMuscular LIKE ?";
                    $params[] = "%$grupo%";
                }
                if ($idExercAdaptado !== null) {
                    $whereAdapt[] = "idExercAdaptado = ?";
                    $params[] = $idExercAdaptado;
                }

                // Montar query para exercicios normais
                $sqlExerc = "SELECT idExercicio, nome, grupoMuscular, descricao, 'normal' AS tipo FROM exercicios";
                if (!empty($whereExerc)) {
                    $sqlExerc .= " WHERE " . implode(" AND ", $whereExerc);
                }

                // Montar query para exercicios adaptados
                $sqlAdapt = "SELECT idExercAdaptado AS idExercicio, nome, grupoMuscular, descricao, 'adaptado' AS tipo FROM exercadaptados";
                if (!empty($whereAdapt)) {
                    $sqlAdapt .= " WHERE " . implode(" AND ", $whereAdapt);
                }

                // Unir resultados
                $sql = "$sqlExerc UNION ALL $sqlAdapt ORDER BY nome ASC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);

                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success'=>true, 'exercicios'=>$result]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success'=>false, 'error'=>'Erro ao buscar exercícios: '.$e->getMessage()]);
            }
        }

        // Buscar treinos do usuário (aluno ou personal) com controle de acesso
        public function listarTreinosUsuario($usuario) {
            /*
            $usuario deve conter:
            - tipo: 'aluno' ou 'personal'
            - id: idAluno ou idPersonal
            - email ou identificador para criadoPor
            */

            $tipo = $usuario['tipo'] ?? null;
            $id = $usuario['id'] ?? null;
            $email = $usuario['email'] ?? null;

            if (!$tipo || !$id) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'Usuário inválido']);
                return;
            }

            try {
                if ($tipo === 'aluno') {
                    // Aluno vê só seus treinos (idAluno = id e criadoPor = email dele)
                    $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idAluno = ? AND criadoPor = ? ORDER BY data_ultima_modificacao DESC");
                    $stmt->execute([$id, $email]);
                } elseif ($tipo === 'personal') {
                    // Personal vê só seus treinos (idPersonal = id e criadoPor = email dele)
                    $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idPersonal = ? AND criadoPor = ? ORDER BY data_ultima_modificacao DESC");
                    $stmt->execute([$id, $email]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success'=>false, 'error'=>'Tipo de usuário inválido']);
                    return;
                }

                $treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success'=>true, 'treinos'=>$treinos]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success'=>false, 'error'=>'Erro ao listar treinos: '.$e->getMessage()]);
            }
        }

        // Atribuir treino a aluno (personal atribui treino criado a um aluno)
        public function atribuirTreinoAluno($idTreino, $idAluno) {
            if (!$idTreino || !$idAluno) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'ID do treino e do aluno são obrigatórios']);
                return;
            }

            try {
                $now = date('Y-m-d H:i:s');

                // Atualiza o treino para atribuir o idAluno
                $stmt = $this->db->prepare("UPDATE treinos SET idAluno = ?, data_ultima_modificacao = ? WHERE idTreino = ?");
                $stmt->execute([$idAluno, $now, $idTreino]);

                http_response_code(200);
                echo json_encode(['success'=>true, 'message'=>'Treino atribuído ao aluno com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success'=>false, 'error'=>'Erro ao atribuir treino: '.$e->getMessage()]);
            }
        }

        // Excluir treino (com controle de acesso)
        public function excluirTreino($idTreino, $usuario) {
            /*
            Só pode excluir se for o criador (criadoPor) e tipo de usuário e id baterem
            */
            if (!$idTreino) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'ID do treino obrigatório']);
                return;
            }

            $tipo = $usuario['tipo'] ?? null;
            $id = $usuario['id'] ?? null;
            $email = $usuario['email'] ?? null;

            if (!$tipo || !$id) {
                http_response_code(400);
                echo json_encode(['success'=>false, 'error'=>'Usuário inválido']);
                return;
            }

            try {
                // Verifica se o treino pertence ao usuário
                if ($tipo === 'aluno') {
                    $stmtCheck = $this->db->prepare("SELECT criadoPor FROM treinos WHERE idTreino = ? AND idAluno = ? AND criadoPor = ?");
                    $stmtCheck->execute([$idTreino, $id, $email]);
                } elseif ($tipo === 'personal') {
                    $stmtCheck = $this->db->prepare("SELECT criadoPor FROM treinos WHERE idTreino = ? AND idPersonal = ? AND criadoPor = ?");
                    $stmtCheck->execute([$idTreino, $id, $email]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success'=>false, 'error'=>'Tipo de usuário inválido']);
                    return;
                }

                $existe = $stmtCheck->fetch();

                if (!$existe) {
                    http_response_code(403);
                    echo json_encode(['success'=>false, 'error'=>'Você não tem permissão para excluir este treino']);
                    return;
                }

                // Deleta os exercícios do treino
                $stmtDelEx = $this->db->prepare("DELETE FROM treino_exercicio WHERE idTreinosP = ?");
                $stmtDelEx->execute([$idTreino]);

                // Deleta o treino
                $stmtDel = $this->db->prepare("DELETE FROM treinos WHERE idTreino = ?");
                $stmtDel->execute([$idTreino]);

                http_response_code(200);
                echo json_encode(['success'=>true, 'message'=>'Treino excluído com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success'=>false, 'error'=>'Erro ao excluir treino: '.$e->getMessage()]);
            }
        }

        // Método para buscar treino completo com exercícios, vídeos e grupamentos musculares
        public function buscarTreinoCompleto($idTreino) {
            /*
            Retorna o treino com:
            - dados do treino
            - lista de exercícios (normais e adaptados)
            - para cada exercício, os vídeos associados
            - lista de grupamentos musculares trabalhados no treino
            */

            try {
                // 1. Buscar dados do treino
                $stmtTreino = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
                $stmtTreino->execute([$idTreino]);
                $treino = $stmtTreino->fetch(PDO::FETCH_ASSOC);

                if (!$treino) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                    return;
                }

                // 2. Buscar exercícios do treino (normais e adaptados)
                // Query para pegar exercícios normais com vídeos
                $sqlExerciciosNormais = "
                    SELECT 
                        te.idTreino_Exercicio,
                        te.series,
                        te.repeticoes,
                        te.carga,
                        te.ordem,
                        te.observacoes,
                        'normal' AS tipo,
                        e.idExercicio,
                        e.nome AS nomeExercicio,
                        e.grupoMuscular,
                        e.descricao,
                        COALESCE(
                            JSON_ARRAYAGG(
                                JSON_OBJECT(
                                    'idvideos', v.idvideos,
                                    'url', v.url,
                                    'cover', v.cover
                                )
                            ), JSON_ARRAY()
                        ) AS videos
                    FROM treino_exercicio te
                    INNER JOIN exercicios e ON te.idExercicio = e.idExercicio
                    LEFT JOIN videos v ON v.idExercicio = e.idExercicio
                    WHERE te.idTreinosP = ?
                    GROUP BY te.idTreino_Exercicio
                ";

                // Query para pegar exercícios adaptados com vídeos
                $sqlExerciciosAdaptados = "
                    SELECT 
                        te.idTreino_Exercicio,
                        te.series,
                        te.repeticoes,
                        te.carga,
                        te.ordem,
                        te.observacoes,
                        'adaptado' AS tipo,
                        ea.idExercAdaptado AS idExercicio,
                        ea.nome AS nomeExercicio,
                        ea.grupoMuscular,
                        ea.descricao,
                        COALESCE(
                            JSON_ARRAYAGG(
                                JSON_OBJECT(
                                    'idvideos', v.idvideos,
                                    'url', v.url,
                                    'cover', v.cover
                                )
                            ), JSON_ARRAY()
                        ) AS videos
                    FROM treino_exercicio te
                    INNER JOIN exercadaptados ea ON te.idExercAdaptado = ea.idExercAdaptado
                    LEFT JOIN videos v ON v.idExercAdaptado = ea.idExercAdaptado
                    WHERE te.idTreinosP = ?
                    GROUP BY te.idTreino_Exercicio
                ";

                // Executa as duas consultas
                $stmtNormais = $this->db->prepare($sqlExerciciosNormais);
                $stmtNormais->execute([$idTreino]);
                $exerciciosNormais = $stmtNormais->fetchAll(PDO::FETCH_ASSOC);

                $stmtAdaptados = $this->db->prepare($sqlExerciciosAdaptados);
                $stmtAdaptados->execute([$idTreino]);
                $exerciciosAdaptados = $stmtAdaptados->fetchAll(PDO::FETCH_ASSOC);

                // 3. Combina os exercícios
                $todosExercicios = array_merge($exerciciosNormais, $exerciciosAdaptados);

                // Decodifica o JSON dos vídeos para array PHP
                foreach ($todosExercicios as &$ex) {
                    $ex['videos'] = json_decode($ex['videos'], true);
                }
                unset($ex);

                // Ordena pelo campo 'ordem'
                usort($todosExercicios, function($a, $b) {
                    return ($a['ordem'] ?? 0) <=> ($b['ordem'] ?? 0);
                });

                // 4. Calcular grupamentos musculares do treino
                $gruposMusculares = [];

                foreach ($todosExercicios as $ex) {
                    if (!empty($ex['grupoMuscular'])) {
                        // Se o grupoMuscular for uma string com vários grupos separados por vírgula, pode separar e adicionar individualmente
                        $grupos = array_map('trim', explode(',', $ex['grupoMuscular']));
                        foreach ($grupos as $grupo) {
                            if ($grupo && !in_array($grupo, $gruposMusculares)) {
                                $gruposMusculares[] = $grupo;
                            }
                        }
                    }
                }

                // 5. Monta resposta
                $response = [
                    'success' => true,
                    'treino' => $treino,
                    'gruposMusculares' => $gruposMusculares,
                    'exercicios' => $todosExercicios
                ];

                http_response_code(200);
                echo json_encode($response);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao buscar treino completo: ' . $e->getMessage()]);
            }
        }
    }
?>