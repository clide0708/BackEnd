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
         * Traduz termo de inglês para português BRASILEIRO.
         * Fluxo: Cache > Dicionário contextual robusto > API para frases > Fallback.
         * Trata palavras únicas, compostas e frases complexas.
         */
        public function traduzirParaPortugues(string $termoIngles): string {
            $termoIngles = mb_strtolower(trim($termoIngles));
            if (empty($termoIngles)) return $termoIngles;

            // Verifica cache no banco
            $traducao = $this->repository->getTraducaoPortugues($termoIngles);
            if ($traducao) return $traducao;

            // Dicionário contextual expandido para alimentos brasileiros
            $traducaoContextual = $this->traducaoContextualAlimentosBrasileiros($termoIngles);
            if ($traducaoContextual && $traducaoContextual !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducaoContextual, 'dicionario');
                return $traducaoContextual;
            }

            // Para frases complexas: usa API com pós-processamento para contexto brasileiro
            $traducao = $this->traduzirFraseComplexaBrasileira($termoIngles);
            if ($traducao && $traducao !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducao, 'api');
            }
            return $traducao ?: $termoIngles;
        }

        /**
         * Dicionário contextual EXPANDIDO para alimentos no contexto brasileiro
         * Inclui traduções específicas para produtos e preparações típicas do Brasil
         */
        private function traducaoContextualAlimentosBrasileiros(string $termoIngles): ?string {
            $dicionarioAlimentos = [
                // Frutas e vegetais
                'apple' => 'maçã',
                'apple juice' => 'suco de maçã',
                'apple cider' => 'cidra de maçã',
                'apple jelly' => 'geleia de maçã',
                'apple sauce' => 'purê de maçã',
                'applesauce' => 'purê de maçã',
                'banana' => 'banana',
                'orange' => 'laranja',
                'orange juice' => 'suco de laranja',
                'grape' => 'uva',
                'grape juice' => 'suco de uva',
                'strawberry' => 'morango',
                'strawberry jam' => 'geleia de morango',
                'watermelon' => 'melancia',
                'pineapple' => 'abacaxi',
                'pineapple juice' => 'suco de abacaxi',
                'mango' => 'manga',
                'papaya' => 'mamão',
                'avocado' => 'abacate',
                'guava' => 'goiaba',
                'passion fruit' => 'maracujá',
                'açaí' => 'açaí',
                
                // Vegetais e legumes
                'broccoli' => 'brócolis',
                'carrot' => 'cenoura',
                'lettuce' => 'alface',
                'spinach' => 'espinafre',
                'kale' => 'couve',
                'cabbage' => 'repolho',
                'tomato' => 'tomate',
                'potato' => 'batata',
                'sweet potato' => 'batata doce',
                'onion' => 'cebola',
                'garlic' => 'alho',
                'ginger' => 'gengibre',
                'cucumber' => 'pepino',
                'bell pepper' => 'pimentão',
                'chili pepper' => 'pimenta',
                
                // Grãos e cereais
                'rice' => 'arroz',
                'brown rice' => 'arroz integral',
                'white rice' => 'arroz branco',
                'beans' => 'feijão',
                'black beans' => 'feijão preto',
                'pinto beans' => 'feijão carioca',
                'lentils' => 'lentilha',
                'chickpeas' => 'grão-de-bico',
                'oats' => 'aveia',
                'quinoa' => 'quinoa',
                'corn' => 'milho',
                'popcorn' => 'pipoca',
                'flour' => 'farinha',
                'wheat flour' => 'farinha de trigo',
                
                // Proteínas
                'chicken' => 'frango',
                'chicken breast' => 'peito de frango',
                'chicken thigh' => 'coxa de frango',
                'beef' => 'carne bovina',
                'ground beef' => 'carne moída',
                'steak' => 'bife',
                'pork' => 'porco',
                'pork chop' => 'costeleta de porco',
                'bacon' => 'bacon',
                'sausage' => 'linguiça',
                'fish' => 'peixe',
                'salmon' => 'salmão',
                'tuna' => 'atum',
                'sardine' => 'sardinha',
                'shrimp' => 'camarão',
                'egg' => 'ovo',
                'eggs' => 'ovos',
                
                // Laticínios
                'milk' => 'leite',
                'whole milk' => 'leite integral',
                'skim milk' => 'leite desnatado',
                'cheese' => 'queijo',
                'mozzarella cheese' => 'queijo mussarela',
                'cheddar cheese' => 'queijo cheddar',
                'cream cheese' => 'cream cheese',
                'yogurt' => 'iogurte',
                'greek yogurt' => 'iogurte grego',
                'butter' => 'manteiga',
                'margarine' => 'margarina',
                'cream' => 'creme de leite',
                'whipped cream' => 'chantilly',
                
                // Pães e massas
                'bread' => 'pão',
                'whole wheat bread' => 'pão integral',
                'white bread' => 'pão branco',
                'french bread' => 'pão francês',
                'toast' => 'torrada',
                'pasta' => 'massa',
                'spaghetti' => 'espaguete',
                'penne' => 'penne',
                'lasagna' => 'lasanha',
                'noodles' => 'macarrão',
                
                // Nozes e sementes
                'peanut' => 'amendoim',
                'peanut butter' => 'manteiga de amendoim',
                'almond' => 'amêndoa',
                'walnut' => 'noz',
                'cashew' => 'castanha de caju',
                'brazil nut' => 'castanha-do-pará',
                'sunflower seed' => 'semente de girassol',
                'chia seed' => 'semente de chia',
                'flaxseed' => 'linhaça',
                
                // Bebidas
                'water' => 'água',
                'sparkling water' => 'água com gás',
                'coffee' => 'café',
                'tea' => 'chá',
                'green tea' => 'chá verde',
                'black tea' => 'chá preto',
                'soda' => 'refrigerante',
                'beer' => 'cerveja',
                'wine' => 'vinho',
                
                // Doces e sobremesas
                'sugar' => 'açúcar',
                'brown sugar' => 'açúcar mascavo',
                'honey' => 'mel',
                'chocolate' => 'chocolate',
                'dark chocolate' => 'chocolate amargo',
                'milk chocolate' => 'chocolate ao leite',
                'ice cream' => 'sorvete',
                'cake' => 'bolo',
                'cookie' => 'biscoito',
                'candy' => 'doce',
                
                // Óleos e condimentos
                'oil' => 'óleo',
                'olive oil' => 'azeite de oliva',
                'coconut oil' => 'óleo de coco',
                'salt' => 'sal',
                'black pepper' => 'pimenta-do-reino',
                'soy sauce' => 'molho de soja',
                'vinegar' => 'vinagre',
                'ketchup' => 'ketchup',
                'mayonnaise' => 'maionese',
                'mustard' => 'mostarda',
                
                // Preparações brasileiras
                'feijoada' => 'feijoada',
                'farofa' => 'farofa',
                'coxinha' => 'coxinha',
                'pão de queijo' => 'pão de queijo',
                'acarajé' => 'acarajé',
                'moqueca' => 'moqueca',
                'brigadeiro' => 'brigadeiro',
                'beijinho' => 'beijinho',
                'quindim' => 'quindim',
                'aipim' => 'aipim',
                'mandioca' => 'mandioca',
                'cuscuz' => 'cuscuz'
            ];

            return $dicionarioAlimentos[$termoIngles] ?? null;
        }

        /**
         * Tradução inteligente para frases complexas no contexto brasileiro
         * Usa API + pós-processamento específico para alimentos
         */
        private function traduzirFraseComplexaBrasileira(string $fraseIngles): string {
            // Primeiro tenta tradução completa via API
            $traducaoAPI = $this->chamarApiTraducaoReal($fraseIngles, 'en', 'pt');
            
            if ($traducaoAPI === $fraseIngles) {
                return $fraseIngles;
            }

            // Aplica correções pós-tradução para contexto brasileiro
            return $this->aplicarCorrecoesBrasileiras($traducaoAPI, $fraseIngles);
        }

        /**
         * Aplica correções específicas para o português brasileiro
         * Corrige termos técnicos, medidas e expressões comuns
         */
        private function aplicarCorrecoesBrasileiras(string $traducao, string $original): string {
            $correcoes = [
                // Termos técnicos e medidas
                'colher de sopa' => 'colher de sopa',
                'colher de chá' => 'colher de chá',
                'xícara de chá' => 'xícara',
                'copos' => 'copos',
                'gramas' => 'gramas',
                'quilogramas' => 'quilogramas',
                'mililitros' => 'mililitros',
                'litros' => 'litros',
                
                // Expressões comuns em receitas
                'picado' => 'picado',
                'cortado em cubos' => 'cortado em cubos',
                'fatiado' => 'fatiado',
                'ralado' => 'ralado',
                'cozido' => 'cozido',
                'grelhado' => 'grelhado',
                'assado' => 'assado',
                'frito' => 'frito',
                'cru' => 'cru',
                
                // Correções de termos específicos
                'gelatina' => 'geleia', // apple jelly -> geleia de maçã, não gelatina
                'molho' => 'molho',
                'caldo' => 'caldo',
                'tempero' => 'tempero',
                'marinada' => 'marinada',
                'cobertura' => 'cobertura',
                'recheio' => 'recheio'
            ];

            // Aplica correções de ordem gramatical para alimentos
            $traducao = $this->corrigirOrdemGramatical($traducao);
            
            // Aplica substituições específicas
            foreach ($correcoes as $errado => $correto) {
                // Busca por variações com diferentes espaçamentos
                $padrao = '/\b' . preg_quote($errado, '/') . '\b/i';
                $traducao = preg_replace($padrao, $correto, $traducao);
            }

            return trim(preg_replace('/\s+/', ' ', $traducao));
        }

        /**
         * Corrige ordem gramatical para português brasileiro
         * Ex: "apple red" -> "maçã vermelha", não "vermelha maçã"
         */
        private function corrigirOrdemGramatical(string $frase): string {
            $padroes = [
                // Adjetivo antes do substantivo (inglês) -> substantivo + adjetivo (português)
                '/(vermelho|vermelha|azul|verde|amarelo|branco|preto|doce|salgado|azedo|fresco|seco|cozido|cru|grande|pequeno) (maçã|banana|laranja|carne|frango|peixe|pão|queijo|arroz|feijão)/i' => '$2 $1',
                
                // Medidas: "1 cup" -> "1 xícara"
                '/(\d+)\s*(cup|cups)/i' => '$1 xícara',
                '/(\d+)\s*(tbsp|tablespoon|tablespoons)/i' => '$1 colher de sopa',
                '/(\d+)\s*(tsp|teaspoon|teaspoons)/i' => '$1 colher de chá',
                '/(\d+)\s*(oz|ounce|ounces)/i' => '$1 onça',
                '/(\d+)\s*(lb|pound|pounds)/i' => '$1 libra'
            ];

            foreach ($padroes as $padrao => $substituicao) {
                $frase = preg_replace($padrao, $substituicao, $frase);
            }

            return $frase;
        }

        /**
         * Chama APIs de tradução: LibreTranslate (primário) > MyMemory (fallback)
         */
        private function chamarApiTraducaoReal(string $texto, string $de, string $para): string {
            // LibreTranslate (suporta frases bem)
            $url = "https://libretranslate.de/translate";
            $data = json_encode([
                'q' => $texto, 
                'source' => $de, 
                'target' => $para, 
                'format' => 'text'
            ]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                error_log("Erro LibreTranslate: " . curl_error($ch));
            }
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $result = json_decode($response, true);
                if (isset($result['translatedText']) && !empty(trim($result['translatedText']))) {
                    return trim($result['translatedText']);
                }
            }

            // Fallback MyMemory
            $url = "https://api.mymemory.translated.net/get?q=" . urlencode($texto) . "&langpair=" . $de . "|" . $para;
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            if ($response) {
                $result = json_decode($response, true);
                if (isset($result['responseData']['translatedText']) && 
                    !empty(trim($result['responseData']['translatedText']))) {
                    return trim($result['responseData']['translatedText']);
                }
            }

            // Fallback final: termo original
            error_log("Falha na tradução de '$texto' ($de -> $para). Usando original.");
            return $texto;
        }

        /**
         * Cria uma nova refeição (ex: "Café da manhã") para o aluno logado.
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

            return $this->repository->deleteRefeicao($idRefeicao);
        }

        /**
         * Lista refeições do aluno logado - VERSÃO CORRIGIDA
         */
        public function listarRefeicoesAluno(int $idAluno, ?string $dataRef = null): array {
            try {
                // DEBUG
                error_log("Service - ID Aluno: $idAluno, Data Ref: " . ($dataRef ?? 'null'));
                
                $refeicoes = $this->repository->getRefeicoesByAluno($idAluno, $dataRef);
                
                // DEBUG
                error_log("Service - Refêições encontradas: " . count($refeicoes));
                
                return $refeicoes;
            } catch (Exception $e) {
                error_log("Erro no Service listarRefeicoesAluno: " . $e->getMessage());
                throw $e;
            }
        }

        /**
         * Busca sugestões de alimentos na Spoonacular com tradução robusta
         */
        public function buscarAlimentosTraduzidos(string $termoPortugues): array {
            if (empty($termoPortugues)) {
                throw new Exception('Termo de busca é obrigatório');
            }
            
            $nomeIngles = $this->traduzirParaIngles($termoPortugues);
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            $url = "https://api.spoonacular.com/food/ingredients/search?query=" . urlencode($nomeIngles) . "&number=10&apiKey=$apiKey";
            
            $context = stream_context_create([
                'http' => ['timeout' => 15],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $data = json_decode(@file_get_contents($url, false, $context), true);
            
            $resultados = [];
            if ($data && isset($data['results'])) {
                foreach ($data['results'] as $item) {
                    // Usa a tradução robusta para português brasileiro
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
                error_log("Nenhum resultado na Spoonacular para '$nomeIngles' (original: '$termoPortugues')");
            }
            
            return $resultados;
        }

        /**
         * Busca informações nutricionais detalhadas
         */
        public function buscarInformacaoAlimento(int $id, float $quantidade = 100, string $unidade = 'g'): array {
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            
            $url = "https://api.spoonacular.com/food/ingredients/{$id}/information?amount={$quantidade}&unit={$unidade}&apiKey={$apiKey}";
            
            $context = stream_context_create([
                'http' => ['timeout' => 15],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $data = json_decode(@file_get_contents($url, false, $context), true);

            if (!$data) {
                throw new Exception('Não foi possível obter informações do alimento da API Spoonacular');
            }

            // Usa tradução robusta para o nome
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

        private function buscarNutrientesAPI(string $nome, float $quantidade = 100.0, string $medida = 'g'): array {
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            $nomeIngles = $this->traduzirParaIngles($nome);

            // Busca ID do ingrediente
            $searchUrl = "https://api.spoonacular.com/food/ingredients/search?query=" . urlencode($nomeIngles) . "&number=1&apiKey=" . $apiKey;
            $context = stream_context_create([
                'http' => ['timeout' => 15],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
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

            // Fallback: Estimativa genérica
            error_log("Spoonacular falhou para '$nomeIngles'. Usando estimativa.");
            $tipo = $this->detectarTipoAlimento($nomeIngles);
            return $this->estimativaGenerica($tipo, $quantidade);
        }

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
         * Listar todos os alimentos de uma refeição específica
         */
        public function listarAlimentosRefeicao(int $idRefeicao, int $idAluno): array {
            if (!$idRefeicao) {
                throw new Exception('ID da refeição é obrigatório');
            }

            // Verifica se a refeição pertence ao aluno
            $stmt = $this->pdo->prepare("SELECT idAluno FROM refeicoes_tipos WHERE id = ?");
            $stmt->execute([$idRefeicao]);
            $refeicao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$refeicao) {
                throw new Exception('Refeição não encontrada');
            }
            
            if ($refeicao['idAluno'] != $idAluno) {
                throw new Exception('Acesso negado: esta refeição não pertence a você');
            }

            return $this->repository->getItensByRefeicaoId($idRefeicao);
        }

        // public function listarAlimentos(string $lista, int $idAluno): array {
        //     return $this->repository->getByLista($lista, $idAluno);
        // }

        public function listarTotais(int $idAluno): array {
            $totaisPorRefeicao = $this->repository->getTotaisPorRefeicao($idAluno);
            $totaisGerais = $this->repository->getTotaisGerais($idAluno);

            $refeicoesEstruturadas = [];
            foreach ($totaisPorRefeicao as $refeicao) {
                $refeicoesEstruturadas[$refeicao['nome_tipo']] = [
                    'items' => [],
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
                'totaisGerais' => [ // ← CORRIGIDO: mudar 'totais_gerais' para 'totaisGerais'
                    'calorias' => (float)($totaisGerais['total_calorias'] ?? 0),
                    'proteinas' => (float)($totaisGerais['total_proteinas'] ?? 0),
                    'carboidratos' => (float)($totaisGerais['total_carboidratos'] ?? 0),
                    'gorduras' => (float)($totaisGerais['total_gorduras'] ?? 0)
                ]
            ];
        }

        public function listarRefeicoesCompletas(int $idAluno, ?string $dataRef = null): array {
            // Busca as refeições básicas
            $refeicoesBasicas = $this->repository->getRefeicoesByAluno($idAluno, $dataRef);
            
            $refeicoesCompletas = [];
            
            foreach ($refeicoesBasicas as $refeicao) {
                $idRefeicao = $refeicao['id'];
                
                // Busca os alimentos desta refeição usando o método correto
                $alimentos = $this->repository->getItensByRefeicaoId($idRefeicao);
                
                // Calcula totais da refeição
                $totaisRefeicao = [
                    'total_calorias' => 0,
                    'total_proteinas' => 0,
                    'total_carboidratos' => 0,
                    'total_gorduras' => 0
                ];
                
                foreach ($alimentos as $alimento) {
                    $totaisRefeicao['total_calorias'] += (float)$alimento['calorias'];
                    $totaisRefeicao['total_proteinas'] += (float)$alimento['proteinas'];
                    $totaisRefeicao['total_carboidratos'] += (float)$alimento['carboidratos'];
                    $totaisRefeicao['total_gorduras'] += (float)$alimento['gorduras'];
                }
                
                // Formata a resposta
                $refeicoesCompletas[] = [
                    'id' => $refeicao['id'],
                    'nome_tipo' => $refeicao['nome_tipo'],
                    'data_ref' => $refeicao['data_ref'],
                    'alimentos' => $alimentos,
                    'totais' => [
                        'calorias' => round($totaisRefeicao['total_calorias'], 2),
                        'proteinas' => round($totaisRefeicao['total_proteinas'], 2),
                        'carboidratos' => round($totaisRefeicao['total_carboidratos'], 2),
                        'gorduras' => round($totaisRefeicao['total_gorduras'], 2)
                    ]
                ];
            }
            
            return $refeicoesCompletas;
        }
    }

?>