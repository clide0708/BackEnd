<?php

    namespace App\Repositories;

    use PDO;
    use App\Config\Database;

    class PerfilRepository {
        private $conn;

        public function __construct() {
            $database = new Database();
            $this->conn = $database->getConnection();
        }

        public function getAlunoById($idAluno) {
            $query = "SELECT a.idAluno, a.nome, a.altura, a.genero, a.meta, a.statusPlano, a.foto_perfil, p.nome as personal_nome, p.email as personal_email
                    FROM alunos a
                    LEFT JOIN personal p ON a.idPersonal = p.idPersonal
                    WHERE a.idAluno = :idAluno";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idAluno", $idAluno);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function createAlunoPerfil($data) {
            // Esta função pode ser usada para preencher dados adicionais após o cadastro inicial
            // ou para um POST inicial de perfil se o cadastro for separado.
            // Para simplificar, vamos focar no PUT para atualização de dados de perfil.
            // O cadastro inicial do aluno já deve criar o registro básico.
            return ["message" => "Use PUT para atualizar o perfil do aluno."];
        }

        public function updateAlunoPerfil($idAluno, $data) {
            $query = "UPDATE alunos SET ";
            $params = [];
            $updates = [];

            if (isset($data["nome"])) { $updates[] = "nome = :nome"; $params[":nome"] = $data["nome"]; }
            if (isset($data["altura"])) { $updates[] = "altura = :altura"; $params[":altura"] = $data["altura"]; }
            if (isset($data["genero"])) { $updates[] = "genero = :genero"; $params[":genero"] = $data["genero"]; }
            if (isset($data["meta"])) { $updates[] = "meta = :meta"; $params[":meta"] = $data["meta"]; }
            if (isset($data["statusPlano"])) { $updates[] = "statusPlano = :statusPlano"; $params[":statusPlano"] = $data["statusPlano"]; }
            if (isset($data["foto_perfil"])) { $updates[] = "foto_perfil = :foto_perfil"; $params[":foto_perfil"] = $data["foto_perfil"]; }

            if (empty($updates)) {
                return ["message" => "Nenhum dado para atualizar."];
            }

            $query .= implode(", ", $updates) . " WHERE idAluno = :idAluno";
            $params[":idAluno"] = $idAluno;

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            return ["message" => "Perfil do aluno atualizado com sucesso.", "rows_affected" => $stmt->rowCount()];
        }

        public function getPersonalById($idPersonal) {
            $query = "SELECT p.idPersonal, p.nome, p.idade, p.genero, p.email, p.foto_perfil, 
                            GROUP_CONCAT(a.nome SEPARATOR "; ") as alunos_vinculados
                    FROM personal p
                    LEFT JOIN alunos a ON p.idPersonal = a.idPersonal
                    WHERE p.idPersonal = :idPersonal
                    GROUP BY p.idPersonal";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idPersonal", $idPersonal);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function createPersonalPerfil($data) {
            // Similar ao aluno, o cadastro inicial do personal já deve criar o registro básico.
            return ["message" => "Use PUT para atualizar o perfil do personal."];
        }

        public function updatePersonalPerfil($idPersonal, $data) {
            $query = "UPDATE personal SET ";
            $params = [];
            $updates = [];

            if (isset($data["nome"])) { $updates[] = "nome = :nome"; $params[":nome"] = $data["nome"]; }
            if (isset($data["idade"])) { $updates[] = "idade = :idade"; $params[":idade"] = $data["idade"]; }
            if (isset($data["genero"])) { $updates[] = "genero = :genero"; $params[":genero"] = $data["genero"]; }
            if (isset($data["foto_perfil"])) { $updates[] = "foto_perfil = :foto_perfil"; $params[":foto_perfil"] = $data["foto_perfil"]; }

            if (empty($updates)) {
                return ["message" => "Nenhum dado para atualizar."];
            }

            $query .= implode(", ", $updates) . " WHERE idPersonal = :idPersonal";
            $params[":idPersonal"] = $idPersonal;

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            return ["message" => "Perfil do personal atualizado com sucesso.", "rows_affected" => $stmt->rowCount()];
        }

        // Função para obter treinos atribuídos a um aluno (para o personal)
        public function getTreinosByAlunoId($idAluno) {
            $query = "SELECT t.idTreino, t.nomeTreino, t.dataCriacao, t.status
                    FROM treinos t
                    WHERE t.idAluno = :idAluno";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idAluno", $idAluno);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Função para atribuir treino a um aluno (para o personal)
        public function atribuirTreinoAluno($idAluno, $idPersonal, $idTreinoPersonal) {
            // Lógica para vincular um treino criado pelo personal a um aluno
            // Isso pode envolver copiar o treino ou criar um novo registro na tabela `treinos`
            // Por simplicidade, vamos assumir que `treinos` já tem `idPersonal` e `idAluno`
            
            // Primeiro, obter os detalhes do treino do personal
            $queryTreinoPersonal = "SELECT nomeTreino FROM treinospersonal WHERE idTreinosP = :idTreinoPersonal AND idPersonal = :idPersonal";
            $stmtTreinoPersonal = $this->conn->prepare($queryTreinoPersonal);
            $stmtTreinoPersonal->bindParam(":idTreinoPersonal", $idTreinoPersonal);
            $stmtTreinoPersonal->bindParam(":idPersonal", $idPersonal);
            $stmtTreinoPersonal->execute();
            $treinoPersonal = $stmtTreinoPersonal->fetch(PDO::FETCH_ASSOC);

            if (!$treinoPersonal) {
                return ["message" => "Treino do personal não encontrado ou não pertence a este personal.", "success" => false];
            }

            // Inserir um novo treino para o aluno baseado no treino do personal
            $queryInsertTreino = "INSERT INTO treinos (idAluno, idPersonal, nomeTreino, dataCriacao, status, criadoPor)
                                VALUES (:idAluno, :idPersonal, :nomeTreino, NOW(), 'Ativo', 'Personal')";
            $stmtInsertTreino = $this->conn->prepare($queryInsertTreino);
            $stmtInsertTreino->bindParam(":idAluno", $idAluno);
            $stmtInsertTreino->bindParam(":idPersonal", $idPersonal);
            $stmtInsertTreino->bindParam(":nomeTreino", $treinoPersonal["nomeTreino"]);
            $stmtInsertTreino->execute();

            return ["message" => "Treino atribuído ao aluno com sucesso.", "success" => true, "new_treino_id" => $this->conn->lastInsertId()];
        }

        // Função para obter a lista de alunos vinculados a um personal
        public function getAlunosVinculados($idPersonal) {
            $query = "SELECT idAluno, nome, email FROM alunos WHERE idPersonal = :idPersonal";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idPersonal", $idPersonal);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Função para obter os treinos criados por um personal
        public function getTreinosCriadosPorPersonal($idPersonal) {
            $query = "SELECT idTreinosP, nomeTreino FROM treinospersonal WHERE idPersonal = :idPersonal";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":idPersonal", $idPersonal);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

?>