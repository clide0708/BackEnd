<?php

    class AlimentosRepository {
        private $pdo;

        public function __construct($pdo) {
            $this->pdo = $pdo;
        }

        //Sessão de traduções API Spoonacular

        // Busca tradução PT -> EN
        public function getTraducaoIngles(string $termoPortugues): ?string {
            $stmt = $this->pdo->prepare("SELECT termo_ingles FROM traducoes_alimentos WHERE termo_portugues = :termo LIMIT 1");
            $stmt->execute([':termo' => $termoPortugues]);
            $result = $stmt->fetchColumn();
            return $result ?: null;
        }

        // Busca tradução EN -> PT
        public function getTraducaoPortugues(string $termoIngles): ?string {
            $stmt = $this->pdo->prepare("SELECT termo_portugues FROM traducoes_alimentos WHERE termo_ingles = :termo LIMIT 1");
            $stmt->execute([':termo' => $termoIngles]);
            $result = $stmt->fetchColumn();
            return $result ?: null;
        }

        // Insere nova tradução
        public function inserirTraducao(string $termoIngles, string $termoPortugues): bool {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO traducoes_alimentos (termo_ingles, termo_portugues) VALUES (:ingles, :portugues)");
                return $stmt->execute([':ingles' => $termoIngles, ':portugues' => $termoPortugues]);
            } catch (PDOException $e) {
                // Pode ser duplicata, ignorar erro
                return false;
            }
        }

        public function getByLista($lista) {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ir.idItensRef,
                    ir.nome, 
                    ir.quantidade,
                    n.calorias,
                    n.proteinas,
                    n.carboidratos,
                    n.gorduras
                FROM itens_refeicao ir
                LEFT JOIN nutrientes n ON ir.idItensRef = n.alimento_id
                WHERE ir.id_tipo_refeicao = (
                    SELECT id FROM refeicoes_tipos WHERE nome_tipo = :lista
                )
                ORDER BY ir.idItensRef ASC
            ");
            $stmt->execute([':lista' => $lista]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function insertItem($tipo_refeicao_id, $nome, $quantidade) {
            $stmt = $this->pdo->prepare("
                INSERT INTO itens_refeicao (id_tipo_refeicao, nome, quantidade) 
                VALUES (:id_tipo, :nome, :quantidade)
            ");
            $stmt->execute([
                ':id_tipo' => $tipo_refeicao_id,
                ':nome' => $nome,
                ':quantidade' => $quantidade
            ]);
            return $this->pdo->lastInsertId();
        }

        public function deleteItem($id) {
            $stmt = $this->pdo->prepare("DELETE FROM itens_refeicao WHERE idItensRef = :id");
            return $stmt->execute([':id' => $id]);
        }

        public function updateQuantidade($id, $quantidade) {
            $stmt = $this->pdo->prepare("UPDATE itens_refeicao SET quantidade = :quantidade WHERE idItensRef = :id");
            return $stmt->execute([':quantidade' => $quantidade, ':id' => $id]);
        }

        public function getNutrientes($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM nutrientes WHERE alimento_id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function updateNutrientes($id, $nutrientes) {
            $stmt = $this->pdo->prepare("
                UPDATE nutrientes SET 
                    calorias = :calorias,
                    proteinas = :proteinas,
                    carboidratos = :carboidratos,
                    gorduras = :gorduras
                WHERE alimento_id = :id
            ");
            return $stmt->execute([
                ':calorias' => $nutrientes['calorias'],
                ':proteinas' => $nutrientes['proteinas'],
                ':carboidratos' => $nutrientes['carboidratos'],
                ':gorduras' => $nutrientes['gorduras'],
                ':id' => $id
            ]);
        }
    }
    
?>