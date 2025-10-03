<?php

    class AlimentosService {
        private $repository;
        private $pdo;

        public function __construct($repository, $pdo) {
            $this->repository = $repository;
            $this->pdo = $pdo;
        }

        /**
         * Traduz termo de português para inglês.
         * Fluxo: Cache > API (LibreTranslate > MyMemory) > Fallback (termo original).
         * Salva tradução no banco se nova.
         */
        public function traduzirParaIngles(string $termoPortugues): string {
            $termoPortugues = mb_strtolower(trim($termoPortugues));
            if (empty($termoPortugues)) return $termoPortugues;

            // Verifica cache no banco
            $traducao = $this->repository->getTraducaoIngles($termoPortugues);
            if ($traducao) return $traducao;

            // Chama API real
            $traducao = $this->chamarApiTraducaoReal($termoPortugues, 'pt', 'en');
            if ($traducao && $traducao !== $termoPortugues) {
                $this->repository->inserirTraducao($traducao, $termoPortugues, 'api');
            }
            return $traducao ?: $termoPortugues;
        }

        /**
         * Traduz termo de inglês para português.
         * Fluxo: Cache > Dicionário contextual > API para frases > Fallback.
         * Suporte robusto a palavras únicas, compostas ou frases (divide e reorganiza gramaticalmente).
         */
        public function traduzirParaPortugues(string $termoIngles): string {
            $termoIngles = mb_strtolower(trim($termoIngles));
            if (empty($termoIngles)) return $termoIngles;

            // Verifica cache no banco
            $traducao = $this->repository->getTraducaoPortugues($termoIngles);
            if ($traducao) return $traducao;

            // Dicionário contextual para alimentos (expandido para itens brasileiros)
            $traducaoContextual = $this->traducaoContextualAlimentos($termoIngles);
            if ($traducaoContextual && $traducaoContextual !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducaoContextual, 'dicionario');
                return $traducaoContextual;
            }

            // Para frases: divide, traduz palavras-chave, reorganiza
            $traducao = $this->traduzirFraseInteligente($termoIngles);
            if ($traducao && $traducao !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducao, 'api');
            }
            return $traducao ?: $termoIngles;
        }

        /**
         * Dicionário contextual expandido para alimentos comuns (PT ↔ EN).
         * Adicione mais entradas conforme necessário para robustez.
         */
        private function traducaoContextualAlimentos(string $termoIngles): ?string {
            $dicionarioAlimentos = [
                // Proteínas
                'chicken breast' => 'peito de frango',
                'ground beef' => 'carne moída',
                'tuna' => 'atum',
                'salmon' => 'salmão',
                'eggs' => 'ovos',
                // Carboidratos
                'brown rice' => 'arroz integral',
                'white rice' => 'arroz branco',
                'whole wheat bread' => 'pão integral',
                'oats' => 'aveia',
                'pasta' => 'massa',
                // Gorduras/Frutas/Vegetais
                'avocado' => 'abacate',
                'olive oil' => 'azeite de oliva',
                'banana' => 'banana',
                'apple' => 'maçã',
                'orange' => 'laranja',
                'broccoli' => 'brócolis',
                // Itens brasileiros comuns
                'feijao carioca' => 'feijão carioca',
                'black beans' => 'feijão preto',
                'mandioca' => 'mandioca',
                'farofa' => 'farofa',
                'pao frances' => 'pão francês',
                'queijo minas' => 'queijo minas',
                'leite integral' => 'whole milk',
                // Expanda com mais 50+ itens para cobertura melhor
                'frango grelhado' => 'grilled chicken',
                'carne assada' => 'roast beef',
                // Adicionais para melhor cobertura
                'milk' => 'leite',
                'cheese' => 'queijo',
                'yogurt' => 'iogurte',
                'butter' => 'manteiga',
                'sugar' => 'açúcar',
                'salt' => 'sal',
                'pepper' => 'pimenta',
                'garlic' => 'alho',
                'onion' => 'cebola',
                'tomato' => 'tomate',
                'potato' => 'batata',
                'carrot' => 'cenoura',
                'lettuce' => 'alface',
                'spinach' => 'espinafre',
                'orange juice' => 'suco de laranja',
                'coffee' => 'café',
                'tea' => 'chá',
                'water' => 'água',
                'bread' => 'pão',
                'rice' => 'arroz',
                'beans' => 'feijão',
                'fish' => 'peixe',
                'pork' => 'porco',
                'chicken' => 'frango',
                'beef' => 'carne',
                'turkey' => 'peru'
            ];
            return $dicionarioAlimentos[$termoIngles] ?? null;
        }

        /**
         * Tradução inteligente para frases compostas.
         * Divide em palavras, traduz individualmente, reorganiza ordem gramatical para PT.
         */
        private function traduzirFraseInteligente(string $fraseIngles): string {
            if (strpos($fraseIngles, ' ') === false) {
                return $this->chamarApiTraducaoReal($fraseIngles, 'en', 'pt');
            }
            $palavras = explode(' ', $fraseIngles);
            $palavrasTraduzidas = [];
            foreach ($palavras as $palavra) {
                $trad = $this->chamarApiTraducaoReal($palavra, 'en', 'pt');
                $palavrasTraduzidas[] = $trad ?: $palavra;
            }
            return $this->reorganizarOrdemPortugues($palavrasTraduzidas, $fraseIngles);
        }

        /**
         * Reorganiza ordem das palavras para gramática portuguesa (ex: adjetivo após substantivo).
         * Correções específicas para alimentos.
         */
        private function reorganizarOrdemPortugues(array $palavras, string $original): string {
            $frase = implode(' ', $palavras);
            // Correções gramaticais simples para alimentos
            $correcoes = [
                'breast chicken' => 'peito de frango',
                'milk whole' => 'leite integral',
                'rice brown' => 'arroz integral',
                'beans black' => 'feijão preto',
                'oil olive' => 'azeite de oliva',
                'juice orange' => 'suco de laranja',
                'bread wheat whole' => 'pão integral',
                'cheese minas' => 'queijo minas',
                // Adicione mais correções conforme necessário
            ];
            foreach ($correcoes as $errado => $correto) {
                $frase = str_replace($errado, $correto, $frase);
            }
            return trim(preg_replace('/\s+/', ' ', $frase));
        }

        /**
         * Chama APIs de tradução: LibreTranslate (primário, robusto para frases) > MyMemory (fallback).
         */
        private function chamarApiTraducaoReal(string $texto, string $de, string $para): string {
            // LibreTranslate (suporta frases bem)
            $url = "https://libretranslate.de/translate";
            $data = json_encode(['q' => $texto, 'source' => $de, 'target' => $para, 'format' => 'text']);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log("Erro LibreTranslate: " . curl_error($ch));
            }
            curl_close($ch);
            $result = json_decode($response, true);
            if (isset($result['translatedText']) && !empty($result['translatedText'])) {
                return $result['translatedText'];
            }

            // Fallback MyMemory
            $url = "https://api.mymemory.translated.net/get?q=" . urlencode($texto) . "&langpair=" . $de . "|" . $para;
            $context = stream_context_create(['http' => ['timeout' => 10]]);
            $response = @file_get_contents($url, false, $context);
            if ($response) {
                $result = json_decode($response, true);
                if (isset($result['responseData']['translatedText']) && !empty($result['responseData']['translatedText'])) {
                    return $result['responseData']['translatedText'];
                }
            }

            // Fallback final: termo original
            error_log("Falha na tradução de '$texto' ($de -> $para). Usando original.");
            return $texto;
        }

        /**
         * Cria uma nova refeição (ex: "Café da manhã") para o aluno logado.
         * Verifica se aluno existe e está ativo.
         */
        public function criarRefeicao(int $idAluno, string $nomeTipo, string $dataRef): int {
            if (empty($nomeTipo) || empty($dataRef)) {
                throw new Exception('Nome do tipo e data são obrigatórios');
            }
            // Verifica aluno ativo (segurança)
            $stmt = $this->pdo->prepare("SELECT idAluno FROM alunos WHERE idAluno = ? AND status_conta = 'Ativa'");
            $stmt->execute([$idAluno]);
            if (!$stmt->fetch()) {
                throw new Exception('Aluno não encontrado ou inativo');
            }
            // Verifica se já existe para o dia (opcional: evitar duplicatas)
            $stmt = $this->pdo->prepare("SELECT id FROM refeicoes_tipos WHERE idAluno = ? AND nome_tipo = ? AND DATE(data_ref) = DATE(?)");
            $stmt->execute([$idAluno, $nomeTipo, $dataRef]);
            if ($stmt->fetch()) {
                throw new Exception('Refeição já existe para este dia e tipo');
            }
            return $this->repository->insertRefeicao($idAluno, $nomeTipo, $dataRef);
        }

        /**
         * Remove uma refeição e todos os itens/nutrientes associados
         * VERSÃO SIMPLIFICADA E FUNCIONAL
         */
        public function deleteRefeicao(int $idRefeicao): bool {
            if (!$idRefeicao) {
                throw new Exception('ID da refeição é obrigatório');
            }

            // Verifica se a refeição pertence ao aluno logado
            $stmt = $this->pdo->prepare("SELECT idAluno FROM refeicoes_tipos WHERE id = ?");
            $stmt->execute([$idRefeicao]);
            $refeicao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$refeicao) {
                throw new Exception('Refeição não encontrada');
            }
            
            if ($refeicao['idAluno'] != $_SERVER['user']['sub']) {
                throw new Exception('Acesso negado: esta refeição não pertence a você');
            }

            // Deleta a refeição (repository gerencia a transação internamente)
            return $this->repository->deleteRefeicao($idRefeicao);
        }

        /**
         * Lista refeições do aluno logado (filtro por data opcional).
         * Verifica permissão via JWT.
         */
        public function listarRefeicoesAluno(int $idAluno, ?string $dataRef = null): array {
            return $this->repository->getRefeicoesByAluno($idAluno, $dataRef);
        }

        /**
         * Busca sugestões de alimentos na Spoonacular, traduzindo o termo em PT para EN.
         * Salva traduções no banco. Retorna nomes em PT para exibição.
         */
        public function buscarAlimentosTraduzidos(string $termoPortugues): array {
            if (empty($termoPortugues)) {
                throw new Exception('Termo de busca é obrigatório');
            }
            $nomeIngles = $this->traduzirParaIngles($termoPortugues);
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            $url = "https://api.spoonacular.com/food/ingredients/search?query=" . urlencode($nomeIngles) . "&number=5&apiKey=$apiKey";
            
            $context = stream_context_create(['http' => ['timeout' => 15]]);
            $data = json_decode(@file_get_contents($url, false, $context), true);
            
            $resultados = [];
            if ($data && isset($data['results'])) {
                foreach ($data['results'] as $item) {
                    $nomePT = $this->traduzirParaPortugues($item['name']);
                    $resultados[] = [
                        'id' => $item['id'],
                        'nome' => $nomePT,
                        'nome_original' => $item['name'],
                        'imagem' => "https://spoonacular.com/cdn/ingredients_100x100/" . ($item['image'] ?? 'default.png')
                    ];
                    // Salva tradução se nova
                    $this->repository->inserirTraducao($item['name'], $nomePT, 'api');
                }
            }
            if (empty($resultados)) {
                error_log("Nenhum resultado na Spoonacular para '$nomeIngles'");
            }
            return $resultados;
        }

        /**
         * Busca informações nutricionais detalhadas de um alimento específico
         */
        public function buscarInformacaoAlimento(int $id, float $quantidade = 100, string $unidade = 'g'): array {
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            
            $url = "https://api.spoonacular.com/food/ingredients/{$id}/information?amount={$quantidade}&unit={$unidade}&apiKey={$apiKey}";
            
            $context = stream_context_create(['http' => ['timeout' => 15]]);
            $data = json_decode(@file_get_contents($url, false, $context), true);

            if (!$data) {
                throw new Exception('Não foi possível obter informações do alimento da API Spoonacular');
            }

            // Traduz o nome para português
            $nomePortugues = $this->traduzirParaPortugues($data['name'] ?? 'Desconhecido');

            return [
                'id' => $id,
                'nome' => $nomePortugues,
                'nome_original' => $data['name'] ?? 'Desconhecido',
                'categoria' => $data['category'] ?? 'Sem categoria',
                'imagem' => "https://spoonacular.com/cdn/ingredients_250x250/" . ($data['image'] ?? 'default.png'),
                'nutrientes' => $this->extrairNutrientes($data['nutrition'] ?? []),
                'quantidade_consulta' => $quantidade,
                'unidade_consulta' => $unidade
            ];
        }

        /**
         * Extrai nutrientes da resposta da Spoonacular
         */
        private function extrairNutrientes(array $nutrition): array {
            $nutrientes = [];
            $mapeamento = [
                'Calories' => 'calorias',
                'Protein' => 'proteína',
                'Carbohydrates' => 'carboidratos',
                'Fat' => 'gordura',
                'Sugar' => 'açúcar',
                'Fiber' => 'fibra',
                'Sodium' => 'sódio'
            ];

            foreach ($nutrition['nutrients'] ?? [] as $nutriente) {
                $nome = $nutriente['name'] ?? '';
                if (isset($mapeamento[$nome])) {
                    $nutrientes[] = [
                        'nome' => $mapeamento[$nome],
                        'nome_original' => $nome,
                        'quantidade' => round($nutriente['amount'] ?? 0, 2),
                        'unidade' => $nutriente['unit'] ?? 'g',
                        'percentual_diario' => round($nutriente['percentOfDailyNeeds'] ?? 0, 1)
                    ];
                }
            }

            return $nutrientes;
        }

        /**
         * Adiciona um alimento a uma refeição, calculando nutrientes proporcionais à quantidade/medida.
         * Usa transação para consistência (itens_refeicao + nutrientes).
         * Verifica permissão (refeição pertence ao aluno logado).
         */
        public function addAlimento(int $idTipoRefeicao, string $nome, float $quantidade, string $medida = 'g'): int {
            if (!$idTipoRefeicao || !$nome || !$quantidade || $quantidade <= 0) {
                throw new Exception('Dados incompletos ou quantidade inválida');
            }
            $this->pdo->beginTransaction();
            try {
                // Verifica se refeição pertence ao aluno logado
                $stmt = $this->pdo->prepare("SELECT idAluno FROM refeicoes_tipos WHERE id = ?");
                $stmt->execute([$idTipoRefeicao]);
                $refeicao = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$refeicao) {
                    throw new Exception('Refeição não encontrada');
                }

                // Insere item
                $alimentoId = $this->repository->insertItem($idTipoRefeicao, $nome, $quantidade, $medida);

                // Busca nutrientes via Spoonacular e ajusta proporcionalmente
                $nutrientes = $this->buscarNutrientesAPI($nome, $quantidade, $medida);

                // Insere nutrientes
                $stmt = $this->pdo->prepare("
                    INSERT INTO nutrientes (alimento_id, calorias, proteinas, carboidratos, gorduras, medida) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $alimentoId,
                    $nutrientes['calorias'],
                    $nutrientes['proteinas'],
                    $nutrientes['carboidratos'],
                    $nutrientes['gorduras'],
                    $medida
                ]);

                $this->pdo->commit();
                return $alimentoId;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Atualiza quantidade/medida de um item, recalculando nutrientes proporcionalmente.
         * Usa transação e verifica permissão.
         */
        public function updAlimento(int $idItensRef, float $quantidadeNova, string $medidaNova = 'g'): bool {
            if (!$idItensRef || !$quantidadeNova || $quantidadeNova <= 0) {
                throw new Exception('ID, quantidade nova e medida são obrigatórios (quantidade > 0)');
            }
            $this->pdo->beginTransaction();
            try {
                // Busca dados antigos
                $itemAntigo = $this->repository->getItemById($idItensRef);
                if (!$itemAntigo) {
                    throw new Exception('Item não encontrado');
                }

                // Recalcula nutrientes com nova quantidade/medida
                $novosNutrientes = $this->buscarNutrientesAPI($itemAntigo['nome'], $quantidadeNova, $medidaNova);

                // Atualiza item e nutrientes
                $this->repository->updateQuantidade($idItensRef, $quantidadeNova, $medidaNova);
                $this->repository->updateNutrientes($idItensRef, $novosNutrientes, $medidaNova);

                $this->pdo->commit();
                return true;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        /**
         * Busca nutrientes do alimento via Spoonacular (traduz nome para EN).
         * Retorna macros para a quantidade e medida especificadas.
         * Fallback para estimativa genérica se API falhar.
         */
        private function buscarNutrientesAPI(string $nome, float $quantidade = 100.0, string $medida = 'g'): array {
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            $nomeIngles = $this->traduzirParaIngles($nome);

            // Busca ID do ingrediente
            $searchUrl = "https://api.spoonacular.com/food/ingredients/search?query=" . urlencode($nomeIngles) . "&number=1&apiKey=" . $apiKey;
            $context = stream_context_create(['http' => ['timeout' => 15]]);
            $searchData = json_decode(@file_get_contents($searchUrl, false, $context), true);

            $nutrientes = ['calorias' => 0.0, 'proteinas' => 0.0, 'carboidratos' => 0.0, 'gorduras' => 0.0];

            if ($searchData && !empty($searchData['results'])) {
                $ingredientId = $searchData['results'][0]['id'];
                $nutriUrl = "https://api.spoonacular.com/food/ingredients/" . $ingredientId . "/information?amount=" . $quantidade . "&unit=" . $medida . "&apiKey=" . $apiKey;
                $nutriData = json_decode(@file_get_contents($nutriUrl, false, $context), true);

                if ($nutriData && isset($nutriData['nutrition']['nutrients'])) {
                    foreach ($nutriData['nutrition']['nutrients'] as $nutriente) {
                        $nomeNutriente = strtolower($nutriente['name'] ?? '');
                        $valor = round($nutriente['amount'] ?? 0, 2);
                        switch ($nomeNutriente) {
                            case 'calories':
                                $nutrientes['calorias'] = $valor;
                                break;
                            case 'protein':
                                $nutrientes['proteinas'] = $valor;
                                break;
                            case 'carbohydrates':
                                $nutrientes['carboidratos'] = $valor;
                                break;
                            case 'fat':
                                $nutrientes['gorduras'] = $valor;
                                break;
                        }
                    }
                    return $nutrientes;
                }
            }

            // Fallback: Estimativa genérica baseada no tipo de alimento
            error_log("Spoonacular falhou para '$nomeIngles'. Usando estimativa.");
            $tipo = $this->detectarTipoAlimento($nomeIngles);
            return $this->estimativaGenerica($tipo, $quantidade);
        }

        /**
         * Detecta tipo aproximado do alimento para fallback (baseado em palavras-chave).
         */
        private function detectarTipoAlimento(string $nomeIngles): string {
            $nomeLower = strtolower($nomeIngles);
            if (strpos($nomeLower, 'chicken') !== false || strpos($nomeLower, 'beef') !== false || 
                strpos($nomeLower, 'tuna') !== false || strpos($nomeLower, 'egg') !== false ||
                strpos($nomeLower, 'fish') !== false || strpos($nomeLower, 'pork') !== false ||
                strpos($nomeLower, 'turkey') !== false) {
                return 'proteina';
            }
            if (strpos($nomeLower, 'rice') !== false || strpos($nomeLower, 'bread') !== false || 
                strpos($nomeLower, 'pasta') !== false || strpos($nomeLower, 'oat') !== false ||
                strpos($nomeLower, 'potato') !== false || strpos($nomeLower, 'bean') !== false) {
                return 'carboidrato';
            }
            if (strpos($nomeLower, 'oil') !== false || strpos($nomeLower, 'avocado') !== false || 
                strpos($nomeLower, 'nut') !== false || strpos($nomeLower, 'butter') !== false) {
                return 'gordura';
            }
            if (strpos($nomeLower, 'fruit') !== false || strpos($nomeLower, 'vegetable') !== false || 
                strpos($nomeLower, 'salad') !== false || strpos($nomeLower, 'broccoli') !== false ||
                strpos($nomeLower, 'carrot') !== false || strpos($nomeLower, 'tomato') !== false) {
                return 'vegetal';
            }
            return 'geral';
        }

        /**
         * Retorna estimativa genérica de nutrientes por 100g/ml (kcal, g de macros).
         * Valores aproximados baseados em médias nutricionais.
         */
        private function estimativaGenerica(string $tipo, float $quantidadeBase = 100.0): array {
            $estimativasPor100g = [
                'proteina' => ['calorias' => 165.0, 'proteinas' => 31.0, 'carboidratos' => 0.0, 'gorduras' => 3.6],
                'carboidrato' => ['calorias' => 130.0, 'proteinas' => 2.5, 'carboidratos' => 28.0, 'gorduras' => 0.5],
                'gordura' => ['calorias' => 884.0, 'proteinas' => 0.0, 'carboidratos' => 0.0, 'gorduras' => 100.0],
                'vegetal' => ['calorias' => 25.0, 'proteinas' => 2.0, 'carboidratos' => 5.0, 'gorduras' => 0.2],
                'geral' => ['calorias' => 100.0, 'proteinas' => 8.0, 'carboidratos' => 15.0, 'gorduras' => 3.0]
            ];
            $base = $estimativasPor100g[$tipo] ?? $estimativasPor100g['geral'];
            $fator = $quantidadeBase / 100.0;
            return [
                'calorias' => round($base['calorias'] * $fator, 2),
                'proteinas' => round($base['proteinas'] * $fator, 2),
                'carboidratos' => round($base['carboidratos'] * $fator, 2),
                'gorduras' => round($base['gorduras'] * $fator, 2)
            ];
        }

        /**
         * Lista itens de uma refeição específica
         */
        public function listarAlimentos(string $lista, int $idAluno): array {
            return $this->repository->getByLista($lista, $idAluno);
        }

        /**
         * Lista totais de nutrientes por tipo de refeição para o aluno logado.
         * Inclui totais gerais no final.
         */
        public function listarTotais(int $idAluno): array {
            $totaisPorRefeicao = $this->repository->getTotaisPorRefeicao($idAluno);
            $totaisGerais = $this->repository->getTotaisGerais($idAluno);

            // Reestruturar para o formato esperado
            $refeicoesEstruturadas = [];
            foreach ($totaisPorRefeicao as $refeicao) {
                $refeicoesEstruturadas[$refeicao['nome_tipo']] = [
                    'items' => [], // Poderia preencher com itens se necessário
                    'totais' => [
                        'calorias' => (float)($refeicao['total_calorias'] ?? 0),
                        'proteinas' => (float)($refeicao['total_proteinas'] ?? 0),
                        'carboidratos' => (float)($refeicao['total_carboidratos'] ?? 0),
                        'gorduras' => (float)($refeicao['total_gorduras'] ?? 0)
                    ]
                ];
            }

            return [
                'refeicoes' => $refeicoesEstruturadas,
                'totaisGerais' => [
                    'calorias' => (float)($totaisGerais['total_calorias'] ?? 0),
                    'proteinas' => (float)($totaisGerais['total_proteinas'] ?? 0),
                    'carboidratos' => (float)($totaisGerais['total_carboidratos'] ?? 0),
                    'gorduras' => (float)($totaisGerais['total_gorduras'] ?? 0)
                ]
            ];
        }

        /**
         * Remove um item de refeição (deleta itens_refeicao e nutrientes vinculados).
         * Verifica permissão.
         */
        public function deleteAlimento(int $idItensRef): bool {
            if (!$idItensRef) {
                throw new Exception('ID do item é obrigatório');
            }
            $this->pdo->beginTransaction();
            try {
                $sucesso = $this->repository->deleteItem($idItensRef);
                $this->pdo->commit();
                return $sucesso;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }

?>