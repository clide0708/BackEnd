<?php

    class AlimentosService {
        private $repository;
        private $pdo;

        public function __construct($repository, $pdo) {
            $this->repository = $repository;
            $this->pdo = $pdo;
        }

        public function listarAlimentos($lista) {
            if (empty($lista)) throw new Exception('Lista não especificada');
            return $this->repository->getByLista($lista);
        }

        public function addAlimento($lista, $nome, $quantidade) {
            if (!$lista || !$nome || !$quantidade) throw new Exception('Dados incompletos');

            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare("SELECT id FROM refeicoes_tipos WHERE nome_tipo = :lista");
                $stmt->execute([':lista' => $lista]);
                $tipo_refeicao_id = $stmt->fetchColumn();
                if (!$tipo_refeicao_id) throw new Exception('Tipo de refeição inválido');

                $alimento_id = $this->repository->insertItem($tipo_refeicao_id, $nome, $quantidade);

                $nutrientes = $this->buscarNutrientesAPI($nome);

                $stmt = $this->pdo->prepare("
                    INSERT INTO nutrientes (alimento_id, calorias, proteinas, carboidratos, gorduras, unidade) 
                    VALUES (:alimento_id, :calorias, :proteinas, :carboidratos, :gorduras, :unidade)
                ");
                $stmt->execute([
                    ':alimento_id'=>$alimento_id,
                    ':calorias'=>$nutrientes['calorias']/100*$quantidade,
                    ':proteinas'=>$nutrientes['proteinas']/100*$quantidade,
                    ':carboidratos'=>$nutrientes['carboidratos']/100*$quantidade,
                    ':gorduras'=>$nutrientes['gorduras']/100*$quantidade,
                    ':unidade'=>$nutrientes['unidade']
                ]);

                $this->pdo->commit();
                return $alimento_id;

            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        public function rmvAlimento($lista, $id) {
            if ($id === '') throw new Exception('ID do alimento não informado');
            return $this->repository->deleteItem($id);
        }

        public function updAlimento($lista, $id, $quantidade_nova) {
            if ($id === '' || $quantidade_nova === '') throw new Exception('Dados incompletos');

            $this->pdo->beginTransaction();
            try {
                // pega dados atuais
                $stmt = $this->pdo->prepare("SELECT quantidade FROM itens_refeicao WHERE idItensRef = :id");
                $stmt->execute([':id'=>$id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) throw new Exception("Item não encontrado");

                $quantidade_antiga = floatval($item['quantidade']);
                $quantidade_nova = floatval($quantidade_nova);

                $nutrientes = $this->repository->getNutrientes($id);
                if (!$nutrientes) throw new Exception("Nutrientes não encontrados");

                // calcula novos nutrientes
                $porGrama = [
                    'calorias' => $nutrientes['calorias'] / $quantidade_antiga,
                    'proteinas' => $nutrientes['proteinas'] / $quantidade_antiga,
                    'carboidratos' => $nutrientes['carboidratos'] / $quantidade_antiga,
                    'gorduras' => $nutrientes['gorduras'] / $quantidade_antiga
                ];

                $novosNutrientes = [
                    'calorias' => $porGrama['calorias'] * $quantidade_nova,
                    'proteinas' => $porGrama['proteinas'] * $quantidade_nova,
                    'carboidratos' => $porGrama['carboidratos'] * $quantidade_nova,
                    'gorduras' => $porGrama['gorduras'] * $quantidade_nova
                ];

                $this->repository->updateQuantidade($id, $quantidade_nova);
                $this->repository->updateNutrientes($id, $novosNutrientes);

                $this->pdo->commit();
                return true;

            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        public function listarTotais() {
            $mealTypes = ['cafe', 'almoco', 'janta', 'outros'];
            $resultado = [];
            $totaisGerais = ['calorias'=>0,'proteinas'=>0,'carboidratos'=>0,'gorduras'=>0];

            foreach ($mealTypes as $tipo) {
                $items = $this->repository->getByLista($tipo);

                $totais = ['calorias'=>0,'proteinas'=>0,'carboidratos'=>0,'gorduras'=>0];

                foreach ($items as $item) {
                    $quantidade = floatval($item['quantidade']);
                    $proporcao = $quantidade / 100;
                    if ($item['calorias'] !== null) {
                        $totais['calorias'] += $proporcao*$item['calorias'];
                        $totais['proteinas'] += $proporcao*$item['proteinas'];
                        $totais['carboidratos'] += $proporcao*$item['carboidratos'];
                        $totais['gorduras'] += $proporcao*$item['gorduras'];
                    }
                }

                $totaisGerais['calorias'] += $totais['calorias'];
                $totaisGerais['proteinas'] += $totais['proteinas'];
                $totaisGerais['carboidratos'] += $totais['carboidratos'];
                $totaisGerais['gorduras'] += $totais['gorduras'];

                $resultado[$tipo] = ['items'=>$items,'totais'=>$totais];
            }

            return ['refeicoes'=>$resultado,'totaisGerais'=>$totaisGerais];
        }

        
        private function traduzirComCache($termoIngles) {
            // Verifica se já existe tradução no cache
            $stmt = $this->pdo->prepare("SELECT termo_portugues FROM traducoes_alimentos WHERE termo_ingles = :termo");
            $stmt->execute([':termo' => $termoIngles]);
            $traducao = $stmt->fetchColumn();
            
            if ($traducao) {
                return $traducao;
            }
            
            // Se não existir, traduz e salva no cache
            $traducao = $this->traduzirParaPortugues($termoIngles);
            
            $stmt = $this->pdo->prepare("INSERT INTO traducoes_alimentos (termo_ingles, termo_portugues) VALUES (:ingles, :portugues)");
            $stmt->execute([':ingles' => $termoIngles, ':portugues' => $traducao]);
            
            return $traducao;
        }
        
        // função auxiliar para buscar nutrientes na API
        private function buscarNutrientesAPI($nome) {
            $apiKey = "617d584fd753442483088b758ccd52fd";
            
            // Traduz o termo de pesquisa para inglês
            $nomeIngles = $this->traduzirParaIngles($nome);
            
            $searchUrl = "https://api.spoonacular.com/food/ingredients/search?query=".urlencode($nomeIngles)."&number=1&apiKey=$apiKey";
            $searchData = json_decode(@file_get_contents($searchUrl), true);

            $nutrientes = ['calorias'=>0,'proteinas'=>0,'carboidratos'=>0,'gorduras'=>0,'unidade'=>'g'];

            if ($searchData && isset($searchData['results'][0]['id'])) {
                $ingredientId = $searchData['results'][0]['id'];
                $nutriUrl = "https://api.spoonacular.com/food/ingredients/$ingredientId/information?amount=100&unit=gram&apiKey=$apiKey";
                $nutriData = json_decode(@file_get_contents($nutriUrl), true);

                if ($nutriData) {
                    // Traduz o nome do alimento
                    $nomeTraduzido = $this->traduzirComCache($searchData['results'][0]['name']);
                    
                    if ($nutriData && isset($nutriData['nutrition']['nutrients'])) {
                        foreach ($nutriData['nutrition']['nutrients'] as $nutriente) {
                            $n = strtolower($nutriente['name']);
                            if ($n==='calories') $nutrientes['calorias']=$nutriente['amount'];
                            if ($n==='protein') $nutrientes['proteinas']=$nutriente['amount'];
                            if ($n==='carbohydrates') $nutrientes['carboidratos']=$nutriente['amount'];
                            if ($n==='fat') $nutrientes['gorduras']=$nutriente['amount'];
                        }
                    }
                }
            }

            return $nutrientes;
        }

    }

?>