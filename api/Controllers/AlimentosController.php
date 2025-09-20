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