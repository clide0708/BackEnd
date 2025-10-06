<?php

  class PerfilRepository
  {
      private $conn;

      public function __construct()
      {
          require_once __DIR__ . '/../Config/db.connect.php';
          $this->conn = DB::connectDB();
      }

      public function getAlunoById($idAluno)
      {
          $query = "SELECT a.idAluno, a.nome, a.altura, a.genero, a.meta, a.foto_perfil, 
                           p.nome as personal_nome, p.email as personal_email, 
                           pl.nome as plano_nome, pl.descricao as plano_descricao, pl.valor_mensal as plano_valor
                    FROM alunos a
                    LEFT JOIN personal p ON a.idPersonal = p.idPersonal
                    LEFT JOIN planos pl ON a.idPlano = pl.idPlano
                    WHERE a.idAluno = :idAluno AND a.status_conta = 'Ativa'";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idAluno", $idAluno);
          $stmt->execute();
          $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
          return $aluno;
      }

      public function getPersonalById($idPersonal)
      {
          $query = "SELECT p.idPersonal, p.nome, p.idade, p.genero, p.email, p.foto_perfil, 
                              pl.nome as plano_nome, pl.descricao as plano_descricao, pl.valor_mensal as plano_valor
                      FROM personal p
                      LEFT JOIN planos pl ON p.idPlano = pl.idPlano
                      WHERE p.idPersonal = :idPersonal AND p.status_conta = 'Ativa'";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idPersonal", $idPersonal);
          $stmt->execute();
          return $stmt->fetch(PDO::FETCH_ASSOC);
      }

      public function getAcademiaById($idAcademia)
      {
          $query = "SELECT ac.idAcademia, ac.nome, ac.cnpj, ac.email, ac.telefone, ac.endereco, 
                              pl.nome as plano_nome, pl.descricao as plano_descricao, pl.valor_mensal as plano_valor
                      FROM academias ac
                      LEFT JOIN planos pl ON ac.idPlano = pl.idPlano
                      WHERE ac.idAcademia = :idAcademia AND ac.status_conta = 'Ativa'";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idAcademia", $idAcademia);
          $stmt->execute();
          return $stmt->fetch(PDO::FETCH_ASSOC);
      }

      public function getDevById($idDev)
      {
          $query = "SELECT d.idDev, d.nome, d.email, d.nivel_acesso
                      FROM devs d
                      WHERE d.idDev = :idDev";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idDev", $idDev);
          $stmt->execute();
          return $stmt->fetch(PDO::FETCH_ASSOC);
      }

      // Métodos POST/PUT para perfis (completar/atualizar)
      public function updateAlunoPerfil($idAluno, $data)
      {
          $updates = [];
          $params = [":idAluno" => $idAluno];

          if (isset($data["nome"])) {
              $updates[] = "nome = :nome";
              $params[":nome"] = $data["nome"];
          }
          if (isset($data["altura"])) {
              $updates[] = "altura = :altura";
              $params[":altura"] = $data["altura"];
          }
          if (isset($data["genero"])) {
              $updates[] = "genero = :genero";
              $params[":genero"] = $data["genero"];
          }
          if (isset($data["meta"])) {
              $updates[] = "meta = :meta";
              $params[":meta"] = $data["meta"];
          }
          if (isset($data["foto_perfil"])) {
              $updates[] = "foto_perfil = :foto_perfil";
              $params[":foto_perfil"] = $data["foto_perfil"];
          }

          if (empty($updates)) {
              return ["success" => false, "message" => "Nenhum dado para atualizar."];
          }

          $query = "UPDATE alunos SET " . implode(", ", $updates) . " WHERE idAluno = :idAluno";
          $stmt = $this->conn->prepare($query);
          $success = $stmt->execute($params);

          return ["success" => $success, "message" => "Perfil do aluno atualizado com sucesso.", "rows_affected" => $stmt->rowCount()];
      }

      public function updatePersonalPerfil($idPersonal, $data)
      {
          $updates = [];
          $params = [":idPersonal" => $idPersonal];

          if (isset($data["nome"])) {
              $updates[] = "nome = :nome";
              $params[":nome"] = $data["nome"];
          }
          if (isset($data["idade"])) {
              $updates[] = "idade = :idade";
              $params[":idade"] = $data["idade"];
          }
          if (isset($data["genero"])) {
              $updates[] = "genero = :genero";
              $params[":genero"] = $data["genero"];
          }
          if (isset($data["foto_perfil"])) {
              $updates[] = "foto_perfil = :foto_perfil";
              $params[":foto_perfil"] = $data["foto_perfil"];
          }

          if (empty($updates)) {
              return ["success" => false, "message" => "Nenhum dado para atualizar."];
          }

          $query = "UPDATE personal SET " . implode(", ", $updates) . " WHERE idPersonal = :idPersonal";
          $stmt = $this->conn->prepare($query);
          $success = $stmt->execute($params);

          return ["success" => $success, "message" => "Perfil do personal atualizado com sucesso.", "rows_affected" => $stmt->rowCount()];
      }

      public function updateAcademiaPerfil($idAcademia, $data)
      {
          $updates = [];
          $params = [":idAcademia" => $idAcademia];

          if (isset($data["nome"])) {
              $updates[] = "nome = :nome";
              $params[":nome"] = $data["nome"];
          }
          if (isset($data["telefone"])) {
              $updates[] = "telefone = :telefone";
              $params[":telefone"] = $data["telefone"];
          }
          if (isset($data["endereco"])) {
              $updates[] = "endereco = :endereco";
              $params[":endereco"] = $data["endereco"];
          }

          if (empty($updates)) {
              return ["success" => false, "message" => "Nenhum dado para atualizar."];
          }

          $query = "UPDATE academias SET " . implode(", ", $updates) . " WHERE idAcademia = :idAcademia";
          $stmt = $this->conn->prepare($query);
          $success = $stmt->execute($params);

          return ["success" => $success, "message" => "Perfil da academia atualizado com sucesso.", "rows_affected" => $stmt->rowCount()];
      }

      public function updateDevPerfil($idDev, $data)
      {
          $updates = [];
          $params = [":idDev" => $idDev];

          if (isset($data["nome"])) {
              $updates[] = "nome = :nome";
              $params[":nome"] = $data["nome"];
          }
          if (isset($data["nivel_acesso"])) {
              $updates[] = "nivel_acesso = :nivel_acesso";
              $params[":nivel_acesso"] = $data["nivel_acesso"];
          }

          if (empty($updates)) {
              return ["success" => false, "message" => "Nenhum dado para atualizar."];
          }

          $query = "UPDATE devs SET " . implode(", ", $updates) . " WHERE idDev = :idDev";
          $stmt = $this->conn->prepare($query);
          $success = $stmt->execute($params);

          return ["success" => $success, "message" => "Perfil do desenvolvedor atualizado com sucesso.", "rows_affected" => $stmt->rowCount()];
      }

      // Métodos para gerenciamento de planos
      public function getPlanoUsuario($idUsuario, $tipoUsuario)
      {
          $idColumn = 'id' . ucfirst($tipoUsuario);
          $query = "SELECT p.idPlano, p.nome, p.descricao, p.valor_mensal, p.tipo_usuario, p.caracteristicas, a.status as status_assinatura
                      FROM " . $tipoUsuario . "s u
                      JOIN assinaturas a ON a.idUsuario = u." . $idColumn . " AND a.tipo_usuario = :tipoUsuario
                      JOIN planos p ON a.idPlano = p.idPlano
                      WHERE u." . $idColumn . " = :idUsuario AND a.status = 'ativa'";

          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idUsuario", $idUsuario);
          $stmt->bindParam(":tipoUsuario", $tipoUsuario);
          $stmt->execute();
          return $stmt->fetch(PDO::FETCH_ASSOC);
      }

      public function getPlanoById($idPlano)
      {
          $query = "SELECT * FROM planos WHERE idPlano = :idPlano AND ativo = TRUE";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idPlano", $idPlano);
          $stmt->execute();
          return $stmt->fetch(PDO::FETCH_ASSOC);
      }

      public function cancelarAssinaturaAtual($idUsuario, $tipoUsuario)
      {
          $query = "UPDATE assinaturas SET status = 'cancelada', data_fim = NOW() 
                      WHERE idUsuario = :idUsuario AND tipo_usuario = :tipoUsuario AND status = 'ativa'";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idUsuario", $idUsuario);
          $stmt->bindParam(":tipoUsuario", $tipoUsuario);
          return $stmt->execute();
      }

      public function criarNovaAssinatura($idUsuario, $tipoUsuario, $idPlano, $status)
      {
          $query = "INSERT INTO assinaturas (idUsuario, tipo_usuario, idPlano, status) VALUES (:idUsuario, :tipoUsuario, :idPlano, :status)";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idUsuario", $idUsuario);
          $stmt->bindParam(":tipoUsuario", $tipoUsuario);
          $stmt->bindParam(":idPlano", $idPlano);
          $stmt->bindParam(":status", $status);
          return $stmt->execute();
      }

      public function updateUsuarioPlano($idUsuario, $tipoUsuario, $idPlano)
      {
          $idColumn = 'id' . ucfirst($tipoUsuario);
          $tableName = $tipoUsuario . 's';
          if ($tipoUsuario === 'dev') { // Devs não têm planos
              return true;
          }
          $query = "UPDATE " . $tableName . " SET idPlano = :idPlano WHERE " . $idColumn . " = :idUsuario";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idPlano", $idPlano);
          $stmt->bindParam(":idUsuario", $idUsuario);
          return $stmt->execute();
      }

      public function getPlanoBasicoId($tipoUsuario)
      {
          $query = "SELECT idPlano FROM planos WHERE nome LIKE ? AND tipo_usuario = ? AND valor_mensal = 0.00";
          $stmt = $this->conn->prepare($query);
          $nomePlano = ucfirst($tipoUsuario) . ' Básico';
          $stmt->bindParam(1, $nomePlano);
          $stmt->bindParam(2, $tipoUsuario);
          $stmt->execute();
          $result = $stmt->fetch(PDO::FETCH_ASSOC);
          return $result ? $result['idPlano'] : null;
      }

      // Métodos para exclusão de conta (soft delete)
      public function softDeleteConta($idUsuario, $tipoUsuario)
      {
          if ($tipoUsuario === 'dev') {
              return ['success' => false, 'error' => 'Não é permitido excluir contas de desenvolvedor via esta funcionalidade.'];
          }

          $idColumn = 'id' . ucfirst($tipoUsuario);
          $tableName = $tipoUsuario . 's';

          try {
              $this->conn->beginTransaction();

              // 1. Marcar a conta como 'Excluida'
              $query = "UPDATE " . $tableName . " SET status_conta = 'Excluida' WHERE " . $idColumn . " = :idUsuario";
              $stmt = $this->conn->prepare($query);
              $stmt->bindParam(":idUsuario", $idUsuario);
              $stmt->execute();

              // 2. Cancelar a assinatura ativa (se houver)
              $this->cancelarAssinaturaAtual($idUsuario, $tipoUsuario);

              $this->conn->commit();
              return ['success' => true, 'message' => 'Conta marcada como excluída com sucesso.'];
          } catch (PDOException $e) {
              $this->conn->rollBack();
              return ['success' => false, 'error' => 'Erro ao excluir conta: ' . $e->getMessage()];
          }
      }

      public function desvincularAlunosDoPersonal($idPersonal)
      {
          $query = "UPDATE alunos SET idPersonal = NULL, status_vinculo = 'Inativo' WHERE idPersonal = :idPersonal";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idPersonal", $idPersonal);
          return $stmt->execute();
      }

      public function isAlunoVinculadoAoPersonal($idAluno, $idPersonal)
      {
          $query = "SELECT COUNT(*) FROM alunos WHERE idAluno = :idAluno AND idPersonal = :idPersonal AND status_vinculo = 'Ativo'";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idAluno", $idAluno);
          $stmt->bindParam(":idPersonal", $idPersonal);
          $stmt->execute();
          return $stmt->fetchColumn() > 0;
      }

      public function getAlunosDoPersonal($idPersonal)
      {
          $query = "SELECT idAluno, nome, email, status_vinculo FROM alunos WHERE idPersonal = :idPersonal AND status_conta = 'Ativa'";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idPersonal", $idPersonal);
          $stmt->execute();
          return $stmt->fetchAll(PDO::FETCH_ASSOC);
      }

      public function getTreinosCriadosPorPersonal($idPersonal)
      {
          $query = "SELECT idTreinosP, nomeTreino FROM treinospersonal WHERE idPersonal = :idPersonal";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":idPersonal", $idPersonal);
          $stmt->execute();
          return $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
  }

?>