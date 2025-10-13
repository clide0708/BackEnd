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
                $exercicio['video_url'] = $video ? $video['url'] : null;
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