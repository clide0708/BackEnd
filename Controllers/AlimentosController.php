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

                if (empty($nomeTipo)) {
                    throw new Exception('Nome do tipo de refeição é obrigatório (ex: Café da manhã)');
                }

                $idRefeicao = $this->service->criarRefeicao($this->idAluno, $nomeTipo, $dataRef);
                echo json_encode(['success' => true, 'id_refeicao' => $idRefeicao, 'message' => 'Refeição criada com sucesso']);
            } catch (Exception $e) {
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

        // Listar refeições do aluno
        public function listarRefeicoes()
        {
            header('Content-Type: application/json');
            try {
                $dataRef = $_GET['data_ref'] ?? null;
                $refeicoes = $this->service->listarRefeicoesAluno($this->idAluno, $dataRef);
                echo json_encode(['success' => true, 'refeicoes' => $refeicoes]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        // Buscar alimentos com tradução
        public function buscarAlimentos()
        {
            header('Content-Type: application/json');
            try {
                $termo = $_GET['nome'] ?? '';
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

        // Listar alimentos de uma refeição - CORREÇÃO
        public function listarAlimentos()
        {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            try {
                $lista = $_GET['lista'] ?? '';
                if (!$lista) {
                    throw new Exception('Tipo de refeição não informado');
                }
                
                // CORREÇÃO: Use o método correto que existe no Service
                $alimentos = $this->service->listarAlimentos($lista, $this->idAluno);
                
                echo json_encode(['success' => true, 'alimentos' => $alimentos]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

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
    }

?>