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

        // Traduz termo EN -> PT com cache - MELHORADA para termos compostos
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

            // Tenta traduzir termos compostos específicos de alimentos
            $traducaoComposta = $this->traduzirTermoComposto($termoIngles);
            if ($traducaoComposta && $traducaoComposta !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducaoComposta);
                return $traducaoComposta;
            }

            // Se não for termo composto conhecido, chama API de tradução
            $traducao = $this->chamarApiTraducaoReal($termoIngles, 'en', 'pt');

            // Salva no cache
            if ($traducao && $traducao !== $termoIngles) {
                $this->repository->inserirTraducao($termoIngles, $traducao);
            }

            return $traducao ?: $termoIngles;
        }

        // NOVO MÉTODO: Traduz termos compostos específicos de alimentos
        private function traduzirTermoComposto(string $termoIngles): string {
            $termosCompostos = [
                // Frutas e vegetais
                'banana leaves' => 'folhas de bananeira',
                'banana chips' => 'chips de banana',
                'banana bread' => 'pão de banana',
                'banana pepper' => 'pimenta banana',
                'pink banana squash' => 'abóbora banana rosa',
                'banana blossoms' => 'flores de bananeira',
                'banana pepper rings' => 'anéis de pimenta banana',
                'banana liqueur' => 'licor de banana',
                'banana extract' => 'extrato de banana',
                'apple juice' => 'suco de maçã',
                'orange juice' => 'suco de laranja',
                'pineapple juice' => 'suco de abacaxi',
                'tomato sauce' => 'molho de tomate',
                'tomato paste' => 'extrato de tomate',
                'cherry tomatoes' => 'tomates cereja',
                'sweet corn' => 'milho doce',
                'green peas' => 'ervilhas',
                'red pepper' => 'pimentão vermelho',
                'green pepper' => 'pimentão verde',
                'yellow pepper' => 'pimentão amarelo',
                'hot pepper' => 'pimenta picante',
                
                // Carnes e proteínas
                'chicken breast' => 'peito de frango',
                'chicken thigh' => 'coxa de frango',
                'chicken wing' => 'asa de frango',
                'chicken leg' => 'sobrecoxa de frango',
                'pork chop' => 'costeleta de porco',
                'pork loin' => 'lombo de porco',
                'beef steak' => 'bife',
                'ground beef' => 'carne moída',
                'salmon fillet' => 'filé de salmão',
                'tuna fish' => 'atum',
                'turkey breast' => 'peito de peru',
                'bacon strips' => 'fatias de bacon',
                'sausage links' => 'linguiças',
                
                // Laticínios
                'cream cheese' => 'cream cheese',
                'cottage cheese' => 'queijo cottage',
                'parmesan cheese' => 'queijo parmesão',
                'mozzarella cheese' => 'queijo mussarela',
                'cheddar cheese' => 'queijo cheddar',
                'greek yogurt' => 'iogurte grego',
                'sour cream' => 'creme de leite azedo',
                'whipped cream' => 'creme chantilly',
                'ice cream' => 'sorvete',
                'condensed milk' => 'leite condensado',
                
                // Grãos e cereais
                'brown rice' => 'arroz integral',
                'white rice' => 'arroz branco',
                'wild rice' => 'arroz selvagem',
                'whole wheat' => 'trigo integral',
                'oatmeal' => 'aveia',
                'whole grain' => 'grão integral',
                'bread crumbs' => 'farinha de rosca',
                'pasta sauce' => 'molho para massa',
                
                // Nozes e sementes
                'peanut butter' => 'manteiga de amendoim',
                'almond milk' => 'leite de amêndoa',
                'coconut milk' => 'leite de coco',
                'sunflower seeds' => 'sementes de girassol',
                'chia seeds' => 'sementes de chia',
                'flax seeds' => 'sementes de linhaça',
                'pumpkin seeds' => 'sementes de abóbora',
                
                // Vegetais
                'sweet potato' => 'batata doce',
                'green beans' => 'vagem',
                'bell pepper' => 'pimentão',
                'cherry tomato' => 'tomate cereja',
                'red onion' => 'cebola roxa',
                'brussels sprouts' => 'couve de bruxelas',
                'cauliflower rice' => 'arroz de couve-flor',
                'spinach leaves' => 'folhas de espinafre',
                'kale chips' => 'chips de couve',
                
                // Outros alimentos compostos
                'olive oil' => 'azeite de oliva',
                'coconut oil' => 'óleo de coco',
                'vegetable oil' => 'óleo vegetal',
                'soy sauce' => 'molho de soja',
                'maple syrup' => 'xarope de bordo',
                'vanilla extract' => 'extrato de baunilha',
                'baking powder' => 'fermento em pó',
                'baking soda' => 'bicarbonato de sódio',
                'whole milk' => 'leite integral',
                'skim milk' => 'leite desnatado',
                'chocolate chips' => 'gotas de chocolate',
                'cocoa powder' => 'cacau em pó'
            ];

            return $termosCompostos[$termoIngles] ?? $termoIngles;
        }

        // Função principal para chamar API de tradução real
        private function chamarApiTraducaoReal(string $texto, string $de, string $para): string {
            // Para termos compostos, tenta quebrar e traduzir separadamente
            if (strpos($texto, ' ') !== false) {
                $traducaoComposta = $this->traduzirTermoCompostoQuebrado($texto, $de, $para);
                if ($traducaoComposta && $traducaoComposta !== $texto) {
                    return $traducaoComposta;
                }
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

        // NOVO MÉTODO: Traduz termos compostos quebrando em partes
        private function traduzirTermoCompostoQuebrado(string $texto, string $de, string $para): string {
            $partes = explode(' ', $texto);
            $traducoes = [];
            
            foreach ($partes as $parte) {
                if ($de === 'en' && $para === 'pt') {
                    $traducoes[] = $this->traduzirParaPortugues($parte);
                } else {
                    $traducoes[] = $this->traduzirParaIngles($parte);
                }
            }
            
            // Junta as traduções (em português usa "de" para ligar palavras)
            if ($para === 'pt') {
                return implode(' ', $traducoes);
            } else {
                return implode(' ', $traducoes);
            }
        }

        // LibreTranslate API (Gratuita e Open Source)
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
                    'timeout' => 10
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

        // MyMemory Translation API (Gratuita até 1000 requests/dia)
        private function chamarMyMemoryTranslate(string $texto, string $de, string $para): ?string {
            $de = $de === 'pt' ? 'pt-BR' : $de;
            $para = $para === 'pt' ? 'pt-BR' : $para;
            
            $url = "https://api.mymemory.translated.net/get?q=" . 
                urlencode($texto) . "&langpair=" . $de . "|" . $para;
            
            try {
                $resposta = @file_get_contents($url, false, stream_context_create([
                    'http' => ['timeout' => 10]
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

        // Mapeamento local como fallback final
        private function mapeamentoLocal(string $texto, string $de, string $para): string {
            $mapaCompleto = [
                // PT -> EN
                'pt-en' => [
                    'banana' => 'banana',
                    'maçã' => 'apple',
                    'maça' => 'apple',
                    'laranja' => 'orange',
                    'abacaxi' => 'pineapple',
                    'morango' => 'strawberry',
                    'uva' => 'grape',
                    'mamão' => 'papaya',
                    'manga' => 'mango',
                    'limão' => 'lemon',
                    'pera' => 'pear',
                    'arroz' => 'rice',
                    'feijão' => 'beans',
                    'feijao' => 'beans',
                    'macarrão' => 'pasta',
                    'macarrao' => 'pasta',
                    'pão' => 'bread',
                    'pao' => 'bread',
                    'carne' => 'meat',
                    'frango' => 'chicken',
                    'peixe' => 'fish',
                    'ovo' => 'egg',
                    'leite' => 'milk',
                    'queijo' => 'cheese',
                    'iogurte' => 'yogurt',
                    'aveia' => 'oats',
                    'amêndoa' => 'almond',
                    'amendoa' => 'almond',
                    'castanha' => 'nut',
                    'brócolis' => 'broccoli',
                    'brocolis' => 'broccoli',
                    'cenoura' => 'carrot',
                    'alface' => 'lettuce',
                    'tomate' => 'tomato',
                    'batata' => 'potato',
                    'cebola' => 'onion',
                    'alho' => 'garlic',
                    'açúcar' => 'sugar',
                    'acucar' => 'sugar',
                    'sal' => 'salt',
                    'óleo' => 'oil',
                    'oleo' => 'oil',
                    'manteiga' => 'butter',
                    'carboidratos' => 'carbohydrates',
                    'proteínas' => 'protein',
                    'proteinas' => 'protein',
                    'gorduras' => 'fat',
                    'calorias' => 'calories',
                    'fibras' => 'fiber',
                    'vitaminas' => 'vitamins',
                    'minerais' => 'minerals',
                    'sódio' => 'sodium',
                    'sodio' => 'sodium',
                    'colesterol' => 'cholesterol',
                    'água' => 'water',
                    'agua' => 'water',
                    'café' => 'coffee',
                    'cafe' => 'coffee',
                    'chá' => 'tea',
                    'cha' => 'tea',
                    'iogurte' => 'yogurt',
                    'aveia' => 'oats',
                    'mel' => 'honey',
                    'chocolate' => 'chocolate',
                    'bolo' => 'cake',
                    'pizza' => 'pizza',
                    'hambúrguer' => 'hamburger',
                    'hamburguer' => 'hamburger',
                    'sorvete' => 'ice cream',
                    'biscoito' => 'cookie',
                    'bolacha' => 'cookie',
                    'salgado' => 'savory',
                    'doce' => 'sweet',
                    'azedo' => 'sour',
                    'amargo' => 'bitter',
                    'salgado' => 'salty',
                    'folhas' => 'leaves',
                    'chips' => 'chips',
                    'pão' => 'bread',
                    'pimenta' => 'pepper',
                    'abóbora' => 'squash',
                    'flores' => 'blossoms',
                    'anéis' => 'rings',
                    'licor' => 'liqueur',
                    'extrato' => 'extract',
                    'suco' => 'juice',
                    'molho' => 'sauce',
                    'ervilhas' => 'peas',
                    'milho' => 'corn',
                    'costeleta' => 'chop',
                    'lombo' => 'loin',
                    'bife' => 'steak',
                    'linguiça' => 'sausage',
                    'bacon' => 'bacon',
                    'creme' => 'cream',
                    'leite' => 'milk',
                    'farinha' => 'flour',
                    'fermento' => 'yeast',
                    'grão' => 'grain',
                    'manteiga' => 'butter',
                    'sementes' => 'seeds',
                    'couve' => 'kale',
                    'espinafre' => 'spinach',
                    'azeite' => 'olive oil',
                    'xarope' => 'syrup',
                    'baunilha' => 'vanilla',
                    'cacau' => 'cocoa'
                ],
                
                // EN -> PT
                'en-pt' => [
                    'banana' => 'banana',
                    'apple' => 'maçã',
                    'orange' => 'laranja',
                    'pineapple' => 'abacaxi',
                    'strawberry' => 'morango',
                    'grape' => 'uva',
                    'papaya' => 'mamão',
                    'mango' => 'manga',
                    'lemon' => 'limão',
                    'pear' => 'pera',
                    'rice' => 'arroz',
                    'beans' => 'feijão',
                    'pasta' => 'macarrão',
                    'bread' => 'pão',
                    'meat' => 'carne',
                    'chicken' => 'frango',
                    'fish' => 'peixe',
                    'egg' => 'ovo',
                    'milk' => 'leite',
                    'cheese' => 'queijo',
                    'yogurt' => 'iogurte',
                    'oats' => 'aveia',
                    'almond' => 'amêndoa',
                    'nut' => 'castanha',
                    'broccoli' => 'brócolis',
                    'carrot' => 'cenoura',
                    'lettuce' => 'alface',
                    'tomato' => 'tomate',
                    'potato' => 'batata',
                    'onion' => 'cebola',
                    'garlic' => 'alho',
                    'sugar' => 'açúcar',
                    'salt' => 'sal',
                    'oil' => 'óleo',
                    'butter' => 'manteiga',
                    'carbohydrates' => 'carboidratos',
                    'protein' => 'proteínas',
                    'fat' => 'gorduras',
                    'calories' => 'calorias',
                    'fiber' => 'fibras',
                    'vitamins' => 'vitaminas',
                    'minerals' => 'minerais',
                    'sodium' => 'sódio',
                    'cholesterol' => 'colesterol',
                    'water' => 'água',
                    'coffee' => 'café',
                    'tea' => 'chá',
                    'honey' => 'mel',
                    'chocolate' => 'chocolate',
                    'cake' => 'bolo',
                    'pizza' => 'pizza',
                    'hamburger' => 'hambúrguer',
                    'ice cream' => 'sorvete',
                    'cookie' => 'biscoito',
                    'savory' => 'salgado',
                    'sweet' => 'doce',
                    'sour' => 'azedo',
                    'bitter' => 'amargo',
                    'salty' => 'salgado',
                    'leaves' => 'folhas',
                    'chips' => 'chips',
                    'bread' => 'pão',
                    'pepper' => 'pimenta',
                    'squash' => 'abóbora',
                    'blossoms' => 'flores',
                    'rings' => 'anéis',
                    'liqueur' => 'licor',
                    'extract' => 'extrato',
                    'juice' => 'suco',
                    'sauce' => 'molho',
                    'peas' => 'ervilhas',
                    'corn' => 'milho',
                    'chop' => 'costeleta',
                    'loin' => 'lombo',
                    'steak' => 'bife',
                    'sausage' => 'linguiça',
                    'bacon' => 'bacon',
                    'cream' => 'creme',
                    'milk' => 'leite',
                    'flour' => 'farinha',
                    'yeast' => 'fermento',
                    'grain' => 'grão',
                    'butter' => 'manteiga',
                    'seeds' => 'sementes',
                    'kale' => 'couve',
                    'spinach' => 'espinafre',
                    'olive oil' => 'azeite',
                    'syrup' => 'xarope',
                    'vanilla' => 'baunilha',
                    'cocoa' => 'cacau'
                ]
            ];

            $chave = $de . '-' . $para;
            
            if (isset($mapaCompleto[$chave][$texto])) {
                return $mapaCompleto[$chave][$texto];
            }

            // Se não encontrou no mapa, retorna o texto original
            return $texto;
        }

        // Busca alimentos traduzidos com tradução real
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
                $nomeIngles = mb_strtolower($item['name']);
                
                // Traduz o nome para português usando API real
                $nomePortugues = $this->traduzirParaPortugues($nomeIngles);

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