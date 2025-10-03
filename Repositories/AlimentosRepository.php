<?php

    class AlimentosRepository {
        private $pdo;

        public function __construct($pdo) {
            $this->pdo = $pdo;
        }

        // Métodos de tradução
        public function getTraducaoIngles(string $termoPortugues): ?string {
            $stmt = $this->pdo->prepare("SELECT termo_ingles FROM traducoes_alimentos WHERE termo_portugues = ? LIMIT 1");
            $stmt->execute([$termoPortugues]);
            return $stmt->fetchColumn() ?: null;
        }

        public function getTraducaoPortugues(string $termoIngles): ?string {
            $stmt = $this->pdo->prepare("SELECT termo_portugues FROM traducoes_alimentos WHERE termo_ingles = ? LIMIT 1");
            $stmt->execute([$termoIngles]);
            return $stmt->fetchColumn() ?: null;
        }

        public function inserirTraducao(string $termoIngles, string $termoPortugues, string $fonte = 'dicionario'): bool {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO traducoes_alimentos (termo_ingles, termo_portugues, fonte) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE termo_portugues = VALUES(termo_portugues), fonte = VALUES(fonte)");
                return $stmt->execute([$termoIngles, $termoPortugues, $fonte]);
            } catch (PDOException $e) {
                error_log("Erro ao inserir tradução: " . $e->getMessage());
                return false;
            }
        }

        // Inserir refeição
        public function insertRefeicao(int $idAluno, string $nomeTipo, string $dataRef): int {
            $stmt = $this->pdo->prepare("INSERT INTO refeicoes_tipos (idAluno, nome_tipo, data_ref) VALUES (?, ?, ?)");
            $stmt->execute([$idAluno, $nomeTipo, $dataRef]);
            return $this->pdo->lastInsertId();
        }

        // Deletar refeição e todos os itens associados
        public function deleteRefeicao(int $idRefeicao): bool {
            $this->pdo->beginTransaction();
            try {
                // Primeiro deleta os nutrientes dos itens desta refeição
                $stmt = $this->pdo->prepare("
                    DELETE n FROM nutrientes n
                    INNER JOIN itens_refeicao ir ON n.alimento_id = ir.idItensRef
                    WHERE ir.id_tipo_refeicao = ?
                ");
                $stmt->execute([$idRefeicao]);

                // Depois deleta os itens da refeição
                $stmt = $this->pdo->prepare("DELETE FROM itens_refeicao WHERE id_tipo_refeicao = ?");
                $stmt->execute([$idRefeicao]);

                // Finalmente deleta a refeição
                $stmt = $this->pdo->prepare("DELETE FROM refeicoes_tipos WHERE id = ?");
                $stmt->execute([$idRefeicao]);

                $this->pdo->commit();
                return true;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                error_log("Erro ao deletar refeição: " . $e->getMessage());
                return false;
            }
        }

        // Listar refeições por aluno
        public function getRefeicoesByAluno(int $idAluno, ?string $dataRef = null): array {
            $sql = "SELECT id, nome_tipo, data_ref FROM refeicoes_tipos WHERE idAluno = ?";
            $params = [$idAluno];
            if ($dataRef) {
                $sql .= " AND DATE(data_ref) = ?";
                $params[] = $dataRef;
            }
            $sql .= " ORDER BY data_ref DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Get by lista com filtro - CORRIGIDO: Adicionado COALESCE para valores nulos
        public function getByLista(string $lista, ?int $idAluno = null): array {
            $sql = "
                SELECT 
                    ir.idItensRef, 
                    ir.nome, 
                    ir.quantidade, 
                    ir.medida,
                    COALESCE(n.calorias, 0) as calorias,
                    COALESCE(n.proteinas, 0) as proteinas,
                    COALESCE(n.carboidratos, 0) as carboidratos,
                    COALESCE(n.gorduras, 0) as gorduras,
                    COALESCE(n.medida, ir.medida) as medida_nutriente
                FROM itens_refeicao ir
                LEFT JOIN nutrientes n ON ir.idItensRef = n.alimento_id
                INNER JOIN refeicoes_tipos rt ON ir.id_tipo_refeicao = rt.id
                WHERE rt.nome_tipo = ?
            ";
            $params = [$lista];
            if ($idAluno) {
                $sql .= " AND rt.idAluno = ?";
                $params[] = $idAluno;
            }
            $sql .= " ORDER BY ir.idItensRef ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Insert item
        public function insertItem(int $tipoRefeicaoId, string $nome, float $quantidade, string $medida = 'g'): int {
            $stmt = $this->pdo->prepare("
                INSERT INTO itens_refeicao (id_tipo_refeicao, nome, quantidade, medida) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$tipoRefeicaoId, $nome, $quantidade, $medida]);
            return $this->pdo->lastInsertId();
        }

        // Update quantidade
        public function updateQuantidade(int $id, float $quantidade, string $medida = 'g'): bool {
            $stmt = $this->pdo->prepare("UPDATE itens_refeicao SET quantidade = ?, medida = ? WHERE idItensRef = ?");
            return $stmt->execute([$quantidade, $medida, $id]);
        }

        // Update nutrientes - CORRIGIDO: Adicionado tratamento para inserção se não existir
        public function updateNutrientes(int $id, array $nutrientes, string $medida = 'g'): bool {
            // Primeiro verifica se existe
            $stmt = $this->pdo->prepare("SELECT alimento_id FROM nutrientes WHERE alimento_id = ?");
            $stmt->execute([$id]);
            $existe = $stmt->fetch();
            
            if ($existe) {
                // Atualiza se existir
                $stmt = $this->pdo->prepare("
                    UPDATE nutrientes SET 
                        calorias = ?, proteinas = ?, carboidratos = ?, gorduras = ?, medida = ?
                    WHERE alimento_id = ?
                ");
            } else {
                // Insere se não existir
                $stmt = $this->pdo->prepare("
                    INSERT INTO nutrientes (alimento_id, calorias, proteinas, carboidratos, gorduras, medida)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
            }
            
            return $stmt->execute([
                $nutrientes['calorias'] ?? 0,
                $nutrientes['proteinas'] ?? 0,
                $nutrientes['carboidratos'] ?? 0,
                $nutrientes['gorduras'] ?? 0,
                $medida,
                $id
            ]);
        }

        // Get nutrientes
        public function getNutrientes(int $id): ?array {
            $stmt = $this->pdo->prepare("SELECT * FROM nutrientes WHERE alimento_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }

        // Get item by ID
        public function getItemById(int $idItensRef): ?array {
            $stmt = $this->pdo->prepare("SELECT * FROM itens_refeicao WHERE idItensRef = ?");
            $stmt->execute([$idItensRef]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }

        // Delete item
        public function deleteItem(int $id): bool {
            $stmt = $this->pdo->prepare("DELETE FROM itens_refeicao WHERE idItensRef = ?");
            return $stmt->execute([$id]);
        }

        // Listar totais por refeição - CORRIGIDO: Adicionado COALESCE e filtro por data atual
        public function getTotaisPorRefeicao(?int $idAluno = null): array {
            $sql = "
                SELECT 
                    rt.nome_tipo,
                    COALESCE(SUM(n.calorias), 0) as total_calorias,
                    COALESCE(SUM(n.proteinas), 0) as total_proteinas,
                    COALESCE(SUM(n.carboidratos), 0) as total_carboidratos,
                    COALESCE(SUM(n.gorduras), 0) as total_gorduras
                FROM refeicoes_tipos rt
                LEFT JOIN itens_refeicao ir ON rt.id = ir.id_tipo_refeicao
                LEFT JOIN nutrientes n ON ir.idItensRef = n.alimento_id
                WHERE DATE(rt.data_ref) = CURDATE()
            ";
            $params = [];
            if ($idAluno) {
                $sql .= " AND rt.idAluno = ?";
                $params[] = $idAluno;
            }
            $sql .= " GROUP BY rt.nome_tipo ORDER BY rt.nome_tipo";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Totais gerais - CORRIGIDO: Adicionado COALESCE e filtro por data atual
        public function getTotaisGerais(?int $idAluno = null): array {
            $sql = "
                SELECT 
                    COALESCE(SUM(n.calorias), 0) as total_calorias,
                    COALESCE(SUM(n.proteinas), 0) as total_proteinas,
                    COALESCE(SUM(n.carboidratos), 0) as total_carboidratos,
                    COALESCE(SUM(n.gorduras), 0) as total_gorduras
                FROM itens_refeicao ir
                INNER JOIN refeicoes_tipos rt ON ir.id_tipo_refeicao = rt.id
                LEFT JOIN nutrientes n ON ir.idItensRef = n.alimento_id
                WHERE DATE(rt.data_ref) = CURDATE()
            ";
            $params = [];
            if ($idAluno) {
                $sql .= " AND rt.idAluno = ?";
                $params[] = $idAluno;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: [
                'total_calorias' => 0, 
                'total_proteinas' => 0, 
                'total_carboidratos' => 0, 
                'total_gorduras' => 0
            ];
        }

        // NOVO MÉTODO: Buscar refeição por ID (para validações)
        public function getRefeicaoById(int $idRefeicao): ?array {
            $stmt = $this->pdo->prepare("SELECT * FROM refeicoes_tipos WHERE id = ?");
            $stmt->execute([$idRefeicao]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }

        // NOVO MÉTODO: Verificar se refeição pertence ao aluno
        public function refeicaoPertenceAoAluno(int $idRefeicao, int $idAluno): bool {
            $stmt = $this->pdo->prepare("SELECT id FROM refeicoes_tipos WHERE id = ? AND idAluno = ?");
            $stmt->execute([$idRefeicao, $idAluno]);
            return (bool) $stmt->fetch();
        }

        // NOVO MÉTODO: Listar itens por ID da refeição (alternativo ao getByLista)
        public function getItensByRefeicaoId(int $idRefeicao): array {
            $sql = "
                SELECT 
                    ir.idItensRef, 
                    ir.nome, 
                    ir.quantidade, 
                    ir.medida,
                    COALESCE(n.calorias, 0) as calorias,
                    COALESCE(n.proteinas, 0) as proteinas,
                    COALESCE(n.carboidratos, 0) as carboidratos,
                    COALESCE(n.gorduras, 0) as gorduras,
                    COALESCE(n.medida, ir.medida) as medida_nutriente
                FROM itens_refeicao ir
                LEFT JOIN nutrientes n ON ir.idItensRef = n.alimento_id
                WHERE ir.id_tipo_refeicao = ?
                ORDER BY ir.idItensRef ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$idRefeicao]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

?>