<?php

    class AlimentosService {
        private $repository;
        private $pdo;

        public function __construct($repository, $pdo) {
            $this->repository = $repository;
            $this->pdo = $pdo;
        }

        // Traduz termo PT -> EN com cache
        public function traduzirParaIngles(string $termoPortugues): string {
            $termoPortugues = mb_strtolower(trim($termoPortugues));
            
            if (empty($termoPortugues)) {
                return $termoPortugues;
            }

            // Verifica cache
            $traducao = $this->repository->getTraducaoIngles($termoPortugues);
            if ($traducao) {
                return $traducao;
            }

            // Se não existir, chama API de tradução real
            $traducao = $this->chamarApiTraducaoReal($termoPortugues, 'pt', 'en');

            // Salva no cache apenas se a tradução for diferente do original
            if ($traducao && $traducao !== $termoPortugues) {
                $this->repository->inserirTraducao($traducao, $termoPortugues);
            }

            return $traducao ?: $termoPortugues;
        }

        // Traduz termo EN -> PT com contexto alimentar brasileiro
        public function traduzirParaPortugues(string $termoIngles): string {
            $termoIngles = mb_strtolower(trim($termoIngles));
            
            if (empty($termoIngles)) {
                return $termoIngles;
            }

            // Verifica cache primeiro
            $traducao = $this->repository->getTraducaoPortugues($termoIngles);
            if ($traducao) {
                return $traducao;
            }

            // Tenta tradução contextual inteligente primeiro
            $traducaoContextual = $this->traducaoContextualAlimentos($termoIngles);
            if ($traducaoContextual && $traducaoContextual !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducaoContextual);
                return $traducaoContextual;
            }

            // Se não for termo conhecido, chama API de tradução com tratamento de frases
            $traducao = $this->traduzirFraseInteligente($termoIngles);

            // Salva no cache
            if ($traducao && $traducao !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducao);
            }

            return $traducao ?: $termoIngles;
        }

        // TRADUÇÃO CONTEXTUAL INTELIGENTE - Nomes reais de alimentos no Brasil
        private function traducaoContextualAlimentos(string $termoIngles): string {
            $dicionarioAlimentos = [
                // Massas e Panificação
                'filo pastry' => 'massa folhada',
                'puff pastry dough' => 'massa folhada',
                'puff pastry' => 'massa folhada',
                'lasagne noodles' => 'massa para lasanha',
                'lasagne sheets' => 'massa para lasanha',
                'lasagna noodles' => 'massa para lasanha',
                'fresh lasagne sheets' => 'massa para lasanha fresca',
                'cooked lasagne noodles' => 'massa para lasanha cozida',
                'cooked lasagna noodles' => 'massa para lasanha cozida',
                'oven ready lasagne noodles' => 'massa para lasanha pré-cozida',
                'oven ready lasagna noodles' => 'massa para lasanha pré-cozida',
                'graham crackers' => 'biscoito maisena',
                'sheet gelatin' => 'gelatina em folha',
                'pastry dough' => 'massa folhada',
                'pie crust' => 'massa de torta',
                'pizza dough' => 'massa de pizza',
                
                // Folhas e Vegetais
                'banana leaves' => 'folha de bananeira',
                'lettuce' => 'alface',
                'spinach' => 'espinafre',
                'kale' => 'couve',
                'cabbage' => 'repolho',
                'nori' => 'alga nori',
                'bean curd sheets' => 'folha de tofu',
                'curry leaves' => 'folhas de curry',
                'mint leaves' => 'folhas de hortelã',
                'basil leaves' => 'folhas de manjericão',
                
                // Carnes
                'chicken breast' => 'peito de frango',
                'chicken thigh' => 'coxa de frango',
                'chicken wing' => 'asa de frango',
                'chicken leg' => 'sobrecoxa de frango',
                'beef steak' => 'bife',
                'ground beef' => 'carne moída',
                'pork chop' => 'costeleta de porco',
                'pork loin' => 'lombo de porco',
                'bacon strips' => 'fatias de bacon',
                'sausage links' => 'linguiças',
                
                // Laticínios
                'cream cheese' => 'cream cheese',
                'cottage cheese' => 'queijo cottage',
                'parmesan cheese' => 'queijo parmesão',
                'mozzarella cheese' => 'muçarela',
                'cheddar cheese' => 'queijo cheddar',
                'greek yogurt' => 'iogurte grego',
                'sour cream' => 'creme azedo',
                'whipped cream' => 'chantilly',
                'ice cream' => 'sorvete',
                'condensed milk' => 'leite condensado',
                'evaporated milk' => 'leite evaporado',
                
                // Grãos e Cereais
                'brown rice' => 'arroz integral',
                'white rice' => 'arroz branco',
                'wild rice' => 'arroz selvagem',
                'whole wheat' => 'trigo integral',
                'oatmeal' => 'aveia em flocos',
                'whole grain' => 'grão integral',
                'bread crumbs' => 'farinha de rosca',
                'pasta sauce' => 'molho para massa',
                
                // Nozes e Sementes
                'peanut butter' => 'manteiga de amendoim',
                'almond milk' => 'leite de amêndoa',
                'coconut milk' => 'leite de coco',
                'sunflower seeds' => 'sementes de girassol',
                'chia seeds' => 'sementes de chia',
                'flax seeds' => 'sementes de linhaça',
                'pumpkin seeds' => 'sementes de abóbora',
                
                // Frutas
                'banana' => 'banana',
                'apple' => 'maçã',
                'orange' => 'laranja',
                'strawberry' => 'morango',
                'pineapple' => 'abacaxi',
                'grape' => 'uva',
                'mango' => 'manga',
                'papaya' => 'mamão',
                'lemon' => 'limão',
                'lime' => 'lima',
                
                // Legumes
                'carrot' => 'cenoura',
                'potato' => 'batata',
                'tomato' => 'tomato',
                'onion' => 'cebola',
                'garlic' => 'alho',
                'bell pepper' => 'pimentão',
                'cucumber' => 'pepino',
                'zucchini' => 'abobrinha',
                'eggplant' => 'berinjela',
                'broccoli' => 'brócolis',
                
                // Bebidas
                'orange juice' => 'suco de laranja',
                'apple juice' => 'suco de maçã',
                'pineapple juice' => 'suco de abacaxi',
                'green tea' => 'chá verde',
                'black tea' => 'chá preto',
                'coffee' => 'café',
                
                // Temperos e Condimentos
                'olive oil' => 'azeite de oliva',
                'coconut oil' => 'óleo de coco',
                'vegetable oil' => 'óleo vegetal',
                'soy sauce' => 'molho de soja',
                'maple syrup' => 'xarope de bordo',
                'vanilla extract' => 'baunilha',
                'baking powder' => 'fermento em pó',
                'baking soda' => 'bicarbonato de sódio',
                'cocoa powder' => 'cacau em pó',
                'cinnamon' => 'canela',
                
                // Outros
                'whole milk' => 'leite integral',
                'skim milk' => 'leite desnatado',
                'chocolate chips' => 'gotas de chocolate',
                'yeast' => 'fermento biológico',
                'honey' => 'mel',
                'sugar' => 'açúcar',
                'salt' => 'sal',
                'pepper' => 'pimenta',
                
                // Termos culinários
                'fresh' => 'fresco',
                'cooked' => 'cozido',
                'baked' => 'assado',
                'fried' => 'frito',
                'roasted' => 'tostado',
                'grilled' => 'grelhado',
                'raw' => 'cru',
                'frozen' => 'congelado',
                'canned' => 'enlatado',
                'dried' => 'seco',
                'powdered' => 'em pó',
                'crushed' => 'triturado',
                'sliced' => 'fatiado',
                'chopped' => 'picado',
                'grated' => 'ralado',
                'minced' => 'moído'
            ];

            return $dicionarioAlimentos[$termoIngles] ?? $termoIngles;
        }

        // TRADUÇÃO INTELIGENTE DE FRASES - Mantém ordem gramatical correta
        private function traduzirFraseInteligente(string $fraseIngles): string {
            // Se for uma única palavra, usa tradução simples
            if (strpos($fraseIngles, ' ') === false) {
                return $this->chamarApiTraducaoReal($fraseIngles, 'en', 'pt');
            }

            // Verifica se é uma frase conhecida no dicionário
            $traducaoFrase = $this->traducaoContextualAlimentos($fraseIngles);
            if ($traducaoFrase !== $fraseIngles) {
                return $traducaoFrase;
            }

            // Divide a frase em palavras
            $palavras = explode(' ', $fraseIngles);
            
            // Traduz cada palavra individualmente
            $palavrasTraduzidas = [];
            foreach ($palavras as $palavra) {
                $palavrasTraduzidas[] = $this->chamarApiTraducaoReal($palavra, 'en', 'pt');
            }

            // Reorganiza a frase na ordem gramatical correta do português
            return $this->reorganizarOrdemPortugues($palavrasTraduzidas, $fraseIngles);
        }

        // REORGANIZA A ORDEM PARA O PORTUGUÊS CORRETO
        private function reorganizarOrdemPortugues(array $palavras, string $original): string {
            $frase = implode(' ', $palavras);
            
            // Corrige ordens específicas que são diferentes entre inglês e português
            $correcoesGramaticais = [
                // Padrão: adjetivo + substantivo (EN) -> substantivo + adjetivo (PT)
                '/(\w+)\s+(fresh|frozen|cooked|baked|fried|raw|dried|powdered|crushed|sliced|chopped|grated|minced|roasted|grilled)$/' => '$2 $1',
                
                // Exemplos específicos comuns
                'cream cheese' => 'cream cheese',
                'cottage cheese' => 'queijo cottage',
                'parmesan cheese' => 'queijo parmesão',
                'lasagne noodles' => 'massa para lasanha',
                'banana leaves' => 'folhas de bananeira',
                'bean curd' => 'tofu',
                'puff pastry' => 'massa folhada',
                'graham crackers' => 'biscoito maisena',
                'sheet gelatin' => 'gelatina em folha',
                'orange juice' => 'suco de laranja',
                'apple juice' => 'suco de maçã',
                'chocolate chips' => 'gotas de chocolate',
                'baking powder' => 'fermento em pó',
                'baking soda' => 'bicarbonato de sódio'
            ];

            // Aplica correções gramaticais
            foreach ($correcoesGramaticais as $padrao => $substituicao) {
                if (preg_match('/' . preg_quote($padrao, '/') . '/', $frase)) {
                    $frase = str_replace($padrao, $substituicao, $frase);
                } elseif (preg_match($padrao, $frase)) {
                    $frase = preg_replace($padrao, $substituicao, $frase);
                }
            }

            // Remove duplos espaços
            $frase = preg_replace('/\s+/', ' ', $frase);
            
            return trim($frase);
        }

        // DICIONÁRIO DE EXCEÇÕES - Alimentos que não seguem regras gerais
        private function obterTraducaoExcecao(string $termoIngles): ?string {
            $excecoes = [
                // Ingredientes japoneses
                'nori' => 'alga nori',
                'tofu' => 'tofu',
                'miso' => 'missô',
                'sake' => 'saquê',
                'wasabi' => 'wasabi',
                'sashimi' => 'sashimi',
                'sushi' => 'sushi',
                'tempura' => 'tempurá',
                'edamame' => 'edamame',
                'kimchi' => 'kimchi',
                'ramen' => 'lámen',
                
                // Massas italianas
                'pasta' => 'massa',
                'spaghetti' => 'espaguete',
                'fettuccine' => 'fettuccine',
                'lasagna' => 'lasanha',
                'ravioli' => 'ravioli',
                'tortellini' => 'tortellini',
                'gnocchi' => 'nhoque',
                'penne' => 'penne',
                'fusilli' => 'fusilli',
                'macaroni' => 'macarrão',
                
                // Queijos
                'provolone' => 'provolone',
                'gorgonzola' => 'gorgonzola',
                'ricotta' => 'ricota',
                'brie' => 'brie',
                'camembert' => 'camembert',
                'gouda' => 'gouda',
                'feta' => 'feta',
                'manchego' => 'manchego',
                
                // Outros
                'hummus' => 'homus',
                'tabbouleh' => 'tabule',
                'guacamole' => 'guacamole',
                'salsa' => 'salsa',
                'curry' => 'curry',
                'chutney' => 'chutney'
            ];

            return $excecoes[$termoIngles] ?? null;
        }

        // Função principal para chamar API de tradução real
        private function chamarApiTraducaoReal(string $texto, string $de, string $para): string {
            // Verifica se é uma exceção primeiro
            $excecao = $this->obterTraducaoExcecao($texto);
            if ($excecao) {
                return $excecao;
            }

            // Tenta LibreTranslate primeiro (gratuito)
            $traducao = $this->chamarLibreTranslate($texto, $de, $para);
            if ($traducao) {
                return $traducao;
            }

            // Fallback para MyMemory API
            $traducao = $this->chamarMyMemoryTranslate($texto, $de, $para);
            if ($traducao) {
                return $traducao;
            }

            // Último fallback: mapeamento local básico
            return $this->mapeamentoLocal($texto, $de, $para);
        }

        // LibreTranslate API
        private function chamarLibreTranslate(string $texto, string $de, string $para): ?string {
            $url = "https://libretranslate.de/translate";
            
            $dados = [
                'q' => $texto,
                'source' => $de,
                'target' => $para,
                'format' => 'text'
            ];

            $opcoes = [
                'http' => [
                    'header' => "Content-type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($dados),
                    'timeout' => 5
                ]
            ];

            $contexto = stream_context_create($opcoes);
            
            try {
                $resposta = @file_get_contents($url, false, $contexto);
                if ($resposta === false) {
                    return null;
                }

                $dadosResposta = json_decode($resposta, true);
                
                if (isset($dadosResposta['translatedText'])) {
                    return mb_strtolower(trim($dadosResposta['translatedText']));
                }
            } catch (Exception $e) {
                error_log("Erro LibreTranslate: " . $e->getMessage());
            }

            return null;
        }

        // MyMemory Translation API
        private function chamarMyMemoryTranslate(string $texto, string $de, string $para): ?string {
            $de = $de === 'pt' ? 'pt-BR' : $de;
            $para = $para === 'pt' ? 'pt-BR' : $para;
            
            $url = "https://api.mymemory.translated.net/get?q=" . 
                urlencode($texto) . "&langpair=" . $de . "|" . $para;
            
            try {
                $resposta = @file_get_contents($url, false, stream_context_create([
                    'http' => ['timeout' => 5]
                ]));
                
                if ($resposta === false) {
                    return null;
                }

                $dadosResposta = json_decode($resposta, true);
                
                if (isset($dadosResposta['responseData']['translatedText'])) {
                    $traducao = $dadosResposta['responseData']['translatedText'];
                    return mb_strtolower(trim($traducao));
                }
            } catch (Exception $e) {
                error_log("Erro MyMemory Translate: " . $e->getMessage());
            }

            return null;
        }

        // Mapeamento local básico
        private function mapeamentoLocal(string $texto, string $de, string $para): string {
            $mapaBasico = [
                // EN -> PT
                'en-pt' => [
                    'leaves' => 'folhas',
                    'sheets' => 'folhas',
                    'pastry' => 'massa',
                    'dough' => 'massa',
                    'noodles' => 'massa',
                    'crackers' => 'biscoitos',
                    'gelatin' => 'gelatina',
                    'bean' => 'feijão',
                    'curd' => 'coalhada',
                    'fresh' => 'fresco',
                    'cooked' => 'cozido',
                    'ready' => 'pronto',
                    'oven' => 'forno',
                    'cream' => 'creme',
                    'cheese' => 'queijo',
                    'sauce' => 'molho',
                    'powder' => 'pó',
                    'extract' => 'extrato',
                    'oil' => 'óleo',
                    'butter' => 'manteiga',
                    'milk' => 'leite',
                    'water' => 'água',
                    'juice' => 'suco',
                    'tea' => 'chá'
                ],
                
                // PT -> EN  
                'pt-en' => [
                    'folhas' => 'leaves',
                    'massa' => 'dough',
                    'biscoitos' => 'crackers',
                    'gelatina' => 'gelatin',
                    'queijo' => 'cheese',
                    'molho' => 'sauce',
                    'óleo' => 'oil',
                    'manteiga' => 'butter',
                    'leite' => 'milk'
                ]
            ];

            $chave = $de . '-' . $para;
            
            if (isset($mapaBasico[$chave][$texto])) {
                return $mapaBasico[$chave][$texto];
            }

            return $texto;
        }

        // Busca alimentos traduzidos com tradução contextual
        public function buscarAlimentosTraduzidos(string $termoPortugues) {
            if (empty($termoPortugues)) {
                throw new Exception('Termo de busca não informado');
            }

            // Traduz para inglês usando a API real
            $termoIngles = $this->traduzirParaIngles($termoPortugues);

            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            $url = "https://api.spoonacular.com/food/ingredients/search?query=" . 
                urlencode($termoIngles) . "&number=10&apiKey=$apiKey";

            $response = @file_get_contents($url);
            if ($response === false) {
                throw new Exception('Erro ao buscar na API Spoonacular. Verifique sua conexão e chave da API.');
            }

            $data = json_decode($response, true);
            if (!$data || !isset($data['results'])) {
                throw new Exception('Resposta inválida da API Spoonacular');
            }

            $resultados = [];
            foreach ($data['results'] as $item) {
                $nomeIngles = $item['name'];
                
                // Traduz o nome para português usando tradução contextual inteligente
                $nomePortugues = $this->traduzirParaPortugues(mb_strtolower($nomeIngles));

                $resultados[] = [
                    'id' => $item['id'],
                    'nome' => $nomePortugues,
                    'nome_original' => $item['name'],
                    'imagem' => isset($item['image']) ? 
                        "https://spoonacular.com/cdn/ingredients_100x100/" . $item['image'] : null
                ];
            }

            return $resultados;
        }

        // Busca informações detalhadas com tradução de nutrientes
        public function buscarInformacaoAlimento(int $idAlimento, float $quantidade = 100, string $unidade = 'g') {
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            $url = "https://api.spoonacular.com/food/ingredients/$idAlimento/information?" .
                "amount=$quantidade&unit=$unidade&apiKey=$apiKey";

            $response = @file_get_contents($url);
            if ($response === false) {
                throw new Exception('Erro ao buscar informações do alimento na API Spoonacular');
            }

            $data = json_decode($response, true);
            if (!$data) {
                throw new Exception('Resposta inválida da API Spoonacular para informações do alimento');
            }

            // Traduz o nome do alimento
            $nomePortugues = $this->traduzirParaPortugues(mb_strtolower($data['name']));

            // Processa e traduz nutrientes
            $nutrientesTraduzidos = [];
            if (isset($data['nutrition']['nutrients'])) {
                foreach ($data['nutrition']['nutrients'] as $nutriente) {
                    $nomeNutrienteIngles = mb_strtolower($nutriente['name']);
                    $nomeNutrientePortugues = $this->traduzirParaPortugues($nomeNutrienteIngles);
                    
                    $nutrientesTraduzidos[] = [
                        'nome' => $nomeNutrientePortugues,
                        'nome_original' => $nutriente['name'],
                        'quantidade' => $nutriente['amount'],
                        'unidade' => $nutriente['unit'],
                        'percentual_diario' => $nutriente['percentOfDailyNeeds'] ?? null
                    ];
                }
            }

            return [
                'id' => $idAlimento,
                'nome' => $nomePortugues,
                'nome_original' => $data['name'],
                'categoria' => $data['category'] ?? null,
                'imagem' => isset($data['image']) ? 
                    "https://spoonacular.com/cdn/ingredients_250x250/" . $data['image'] : null,
                'nutrientes' => $nutrientesTraduzidos,
                'quantidade_consulta' => $quantidade,
                'unidade_consulta' => $unidade
            ];
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

        private function buscarNutrientesAPI($nome) {
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '22d63ed8891245009cfa9acb18ec29ac';
            
            // Traduz o termo de pesquisa para inglês
            $nomeIngles = $this->traduzirParaIngles($nome);
            
            $searchUrl = "https://api.spoonacular.com/food/ingredients/search?query=".
                        urlencode($nomeIngles)."&number=1&apiKey=$apiKey";
            $searchData = json_decode(@file_get_contents($searchUrl), true);

            $nutrientes = ['calorias'=>0,'proteinas'=>0,'carboidratos'=>0,'gorduras'=>0,'unidade'=>'g'];

            if ($searchData && isset($searchData['results'][0]['id'])) {
                $ingredientId = $searchData['results'][0]['id'];
                $nutriUrl = "https://api.spoonacular.com/food/ingredients/$ingredientId/information?amount=100&unit=gram&apiKey=$apiKey";
                $nutriData = json_decode(@file_get_contents($nutriUrl), true);

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

            return $nutrientes;
        }
    }
    
?>