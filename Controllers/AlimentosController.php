<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Repositories/AlimentosRepository.php';
    require_once __DIR__ . '/../Services/AlimentosService.php';

    class AlimentosController
    {
        private $service;

        public function __construct()
        {
            // Conecta ao banco de dados
            $this->pdo = DB::connectDB();
            
            // Instancia o repositório passando a conexão PDO
            $repo = new AlimentosRepository($this->pdo);
            
            // Instancia o service passando o repositório e a conexão PDO
            $this->service = new AlimentosService($repo, $this->pdo);
        }

        // Endpoint para buscar alimentos com tradução
        public function buscarAlimentos() {
            header('Content-Type: application/json');
            try {
                $termo = filter_input(INPUT_GET, 'nome', FILTER_SANITIZE_STRING);
                if (!$termo) {
                    throw new Exception('Termo de busca não informado');
                }
                $resultados = $this->service->buscarAlimentosTraduzidos($termo);
                echo json_encode(['success' => true, 'resultados' => $resultados]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Endpoint para buscar informações detalhadas de um alimento específico
        public function buscarInformacaoAlimento() {
            header('Content-Type: application/json');
            try {
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                $quantidade = filter_input(INPUT_GET, 'quantidade', FILTER_VALIDATE_FLOAT) ?? 100;
                $unidade = filter_input(INPUT_GET, 'unidade', FILTER_SANITIZE_STRING) ?? 'g';
                
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

        // Endpoint para testar tradução
        public function testarTraducao() {
            header('Content-Type: application/json');
            try {
                $texto = filter_input(INPUT_GET, 'texto', FILTER_SANITIZE_STRING);
                $de = filter_input(INPUT_GET, 'de', FILTER_SANITIZE_STRING) ?? 'pt';
                $para = filter_input(INPUT_GET, 'para', FILTER_SANITIZE_STRING) ?? 'en';
                
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

        public function listarAlimentos()
        {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            try {
                $lista = filter_input(INPUT_GET, 'lista', FILTER_SANITIZE_STRING); // pega e limpa a entrada
                $alimentos = $this->service->listarAlimentos($lista);
                echo json_encode($alimentos);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
        }

        public function addAlimento()
        {
            header('Content-Type: application/json');
            try {
                $lista = $_POST['lista'] ?? '';
                $nome = $_POST['nome'] ?? '';
                $quantidade = $_POST['quantidade'] ?? '';
                $id = $this->service->addAlimento($lista, $nome, $quantidade);
                echo json_encode(['success' => true, 'id' => $id]);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
        }

        public function rmvAlimento()
        {
            header('Content-Type: application/json');
            try {
                $lista = $_POST['lista'] ?? '';
                $index = $_POST['index'] ?? '';
                if ($lista === '' || $index === '') throw new Exception('Dados incompletos');

                $this->service->rmvAlimento($lista, $index);
                echo json_encode(['success' => true, 'id_removido' => $index]);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
        }

        public function updAlimento()
        {
            header('Content-Type: application/json');
            try {
                $lista = $_POST['lista'] ?? '';
                $index = $_POST['index'] ?? '';
                $quantidade_nova = $_POST['quantidade'] ?? '';
                if ($lista === '' || $index === '' || $quantidade_nova === '') throw new Exception('Dados incompletos');

                $this->service->updAlimento($lista, $index, $quantidade_nova);
                echo json_encode(['success' => true, 'message' => 'Especificação e nutrientes atualizados com sucesso']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function listarTotais()
        {
            header('Content-Type: application/json');
            try {
                $totais = $this->service->listarTotais();
                echo json_encode(['success' => true, 'refeicoes' => $totais['refeicoes'], 'totaisGerais' => $totais['totaisGerais']]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
    }
?>