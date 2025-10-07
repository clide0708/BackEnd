<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Repositories/AlimentosRepository.php';
    require_once __DIR__ . '/../Services/AlimentosService.php';
    require_once __DIR__ . '/../Config/auth.middleware.php';

    class AlimentosController
    {
        private $service;
        private $idAluno;
        private $pdo;

        public function __construct()
        {
            // Autentica e pega idAluno do token
            autenticar();
            if (!isset($_SERVER['user']) || $_SERVER['user']['tipo'] !== 'aluno') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas alunos podem gerenciar alimentação.']);
                exit;
            }
            $this->idAluno = $_SERVER['user']['sub'];

            $this->pdo = DB::connectDB();
            $repo = new AlimentosRepository($this->pdo);
            $this->service = new AlimentosService($repo, $this->pdo);
        }

        // Criar refeição
        public function criarRefeicao()
        {
            header('Content-Type: application/json');
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $nomeTipo = $data['nome_tipo'] ?? '';
                $dataRef = $data['data_ref'] ?? date('Y-m-d H:i:s');
                // $dataRef = date('Y-m-d H:i:s');

                // DEBUG
                error_log("=== DEBUG CRIAR REFEICAO ===");
                error_log("ID Aluno: " . $this->idAluno);
                error_log("Nome Tipo: " . $nomeTipo);
                error_log("Data Recebida: " . $dataRef);
                error_log("Data Servidor: " . date('Y-m-d H:i:s'));
                error_log("========================");

                if (empty($nomeTipo)) {
                    throw new Exception('Nome do tipo de refeição é obrigatório (ex: Café da manhã)');
                }

                $idRefeicao = $this->service->criarRefeicao($this->idAluno, $nomeTipo, $dataRef);

                // DEBUG
                error_log("Refeição criada com ID: " . $idRefeicao);

                echo json_encode([
                    'success' => true, 
                    'id_refeicao' => $idRefeicao, 
                    'message' => 'Refeição criada com sucesso',
                    'debug' => [
                        'data_recebida' => $dataRef,
                        'data_servidor' => date('Y-m-d H:i:s')
                    ]
                ]);
            } catch (Exception $e) {
                error_log("ERRO ao criar refeição: " . $e->getMessage());
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        //Remover refeição
        public function removerRefeicao()
        {
            header('Content-Type: application/json');
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $idRefeicao = $data['id_refeicao'] ?? 0;

                if (!$idRefeicao) {
                    throw new Exception('ID da refeição é obrigatório');
                }

                $this->service->deleteRefeicao($idRefeicao);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Refeição removida com sucesso',
                    'id_removido' => $idRefeicao
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Listar refeições diretamente
        public function listarRefeicoesSimples() {
            header('Content-Type: application/json');
            try {
                $dataRef = $_GET['data_ref'] ?? null;
                
                // Conexão direta com o banco para diagnóstico
                $pdo = DB::connectDB();
                
                $sql = "SELECT id, nome_tipo, data_ref FROM refeicoes_tipos WHERE idAluno = ?";
                $params = [$this->idAluno];
                
                if ($dataRef) {
                    $sql .= " AND DATE(data_ref) = ?";
                    $params[] = $dataRef;
                }
                
                $sql .= " ORDER BY data_ref DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $refeicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true, 
                    'refeicoes' => $refeicoes,
                    'debug' => [
                        'id_aluno' => $this->idAluno,
                        'total_refeicoes' => count($refeicoes)
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Listar refeições do aluno
        public function listarRefeicoes() {
            header('Content-Type: application/json');
            try {
                $dataRef = $_GET['data_ref'] ?? null;
                
                // DEBUG: Verificar se o idAluno está correto
                error_log("ID Aluno no Controller: " . $this->idAluno);
                
                // Busca as refeições básicas diretamente do repositório se necessário
                $refeicoes = $this->service->listarRefeicoesCompletas($this->idAluno, $dataRef);
                
                // DEBUG: Verificar o resultado
                error_log("Refeições encontradas: " . count($refeicoes));
                
                echo json_encode(['success' => true, 'refeicoes' => $refeicoes]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarRefeicoesHoje() {
            header('Content-Type: application/json');
            try {
                $dataRef = date('Y-m-d'); // Apenas data de hoje
                
                // DEBUG
                error_log("=== LISTAR REFEICOES HOJE ===");
                error_log("ID Aluno: " . $this->idAluno);
                error_log("Data Ref: " . $dataRef);

                // Busca refeições do dia atual
                $refeicoes = $this->service->listarRefeicoesCompletas($this->idAluno, $dataRef);
                
                // DEBUG
                error_log("Refeições encontradas: " . count($refeicoes));
                foreach ($refeicoes as $ref) {
                    error_log(" - " . $ref['nome_tipo'] . " (ID: " . $ref['id'] . ")");
                }
                error_log("============================");
                
                echo json_encode([
                    'success' => true, 
                    'refeicoes' => $refeicoes,
                    'data_ref' => $dataRef,
                    'total_refeicoes' => count($refeicoes),
                    'debug' => [
                        'id_aluno' => $this->idAluno,
                        'data_consulta' => $dataRef
                    ]
                ]);
            } catch (Exception $e) {
                error_log("ERRO em listarRefeicoesHoje: " . $e->getMessage());
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // private function calcularTotaisRefeicao(array $alimentos): array {
        //     $totais = ['calorias' => 0, 'proteinas' => 0, 'carboidratos' => 0, 'gorduras' => 0];
            
        //     foreach ($alimentos as $alimento) {
        //         $totais['calorias'] += (float)($alimento['calorias'] ?? 0);
        //         $totais['proteinas'] += (float)($alimento['proteinas'] ?? 0);
        //         $totais['carboidratos'] += (float)($alimento['carboidratos'] ?? 0);
        //         $totais['gorduras'] += (float)($alimento['gorduras'] ?? 0);
        //     }
            
        //     return [
        //         'calorias' => round($totais['calorias'], 2),
        //         'proteinas' => round($totais['proteinas'], 2),
        //         'carboidratos' => round($totais['carboidratos'], 2),
        //         'gorduras' => round($totais['gorduras'], 2)
        //     ];
        // }

        //Buscar alimentos com tradução
        public function buscarAlimentos()
        {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $termo = $_GET['nome'] ?? '';
                
                // // DECODIFICA o termo para tratar caracteres especiais
                // $termo = urldecode($termo);
                // $termo = trim($termo);
                
                if (!$termo) {
                    throw new Exception('Termo de busca não informado');
                }
                
                error_log("🔍 Buscando alimentos para: " . $termo);
                
                $resultados = $this->service->buscarAlimentosTraduzidos($termo);
                
                // Garante que a resposta está em UTF-8
                echo json_encode([
                    'success' => true, 
                    'resultados' => $resultados
                ], JSON_UNESCAPED_UNICODE);
                
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
            }
        }

        // public function buscarAlimentos()
        // {
        //     header('Content-Type: application/json');
        //     try {
        //         $termo = $_GET['nome'] ?? '';
        //         if (!$termo) {
        //             throw new Exception('Termo de busca não informado');
        //         }
        //         $resultados = $this->service->buscarAlimentosTraduzidos($termo);
        //         echo json_encode(['success' => true, 'resultados' => $resultados]);
        //     } catch (Exception $e) {
        //         http_response_code(400);
        //         echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        //     }
        // }

        // Informações detalhadas de alimento
        public function buscarInformacaoAlimento()
        {
            header('Content-Type: application/json');
            try {
                $id = $_GET['id'] ?? 0;
                $quantidade = $_GET['quantidade'] ?? 100;
                $unidade = $_GET['unidade'] ?? 'g';
                
                if (!$id) {
                    throw new Exception('ID do alimento não informado ou inválido');
                }

                $informacoes = $this->service->buscarInformacaoAlimento($id, $quantidade, $unidade);
                echo json_encode(['success' => true, 'alimento' => $informacoes]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Testar tradução
        public function testarTraducao()
        {
            header('Content-Type: application/json');
            try {
                $texto = $_GET['texto'] ?? '';
                $de = $_GET['de'] ?? 'pt';
                $para = $_GET['para'] ?? 'en';
                
                if (!$texto) {
                    throw new Exception('Texto para traduzir não informado');
                }

                if ($de === 'pt' && $para === 'en') {
                    $traducao = $this->service->traduzirParaIngles($texto);
                } elseif ($de === 'en' && $para === 'pt') {
                    $traducao = $this->service->traduzirParaPortugues($texto);
                } else {
                    throw new Exception('Combinação de idiomas não suportada');
                }

                echo json_encode([
                    'success' => true,
                    'original' => $texto,
                    'traducao' => $traducao,
                    'idiomas' => "{$de} → {$para}"
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Listar alimentos de uma refeição específica
        public function listarAlimentosRefeicao()
        {
            header('Content-Type: application/json');
            try {
                $idRefeicao = $_GET['id_refeicao'] ?? 0;
                
                if (!$idRefeicao) {
                    throw new Exception('ID da refeição é obrigatório');
                }

                $alimentos = $this->service->listarAlimentosRefeicao($idRefeicao, $this->idAluno);
                
                echo json_encode([
                    'success' => true, 
                    'id_refeicao' => $idRefeicao,
                    'alimentos' => $alimentos,
                    'total_itens' => count($alimentos)
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // // Listar alimentos de uma refeição - CORREÇÃO
        // public function listarAlimentos()
        // {
        //     header('Content-Type: application/json');
        //     header('Cache-Control: no-cache, no-store, must-revalidate');
        //     header('Pragma: no-cache');
        //     header('Expires: 0');

        //     try {
        //         $lista = $_GET['lista'] ?? '';
        //         if (!$lista) {
        //             throw new Exception('Tipo de refeição não informado');
        //         }
                
        //         // CORREÇÃO: Use o método correto que existe no Service
        //         $alimentos = $this->service->listarAlimentos($lista, $this->idAluno);
                
        //         echo json_encode(['success' => true, 'alimentos' => $alimentos]);
        //     } catch (Exception $e) {
        //         http_response_code(400);
        //         echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        //     }
        // }

        // Adicionar alimento
        public function addAlimento()
        {
            header('Content-Type: application/json');
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $idTipoRefeicao = $data['id_tipo_refeicao'] ?? 0;
                $nome = $data['nome'] ?? '';
                $quantidade = $data['quantidade'] ?? 0;
                $medida = $data['medida'] ?? 'g';

                if (!$idTipoRefeicao || !$nome || !$quantidade) {
                    throw new Exception('id_tipo_refeicao, nome e quantidade são obrigatórios');
                }

                $id = $this->service->addAlimento($idTipoRefeicao, $nome, $quantidade, $medida);
                echo json_encode(['success' => true, 'id' => $id]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Remover alimento
        public function rmvAlimento()
        {
            header('Content-Type: application/json');
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? 0;

                if (!$id) {
                    throw new Exception('ID do alimento não informado');
                }

                $this->service->deleteAlimento($id);
                echo json_encode(['success' => true, 'id_removido' => $id]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Atualizar alimento
        public function updAlimento()
        {
            header('Content-Type: application/json');
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? 0;
                $quantidadeNova = $data['quantidade'] ?? 0;
                $medidaNova = $data['medida'] ?? 'g';

                if (!$id || !$quantidadeNova) {
                    throw new Exception('ID e nova quantidade são obrigatórios');
                }

                $this->service->updAlimento($id, $quantidadeNova, $medidaNova);
                echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso']);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Listar totais
        public function listarTotais()
        {
            header('Content-Type: application/json');
            try {
                $totais = $this->service->listarTotais($this->idAluno);
                echo json_encode(['success' => true, 'refeicoes' => $totais['refeicoes'], 'totaisGerais' => $totais['totaisGerais']]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Método temporário para diagnóstico - adicione no AlimentosController
        public function diagnosticarRefeicoes() {
            header('Content-Type: application/json');
            try {
                $dataRef = $_GET['data_ref'] ?? null;
                
                // Teste 1: Buscar refeições básicas
                $refeicoesBasicas = $this->service->listarRefeicoesAluno($this->idAluno, $dataRef);
                
                // Teste 2: Buscar uma refeição específica se existir
                $refeicaoDetalhada = [];
                if (!empty($refeicoesBasicas)) {
                    $primeiraRefeicao = $refeicoesBasicas[0];
                    $refeicaoDetalhada = $this->service->listarAlimentosRefeicao($primeiraRefeicao['id'], $this->idAluno);
                }
                
                echo json_encode([
                    'success' => true,
                    'diagnostico' => [
                        'id_aluno' => $this->idAluno,
                        'refeicoes_basicas' => $refeicoesBasicas,
                        'total_refeicoes' => count($refeicoesBasicas),
                        'primeira_refeicao_detalhada' => $refeicaoDetalhada,
                        'data_ref' => $dataRef
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } 

        public function diagnosticarAlimentos() {
            header('Content-Type: application/json');
            try {
                $idRefeicao = $_GET['id_refeicao'] ?? 1; // Teste com a refeição ID 1
                
                // Diagnóstico passo a passo
                $diagnostico = [
                    'id_refeicao_testada' => $idRefeicao,
                    'passo_1_direct_query' => $this->queryDiretaItensRefeicao($idRefeicao),
                    'passo_2_repository_method' => $this->service->listarAlimentosRefeicao($idRefeicao, $this->idAluno),
                    'passo_3_sql_count' => $this->countItensRefeicao($idRefeicao)
                ];
                
                echo json_encode(['success' => true, 'diagnostico_alimentos' => $diagnostico]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function diagnosticarBusca() {
            header('Content-Type: application/json');
            try {
                $termo = $_GET['nome'] ?? 'maçã';
                
                // Diagnóstico passo a passo
                $diagnostico = [
                    'termo_original' => $termo,
                    'traducao_ingles' => $this->service->traduzirParaIngles($termo),
                    'api_key' => $_ENV['SPOONACULAR_API_KEY'] ?? 'Não encontrada',
                    'teste_api_direto' => $this->testarAPIDiretamente($termo)
                ];
                
                echo json_encode(['success' => true, 'diagnostico' => $diagnostico]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        private function testarAPIDiretamente(string $termo): array {
            $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? '1b595354b7fa490e84c1d3942f6b04c5';
            $url = "https://api.spoonacular.com/food/ingredients/search?query=" . urlencode($termo) . "&number=5&apiKey=$apiKey";
            
            $context = stream_context_create([
                'http' => ['timeout' => 15],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            $data = $response ? json_decode($response, true) : null;
            
            return [
                'url_chamada' => $url,
                'resposta_bruta' => $response ? substr($response, 0, 500) : 'NULL',
                'dados_decodificados' => $data,
                'http_status' => $http_response_header[0] ?? 'Desconhecido'
            ];
        }

    }

?>