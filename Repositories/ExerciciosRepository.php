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