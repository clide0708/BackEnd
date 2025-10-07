<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Config/jwt.config.php';
    ini_set('display_errors', 1);
    error_reporting(E_ALL);



    class TreinosController
    {
        private $db;

        public function __construct()
        {
            $this->db = connectDB(); // chama a função que tu definiu no final do DB.php
        }


        // Criar treino - obrigatório: nome, tipo, criadoPor, idAluno ou idPersonal
        public function criarTreino($data)
        {
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
            $tiposValidos = ['Musculação', 'CrossFit', 'Calistenia', 'Pilates', 'Aquecimento', 'Treino Específico', 'Outros'];
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

        // Atualizar treino (nome, tipo, descricao) e atualizar data_ultima_modificacao
        public function atualizarTreino($idTreino, $data)
        {
            $idTreino = (int)$idTreino;

            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            // mapear id conforme tipo
            $idAluno = ($usuario['tipo'] === 'aluno') ? $usuario['sub'] : null;
            $idPersonal = ($usuario['tipo'] === 'personal') ? $usuario['sub'] : null;

            $emailUsuario = strtolower(trim($usuario['email'])); // normaliza

            // buscar treino
            $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
            $stmt->execute([$idTreino]);
            $treino = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$treino) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                return;
            }

            // checar permissão: id bate E email bate
            $usuarioValido = false;
            if (!is_null($treino['idAluno']) && $idAluno == $treino['idAluno'] && $emailUsuario === strtolower(trim($treino['criadoPor']))) {
                $usuarioValido = true;
            }
            if (!is_null($treino['idPersonal']) && $idPersonal == $treino['idPersonal'] && $emailUsuario === strtolower(trim($treino['criadoPor']))) {
                $usuarioValido = true;
            }

            if (!$usuarioValido) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para editar este treino']);
                return;
            }

            // atualizar dados
            $nome = trim($data['nome'] ?? $treino['nome']);
            $descricao = $data['descricao'] ?? $treino['descricao'];
            $tipo = $data['tipo'] ?? $treino['tipo'];

            // validar tipo
            $tiposValidos = ['Musculação', 'CrossFit', 'Calistenia', 'Pilates', 'Aquecimento', 'Treino Específico', 'Outros'];
            if (!in_array($tipo, $tiposValidos)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Tipo inválido']);
                return;
            }

            $now = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("UPDATE treinos SET nome = ?, tipo = ?, descricao = ?, data_ultima_modificacao = ? WHERE idTreino = ?");
            $stmt->execute([$nome, $tipo, $descricao, $now, $idTreino]);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Treino atualizado com sucesso']);
        }

        // Adicionar exercício ao treino
        public function adicionarExercicioAoTreino($idTreino, $exercicioData)
        {
            $idTreino = (int)$idTreino;

            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            $idAlunoToken = $usuario['tipo'] === 'aluno' ? $usuario['sub'] : null;
            $idPersonalToken = $usuario['tipo'] === 'personal' ? $usuario['sub'] : null;
            $emailUsuario = strtolower(trim($usuario['email']));

            // Verificar se treino existe e pertence ao usuário (aluno ou personal)
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
            if (!is_null($treino['idAluno']) && $idAlunoToken == $treino['idAluno'] && $emailUsuario == $treino['criadoPor']) {
                $usuarioValido = true;
            }
            if (!is_null($treino['idPersonal']) && $idPersonalToken == $treino['idPersonal'] && $emailUsuario == $treino['criadoPor']) {
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
                $stmt = $this->db->prepare("INSERT INTO treino_exercicio (idTreino, idExercicio, idExercAdaptado, data_criacao, data_ultima_modificacao, series, repeticoes, carga, descanso, ordem, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $idTreino,
                    $exercicioData['idExercicio'] ?? null,
                    $exercicioData['idExercAdaptado'] ?? null,
                    $now,
                    $now,
                    $exercicioData['series'] ?? null,
                    $exercicioData['repeticoes'] ?? null,
                    $exercicioData['carga'] ?? null,
                    $exercicioData['descanso'] ?? null, // Campo descanso adicionado
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

        public function listarExerciciosDoTreino($idTreino)
        {
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
        public function atualizarExercicioNoTreino($idTreinoExercicio, $data)
        {
            $idTreinoExercicio = (int)$idTreinoExercicio;
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido']);
                return;
            }

            $idAlunoToken = $usuario['tipo'] === 'aluno' ? $usuario['sub'] : null;
            $idPersonalToken = $usuario['tipo'] === 'personal' ? $usuario['sub'] : null;
            $emailUsuario = strtolower(trim($usuario['email']));

            $stmt = $this->db->prepare("SELECT te.*, t.idAluno, t.idPersonal, t.criadoPor FROM treino_exercicio te INNER JOIN treinos t ON te.idTreino = t.idTreino WHERE te.idTreino_Exercicio = ?");
            $stmt->execute([$idTreinoExercicio]);
            $exercicio = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$exercicio) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Exercício no treino não encontrado']);
                return;
            }

            $usuarioValido = false;
            if (!is_null($exercicio['idAluno']) && $idAlunoToken == $exercicio['idAluno'] && $emailUsuario == strtolower(trim($exercicio['criadoPor']))) {
                $usuarioValido = true;
            }
            if (!is_null($exercicio['idPersonal']) && $idPersonalToken == $exercicio['idPersonal'] && $emailUsuario == strtolower(trim($exercicio['criadoPor']))) {
                $usuarioValido = true;
            }

            if (!$usuarioValido) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para editar este exercício']);
                return;
            }

            $series = $data['series'] ?? $exercicio['series'];
            $repeticoes = $data['repeticoes'] ?? $exercicio['repeticoes'];
            $carga = $data['carga'] ?? $exercicio['carga'];
            $descanso = $data['descanso'] ?? $exercicio['descanso']; // Campo descanso adicionado
            $ordem = $data['ordem'] ?? $exercicio['ordem'];
            $observacoes = $data['observacoes'] ?? $exercicio['observacoes'];

            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $this->db->prepare("UPDATE treino_exercicio SET series = ?, repeticoes = ?, carga = ?, descanso = ?, ordem = ?, observacoes = ?, data_ultima_modificacao = ? WHERE idTreino_Exercicio = ?");
                $stmt->execute([$series, $repeticoes, $carga, $descanso, $ordem, $observacoes, $now, $idTreinoExercicio]);

                $stmtUpdateTreino = $this->db->prepare("UPDATE treinos SET data_ultima_modificacao = ? WHERE idTreino = ?");
                $stmtUpdateTreino->execute([$now, $exercicio['idTreino']]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Exercício atualizado com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar exercício: ' . $e->getMessage()]);
            }
        }


        // Remover exercício do treino
        public function removerExercicioDoTreino($idTreinoExercicio)
        {
            $idTreinoExercicio = (int)$idTreinoExercicio;

            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            $idAlunoToken = $usuario['tipo'] === 'aluno' ? $usuario['sub'] : null;
            $idPersonalToken = $usuario['tipo'] === 'personal' ? $usuario['sub'] : null;
            $emailUsuario = strtolower(trim($usuario['email']));


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
                if (!is_null($treinoExercicio['idAluno']) && $idAlunoToken == $treinoExercicio['idAluno'] && $emailUsuario == $treinoExercicio['criadoPor']) {
                    $usuarioValido = true;
                }
                if (!is_null($treinoExercicio['idPersonal']) && $idPersonalToken == $treinoExercicio['idPersonal'] && $emailUsuario == $treinoExercicio['criadoPor']) {
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

        // Listar treinos para aluno
        public function listarTreinosAluno($idAluno = null)
        {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            // se não passou idAluno, pega do token
            $idAluno = $idAluno ?? ($usuario['idAluno'] ?? null);

            // se ainda não tiver idAluno ou o usuário não é dono do treino, bloqueia
            if (!$idAluno) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            try {
                // treinos do próprio aluno
                $stmt1 = $this->db->prepare("SELECT * FROM treinos WHERE idAluno = ? AND idPersonal IS NULL ORDER BY data_ultima_modificacao DESC");
                $stmt1->execute([$idAluno]);
                $meusTreinos = $stmt1->fetchAll(PDO::FETCH_ASSOC);

                // treinos criados pelo personal
                $stmt2 = $this->db->prepare("SELECT t.*, p.nome as nomePersonal FROM treinos t LEFT JOIN personal p ON t.idPersonal = p.idPersonal WHERE t.idAluno = ? AND t.idPersonal IS NOT NULL ORDER BY t.data_ultima_modificacao DESC");
                $stmt2->execute([$idAluno]);
                $treinosPersonal = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'meusTreinos' => $meusTreinos,
                    'treinosPersonal' => $treinosPersonal
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao listar treinos: ' . $e->getMessage()]);
            }
        }

        public function listarTreinosAlunoComPersonal($idAluno)
        {
            header('Content-Type: application/json');

            // Obter usuário do token JWT
            // $usuario = $this->obterUsuarioDoToken();
            // if (!$usuario || $usuario['sub'] != $idAluno || $usuario['tipo'] !== 'aluno') {
            //     http_response_code(403);
            //     echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
            //     return;
            // }

            try {
                $stmt = $this->db->prepare("
                SELECT t.*, p.nome AS nomePersonal
                FROM treinos t
                LEFT JOIN personal p ON t.idPersonal = p.idPersonal
                WHERE t.idAluno = ? AND t.idPersonal IS NOT NULL
                ORDER BY t.data_ultima_modificacao DESC
            ");
                $stmt->execute([$idAluno]);
                $treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'treinosAtribuidos' => $treinos
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarTreinosPersonal($idPersonal)
        {
            header('Content-Type: application/json');

            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            try {
                $stmt = $this->db->prepare("
                SELECT t.*, a.nome AS nomeAluno
                FROM treinos t
                LEFT JOIN alunos a ON t.idAluno = a.idAluno
                WHERE t.idPersonal = ? AND t.idAluno IS NULL
                ORDER BY t.data_ultima_modificacao DESC
            ");
                $stmt->execute([$idPersonal]);
                $treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'meusTreinos' => $treinos
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }


        public function atribuirTreinoAluno($data)
        {
            $idTreino = (int)($data['idTreino'] ?? 0);
            $idAluno = (int)($data['idAluno'] ?? 0);

            if ($idTreino === 0 || $idAluno === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'idTreino e idAluno são obrigatórios']);
                return;
            }

            $token = extrairTokenHeader();
            $usuario = $token ? obterDadosUsuario($token) : null;
            if (!$usuario || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas personais podem atribuir treinos']);
                return;
            }
            $idPersonalToken = $usuario['sub'];

            // verifica aluno e vínculo
            $stmtAluno = $this->db->prepare("SELECT * FROM alunos WHERE idAluno = ? AND idPersonal = ?");
            $stmtAluno->execute([$idAluno, $idPersonalToken]);
            $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

            if (!$aluno) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Aluno não encontrado ou não vinculado a você']);
                return;
            }

            // pega treino original
            $stmtTreino = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
            $stmtTreino->execute([$idTreino]);
            $treinoOriginal = $stmtTreino->fetch(PDO::FETCH_ASSOC);

            if (!$treinoOriginal) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                return;
            }

            try {
                $this->db->beginTransaction();

                // duplica treino
                unset($treinoOriginal['idTreino']);
                $treinoOriginal['idAluno'] = $idAluno;
                $treinoOriginal['data_ultima_modificacao'] = date('Y-m-d H:i:s');

                $campos = array_keys($treinoOriginal);
                $placeholders = array_map(fn($c) => '?', $campos);

                $stmtInsert = $this->db->prepare(
                    "INSERT INTO treinos (" . implode(',', $campos) . ") VALUES (" . implode(',', $placeholders) . ")"
                );
                $stmtInsert->execute(array_values($treinoOriginal));
                $novoIdTreino = $this->db->lastInsertId();

                // duplica exercícios do treino
                $stmtEx = $this->db->prepare("SELECT * FROM treino_exercicio WHERE idTreino = ?");
                $stmtEx->execute([$idTreino]);
                $exercicios = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

                $stmtInsertEx = $this->db->prepare(
                    "INSERT INTO treino_exercicio (idTreino, idExercicio, series, repeticoes, carga) VALUES (?, ?, ?, ?, ?)"
                );

                foreach ($exercicios as $ex) {
                    $stmtInsertEx->execute([
                        $novoIdTreino,
                        $ex['idExercicio'],
                        $ex['series'],
                        $ex['repeticoes'],
                        $ex['carga']
                    ]);
                }

                $this->db->commit();

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Treino e exercícios duplicados e atribuídos ao aluno com sucesso']);
            } catch (Exception $e) {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao duplicar treino: ' . $e->getMessage()]);
            }
        }

        // Excluir treino
        public function excluirTreino($idTreino)
        {
            $idTreino = (int)$idTreino;

            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken(); // retorna array ou null
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

            // Verificar permissão: só pode excluir se for o criador (aluno ou personal)
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $emailUsuario = strtolower(trim($usuario['email'])); // normaliza

            // buscar treino
            $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
            $stmt->execute([$idTreino]);
            $treino = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$treino) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                return;
            }

            $emailCriador = strtolower(trim($treino['criadoPor'])); // normaliza também

            // checar permissão: id bate E email bate
            $usuarioValido = false;
            if (!is_null($treino['idAluno']) && $usuario['tipo'] === 'aluno' && $usuario['sub'] == $treino['idAluno'] && $emailUsuario === $emailCriador) {
                $usuarioValido = true;
            }
            if (!is_null($treino['idPersonal']) && $usuario['tipo'] === 'personal' && $usuario['sub'] == $treino['idPersonal'] && $emailUsuario === $emailCriador) {
                $usuarioValido = true;
            }

            if (!$usuarioValido) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para excluir este treino']);
                return;
            }

            // se passou, exclui



            try {
                // Excluir exercícios relacionados
                $stmtEx = $this->db->prepare("DELETE FROM treino_exercicio WHERE idTreino = ?");
                $stmtEx->execute([$idTreino]);

                // Excluir treino
                $stmt = $this->db->prepare("DELETE FROM treinos WHERE idTreino = ?");
                $stmt->execute([$idTreino]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Treino excluído com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao excluir treino: ' . $e->getMessage()]);
            }
        }


        // Listar alunos do personal
        public function listarAlunosDoPersonal($idPersonal)
        {
            $idPersonal = (int)$idPersonal;

            // Verificar permissão
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $idPersonalToken != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes alunos']);
                return;
            }

            try {
                $stmt = $this->db->prepare("SELECT idAluno, nome, email FROM alunos WHERE idPersonal = ?");
                $stmt->execute([$idPersonal]);
                $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success' => true, 'alunos' => $alunos]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao listar alunos: ' . $e->getMessage()]);
            }
        }

        // Listar treinos do aluno atribuídos pelo personal
        public function listarTreinosDoAlunoAtribuidos($idPersonal, $idAluno)
        {
            $idPersonal = (int)$idPersonal;
            $idAluno = (int)$idAluno;

            // Verificar permissão
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $idPersonalToken != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            // Verificar se o aluno pertence ao personal
            $stmtAluno = $this->db->prepare("SELECT * FROM alunos WHERE idAluno = ? AND idPersonal = ?");
            $stmtAluno->execute([$idAluno, $idPersonal]);
            $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

            if (!$aluno) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Aluno não encontrado ou não vinculado a você']);
                return;
            }

            try {
                $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idPersonal = ? AND idAluno = ? ORDER BY data_ultima_modificacao DESC");
                $stmt->execute([$idPersonal, $idAluno]);
                $treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success' => true, 'treinos' => $treinos]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao listar treinos do aluno: ' . $e->getMessage()]);
            }
        }

        // Atualizar treino atribuído
        public function atualizarTreinoAtribuido($idTreino, $data)
        {
            $idTreino = (int)$idTreino;

            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || !isset($idPersonalToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas personais podem atualizar treinos atribuídos']);
                return;
            }

            // Verificar se treino existe e pertence ao personal e ao aluno
            $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
            $stmt->execute([$idTreino]);
            $treino = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$treino) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                return;
            }

            if ($treino['idPersonal'] != $idPersonalToken || $usuario['email'] != $treino['criadoPor']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para editar este treino']);
                return;
            }

            // Atualizar nome, tipo, descricao e data_ultima_modificacao
            $nome = trim($data['nome'] ?? $treino['nome']);
            $descricao = $data['descricao'] ?? $treino['descricao'];
            $tipo = $data['tipo'] ?? $treino['tipo'];

            $tiposValidos = ['Musculação', 'CrossFit', 'Calistenia', 'Pilates', 'Aquecimento', 'Treino Específico', 'Outros'];
            if (!in_array($tipo, $tiposValidos)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Tipo inválido']);
                return;
            }

            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $this->db->prepare("UPDATE treinos SET nome = ?, tipo = ?, descricao = ?, data_ultima_modificacao = ? WHERE idTreino = ?");
                $stmt->execute([$nome, $tipo, $descricao, $now, $idTreino]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Treino atribuído atualizado com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar treino: ' . $e->getMessage()]);
            }
        }

        // Desatribuir treino do aluno
        public function desatribuirTreinoDoAluno($idTreino)
        {
            $idTreino = (int)$idTreino;

            // Obter usuário do token JWT
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas personais podem desatribuir treinos']);
                return;
            }

            $idPersonalToken = $usuario['sub'];

            // Verificar se treino existe e pertence ao personal
            $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idTreino = ?");
            $stmt->execute([$idTreino]);
            $treino = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$treino) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Treino não encontrado']);
                return;
            }

            if ($treino['idPersonal'] != $idPersonalToken) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para desatribuir este treino']);
                return;
            }

            try {
                $stmt = $this->db->prepare("UPDATE treinos SET idAluno = NULL, data_ultima_modificacao = ? WHERE idTreino = ?");
                $now = date('Y-m-d H:i:s');
                $stmt->execute([$now, $idTreino]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Treino desatribuído do aluno com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao desatribuir treino: ' . $e->getMessage()]);
            }
        }

        // Desvincular aluno <-> personal e remover treinos atribuídos
        // rota: 'personal/(\d+)/desvincular-aluno/(\d+)' 
        // onde primeiro número é idPersonal e segundo é idAluno

        public function desvincularAluno($idPersonal, $idAluno)
        {
            $idAluno = (int)$idAluno;
            $idPersonal = (int)$idPersonal;

            // obter usuário do token
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['tipo'] !== 'personal' || $usuario['sub'] != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas o personal dono do aluno pode desvincular']);
                return;
            }

            try {
                // verificar se aluno existe e tá vinculado ao personal
                $stmtAluno = $this->db->prepare("SELECT * FROM alunos WHERE idAluno = ? AND idPersonal = ?");
                $stmtAluno->execute([$idAluno, $idPersonal]);
                $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

                if (!$aluno) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Aluno não encontrado ou não vinculado a você']);
                    return;
                }

                // pegar treinos atribuídos desse aluno pelo personal
                $stmtTreinos = $this->db->prepare("SELECT idTreino FROM treinos WHERE idAluno = ? AND idPersonal = ?");
                $stmtTreinos->execute([$idAluno, $idPersonal]);
                $treinos = $stmtTreinos->fetchAll(PDO::FETCH_ASSOC);

                foreach ($treinos as $treino) {
                    // apagar exercícios
                    $stmtEx = $this->db->prepare("DELETE FROM treino_exercicio WHERE idTreino = ?");
                    $stmtEx->execute([$treino['idTreino']]);

                    // apagar treino
                    $stmtDel = $this->db->prepare("DELETE FROM treinos WHERE idTreino = ?");
                    $stmtDel->execute([$treino['idTreino']]);
                }

                // desvincular aluno do personal
                $stmtUpd = $this->db->prepare("UPDATE alunos SET idPersonal = NULL WHERE idAluno = ?");
                $stmtUpd->execute([$idAluno]);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Aluno desvinculado e treinos atribuídos apagados com sucesso'
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao desvincular aluno: ' . $e->getMessage()]);
            }
        }

        // Listar meus treinos (personal) - treinos criados para si mesmo (idAluno IS NULL)
        public function listarMeusTreinosPersonal($idPersonal)
        {
            $idPersonal = (int)$idPersonal;

            // Verificar permissão
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['sub'] != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            try {
                $stmt = $this->db->prepare("SELECT * FROM treinos WHERE idPersonal = ? AND idAluno IS NULL ORDER BY data_ultima_modificacao DESC");
                $stmt->execute([$idPersonal]);
                $meusTreinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success' => true, 'meusTreinos' => $meusTreinos]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao listar meus treinos: ' . $e->getMessage()]);
            }
        }

        // Buscar exercícios para treino
        public function buscarExercicios($data)
        {
            $nome = $data['nome'] ?? '';
            $grupoMuscular = $data['grupoMuscular'] ?? '';

            try {
                $sql = "SELECT * FROM exercicios WHERE 1=1";
                $params = [];

                if (!empty($nome)) {
                    $sql .= " AND nome LIKE ?";
                    $params[] = "%$nome%";
                }

                if (!empty($grupoMuscular)) {
                    $sql .= " AND grupoMuscular LIKE ?";
                    $params[] = "%$grupoMuscular%";
                }

                $sql .= " ORDER BY nome";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(['success' => true, 'exercicios' => $exercicios]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao buscar exercícios: ' . $e->getMessage()]);
            }
        }

        // Listar treinos do usuário autenticado
        public function listarTreinosUsuario($data)
        {
            $tipo = $data['tipo'] ?? '';
            $id = (int)($data['id'] ?? 0);
            $email = $data['email'] ?? '';

            if (empty($tipo) || $id === 0 || empty($email)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'tipo, id e email são obrigatórios']);
                return;
            }

            // Verificar se o usuário tem permissão
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['email'] != $email) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            try {
                if ($tipo === 'aluno') {
                    // Treinos do aluno
                    $stmt = $this->db->prepare("
                            SELECT t.*, p.nome as nomePersonal 
                            FROM treinos t 
                            LEFT JOIN personal p ON t.idPersonal = p.idPersonal 
                            WHERE t.idAluno = ? 
                            ORDER BY t.data_ultima_modificacao DESC
                        ");
                    $stmt->execute([$id]);
                    $treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Treinos do personal
                    $stmt = $this->db->prepare("
                            SELECT t.*, a.nome as nomeAluno 
                            FROM treinos t 
                            LEFT JOIN alunos a ON t.idAluno = a.idAluno 
                            WHERE t.idPersonal = ? 
                            ORDER BY t.data_ultima_modificacao DESC
                        ");
                    $stmt->execute([$id]);
                    $treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                http_response_code(200);
                echo json_encode(['success' => true, 'treinos' => $treinos]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao listar treinos: ' . $e->getMessage()]);
            }
        }

        // Buscar treino completo com exercícios e vídeos
        public function buscarTreinoCompleto($idTreino)
        {
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
                        WHERE idTreino = ?
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
        private function obterUsuarioDoToken()
        {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                require_once __DIR__ . '/../Config/jwt.config.php';

                try {
                    $decoded = decodificarToken($token); // usar sua função que já retorna payload
                    return $decoded ? (array)$decoded : null;
                } catch (Exception $e) {
                    return null;
                }
            }

            return null;
        }
    }

?>