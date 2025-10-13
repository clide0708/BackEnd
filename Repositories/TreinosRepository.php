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
        public function duplicarTreino($dadosTreino) {
            $sql = "INSERT INTO treinos (idAluno, idPersonal, criadoPor, nome, tipo, descricao, data_criacao, data_ultima_modificacao, tipo_treino) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                $dadosTreino['idAluno'],
                $dadosTreino['idPersonal'],
                $dadosTreino['criadoPor'],
                $dadosTreino['nome'],
                $dadosTreino['tipo'],
                $dadosTreino['descricao'],
                $dadosTreino['data_criacao'],
                $dadosTreino['data_ultima_modificacao'],
                $dadosTreino['tipo_treino']
            ]);

            return $success ? $this->db->lastInsertId() : false;
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