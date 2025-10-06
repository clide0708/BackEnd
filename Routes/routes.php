<?php

    // Incluir jwt.config.php uma vez
    require_once __DIR__ . '/../Config/jwt.config.php';

    // Define as rotas do sistema
    $routes = [

        // =============================
        // ROTAS PARA CADASTRO
        // =============================
        'cadastro/aluno' => [
            'controller' => 'CadastroController',
            'method' => 'cadastrarAluno'
        ],
        'cadastro/personal' => [
            'controller' => 'CadastroController',
            'method' => 'cadastrarPersonal'
        ],
        'cadastro/academia' => [
            'controller' => 'CadastroController',
            'method' => 'cadastrarAcademia'
        ],
        'cadastro/dev' => [
            'controller' => 'CadastroController',
            'method' => 'cadastrarDev'
        ],
        'cadastro/verificar-email' => [
            'controller' => 'CadastroController',
            'method' => 'verificarEmail'
        ],
        'cadastro/verificar-cpf' => [
            'controller' => 'CadastroController',
            'method' => 'verificarCpf'
        ],
        'cadastro/verificar-rg' => [
            'controller' => 'CadastroController',
            'method' => 'verificarRg'
        ],
        'cadastro/verificar-cnpj' => [
            'controller' => 'CadastroController',
            'method' => 'verificarCnpj'
        ],

        // =============================
        // ROTAS PARA AUTENTICAÇÃO
        // =============================
        'auth/login' => [
            'controller' => 'AuthController',
            'method' => 'login'
        ],
        'auth/logout' => [
            'controller' => 'AuthController',
            'method' => 'logout'
        ],
        'auth/verificar-token' => [
            'controller' => 'AuthController',
            'method' => 'verificarToken'
        ],
        'auth/obter-usuario' => [
            'controller' => 'AuthController',
            'method' => 'obterUsuarioToken'
        ],
        'auth/verificar-autenticacao' => [
            'controller' => 'AuthController',
            'method' => 'verificarAutenticacao'
        ],

        // =============================
        // ROTAS PARA EXERCÍCIOS
        // =============================
        'exercicios/buscarTodos' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarTodosExercicios'
        ],
        'exercicios/buscarPorID' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarPorID'
        ],
        'exercicios/buscarPorID/(\d+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarPorID'
        ],
        'exercicios/buscarPorNome' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarPorNome'
        ],
        'exercicios/buscarPorNome/([a-zA-Z0-9%C3%A1%C3%A0%C3%A3%C3%A2%C3%A9%C3%A8%C3%AA%C3%AD%C3%AC%C3%B3%C3%B2%C3%B4%C3%BA%C3%FA%C3%BC%C3%A7\s]+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarPorNome'
        ],
        'exercicios/cadastrar' => [
            'controller' => 'ExerciciosController',
            'method' => 'cadastrarExercicio'
        ],
        'exercicios/atualizar' => [
            'controller' => 'ExerciciosController',
            'method' => 'atualizarExercicio'
        ],
        'exercicios/atualizar/(\d+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'atualizarExercicio'
        ],
        'exercicios/deletar' => [
            'controller' => 'ExerciciosController',
            'method' => 'deletarExercicio'
        ],
        'exercicios/deletar/(\d+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'deletarExercicio'
        ],
        'exercicios/listarGruposMusculares' => [
            'controller' => 'ExerciciosController',
            'method' => 'listarGruposMusculares'
        ],
        'exercicios/exercicioComVideos/(\w+)/(\d+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExercicioComVideos'
        ],

        // =============================
        // ROTAS PARA TREINOS
        // =============================
        'treinos/criar' => [
            'controller' => 'TreinosController',
            'method' => 'criarTreino'
        ],
        'treinos/atualizar/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'atualizarTreino'
        ],
        'treinos/(\d+)/adicionar-exercicio' => [
            'controller' => 'TreinosController',
            'method' => 'adicionarExercicioAoTreino'
        ],
        'treinos/aluno/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosAluno'
        ],
        'treinos/personal/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosPersonal'
        ],
        'treinos/aluno' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosAluno'
        ],
        'treinos/aluno/personal/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosAlunoComPersonal'
        ],
        'treinos/(\d+)/exercicios' => [
            'controller' => 'TreinosController',
            'method' => 'listarExerciciosDoTreino'
        ],
        'treinos/exercicio/(\d+)/atualizar' => [
            'controller' => 'TreinosController',
            'method' => 'atualizarExercicioNoTreino'
        ],
        'treinos/exercicio/(\d+)/remover' => [
            'controller' => 'TreinosController',
            'method' => 'removerExercicioDoTreino'
        ],
        'treinos/atribuir' => [
            'controller' => 'TreinosController',
            'method' => 'atribuirTreinoAluno'
        ],
        'treinos/excluir/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'excluirTreino'
        ],
        'treinos/personal/(\d+)/alunos' => [
            'controller' => 'TreinosController',
            'method' => 'listarAlunosDoPersonal'
        ],
        'treinos/personal/(\d+)/aluno/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosDoAlunoAtribuidos'
        ],
        'treinos/atribuido/(\d+)/atualizar' => [
            'controller' => 'TreinosController',
            'method' => 'atualizarTreinoAtribuido'
        ],
        'treinos/atribuido/(\d+)/desatribuir' => [
            'controller' => 'TreinosController',
            'method' => 'desatribuirTreinoDoAluno'
        ],
        'treinos/personal/(\d+)/desvincular-aluno/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'desvincularAluno'
        ],
        'treinos/personal/(\d+)/meus-treinos' => [
            'controller' => 'TreinosController',
            'method' => 'listarMeusTreinosPersonal'
        ],
        'treinos/buscarExercicios' => [
            'controller' => 'TreinosController',
            'method' => 'buscarExercicios'
        ],
        'treinos/listarUsuario' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosUsuario'
        ],
        'treinos/buscarCompleto/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'buscarTreinoCompleto'
        ],

        // =============================
        // ROTAS PARA ALIMENTOS
        // =============================
        'alimentos/criar-refeicao' => [
            'controller' => 'AlimentosController',
            'method' => 'criarRefeicao'
        ],
        'alimentos/remover-refeicao' => [
            'controller' => 'AlimentosController',
            'method' => 'removerRefeicao'
        ],
        'alimentos/listar-refeicoes' => [
            'controller' => 'AlimentosController',
            'method' => 'listarRefeicoes'
        ],
        'alimentos/listar-refeicoes-simples' => [
        'controller' => 'AlimentosController',
        'method' => 'listarRefeicoesSimples'
        ],
        'alimentos/buscar' => [
            'controller' => 'AlimentosController',
            'method' => 'buscarAlimentos'
        ],
        'alimentos/informacao' => [
            'controller' => 'AlimentosController',
            'method' => 'buscarInformacaoAlimento'
        ],
        'alimentos/testar-traducao' => [
            'controller' => 'AlimentosController',
            'method' => 'testarTraducao'
        ],
        'alimentos/listar' => [
            'controller' => 'AlimentosController',
            'method' => 'listarAlimentos'
        ],
        'alimentos/adicionar' => [
            'controller' => 'AlimentosController',
            'method' => 'addAlimento'
        ],
        'alimentos/remover' => [
            'controller' => 'AlimentosController',
            'method' => 'rmvAlimento'
        ],
        'alimentos/atualizar' => [
            'controller' => 'AlimentosController',
            'method' => 'updAlimento'
        ],
        'alimentos/totais' => [
            'controller' => 'AlimentosController',
            'method' => 'listarTotais'
        ],
        'alimentos/refeicao/alimentos' => [
        'controller' => 'AlimentosController',
        'method' => 'listarAlimentosRefeicao'
        ],

        'alimentos/diagnosticar' => [
        'controller' => 'AlimentosController',
        'method' => 'diagnosticarRefeicoes'
        ],

        'alimentos/diagnosticar-alimentos' => [
        'controller' => 'AlimentosController',
        'method' => 'diagnosticarAlimentos'
        ],

        // =============================
        // ROTAS PARA CONFIGURAÇÃO
        // =============================
        'config/testarConexao' => [
            'controller' => 'ConfigController',
            'method' => 'testarConexaoDB'
        ],

        // =============================
        // ROTAS PARA CONVITES
        // =============================
        'convites/criar' => [
            'controller' => 'ConvitesController',
            'method' => 'criarConvite'
        ],
        // Rota para visualizar um convite específico por token (para links)
        'convites/([a-zA-Z0-9]{64})' => [
            'controller' => 'ConvitesController',
            'method' => 'getConvite'
        ],
        // Rota para listar todos convites de um aluno por email
        'convites/aluno/([^/]+)' => [
            'controller' => 'ConvitesController',
            'method' => 'getConvites'
        ],
        // Rotas para aceitar/negar por token (para links)
        'convites/([a-zA-Z0-9]{64})/aceitar' => [
            'controller' => 'ConvitesController',
            'method' => 'aceitarConviteToken'
        ],
        'convites/([a-zA-Z0-9]{64})/negar' => [
            'controller' => 'ConvitesController',
            'method' => 'negarConviteToken'
        ],
        // Rotas para aceitar/negar por idConvite (para interface)
        'convites/(\d+)/aceitar' => [
            'controller' => 'ConvitesController',
            'method' => 'aceitarConvite'
        ],
        'convites/(\d+)/negar' => [
            'controller' => 'ConvitesController',
            'method' => 'negarConvite'
        ],

        // =============================
        // ROTAS PARA RECUPERAÇÃO DE SENHA
        // =============================
        'recuperacao-senha/esqueci-senha' => [
            'controller' => 'RecuperacaoSenhaController',
            'method' => 'esqueciSenha'
        ],
        'recuperacao-senha/resetar-senha' => [
            'controller' => 'RecuperacaoSenhaController',
            'method' => 'resetarSenha'
        ],

        // =============================
        // ROTAS PARA PERFIL
        // =============================
        'perfil/aluno/(\d+)' => [
            'controller' => 'PerfilController',
            'method' => 'getPerfilAluno'
        ],
        'perfil/aluno' => [
            'controller' => 'PerfilController',
            'method' => 'postPerfilAluno'
        ],
        'perfil/aluno/(\d+)' => [
            'controller' => 'PerfilController',
            'method' => 'putPerfilAluno'
        ],
        'perfil/personal/(\d+)' => [
            'controller' => 'PerfilController',
            'method' => 'getPerfilPersonal'
        ],
        'perfil/personal' => [
            'controller' => 'PerfilController',
            'method' => 'postPerfilPersonal'
        ],
        'perfil/personal/(\d+)' => [
            'controller' => 'PerfilController',
            'method' => 'putPerfilPersonal'
        ],
        'perfil/academia/(\d+)' => [
            'controller' => 'PerfilController',
            'method' => 'getPerfilAcademia'
        ],
        'perfil/academia' => [
            'controller' => 'PerfilController',
            'method' => 'postPerfilAcademia'
        ],
        'perfil/academia/(\d+)' => [
            'controller' => 'PerfilController',
            'method' => 'putPerfilAcademia'
        ],
        'perfil/dev/(\d+)' => [
            'controller' => 'PerfilController',
            'method' => 'getPerfilDev'
        ],
        'perfil/dev' => [
            'controller' => 'PerfilController',
            'method' => 'putPerfilDev'
        ],
        'perfil/personal/(\d+)/alunos' => [
            'controller' => 'PerfilController',
            'method' => 'getAlunosDoPersonal'
        ],
        'perfil/personal/(\d+)/treinos-criados' => [
            'controller' => 'PerfilController',
            'method' => 'getTreinosCriadosPorPersonal'
        ],
        'perfil/plano' => [
            'controller' => 'PerfilController',
            'method' => 'getPlanoUsuario'
        ],
        'perfil/plano/trocar' => [
            'controller' => 'PerfilController',
            'method' => 'trocarPlano'
        ],
        'perfil/plano/cancelar' => [
            'controller' => 'PerfilController',
            'method' => 'cancelarPlano'
        ],
        'perfil/excluir-conta' => [
            'controller' => 'PerfilController',
            'method' => 'excluirConta'
        ],

        // =============================
        // ROTAS PARA PLANOS
        // =============================
        'planos' => [
            'controller' => 'PlanosController',
            'method' => 'getAllPlanos'
        ],
        'planos/(\d+)' => [
            'controller' => 'PlanosController',
            'method' => 'getPlanoById'
        ],
        'planos/criar' => [
            'controller' => 'PlanosController',
            'method' => 'createPlano'
        ],
        'planos/atualizar/(\d+)' => [
            'controller' => 'PlanosController',
            'method' => 'updatePlano'
        ],
        'planos/deletar/(\d+)' => [
            'controller' => 'PlanosController',
            'method' => 'deletePlano'
        ],

        // =============================
        // ROTAS PARA PAGAMENTOS
        // =============================
        'pagamentos/iniciar' => [
            'controller' => 'PagamentosController',
            'method' => 'iniciarPagamento'
        ],
        'pagamentos/confirmar' => [
            'controller' => 'PagamentosController',
            'method' => 'confirmarPagamento'
        ],
        'pagamentos/historico' => [
            'controller' => 'PagamentosController',
            'method' => 'getHistoricoPagamentos'
        ],
    ];

    // Mapeamento de controladores
    $controller_paths = [
        'CadastroController' => __DIR__ . '/../Controllers/CadastroController.php',
        'AuthController' => __DIR__ . '/../Controllers/AuthController.php',
        'ExerciciosController' => __DIR__ . '/../Controllers/ExerciciosController.php',
        'ConfigController' => __DIR__ . '/../Config/ConfigController.php',
        'AlimentosController' => __DIR__ . '/../Controllers/AlimentosController.php',
        'TreinosController' => __DIR__ . '/../Controllers/TreinosController.php',
        'ConvitesController' => __DIR__ . '/../Controllers/ConvitesController.php',
        'RecuperacaoSenhaController' => __DIR__ . '/../Controllers/RecuperacaoSenhaController.php',
        'PerfilController' => __DIR__ . '/../Controllers/PerfilController.php',
        'PlanosController' => __DIR__ . '/../Controllers/PlanosController.php',
        'PagamentosController' => __DIR__ . '/../Controllers/PagamentosController.php',
    ];

    // Função para despachar a requisição
    function dispatch($path, $routes, $controller_paths, $method_http)
    {
        // Remove 'api/' do início do path, se existir
        $path_segments = explode('/', $path);
        if ($path_segments[0] === 'api') {
            array_shift($path_segments);
        }

        $clean_path = implode('/', $path_segments);
        $matched_route = null;
        $params = [];

        require_once __DIR__ . '/../Config/auth.middleware.php';

        // Remove query string do path para matching de rotas
        $clean_path = parse_url($clean_path, PHP_URL_PATH);
        $clean_path = trim($clean_path, '/');

        // Rotas públicas que não precisam de autenticação
        $rotasPublicas = [
            'auth/login',
            'auth/verificar-token',
            'auth/logout',
            'auth/obter-usuario',
            'auth/verificar-autenticacao',

            'cadastro/aluno',
            'cadastro/personal',
            'cadastro/academia',
            'cadastro/dev',
            'cadastro/verificar-email',
            'cadastro/verificar-cpf',
            'cadastro/verificar-rg',
            'cadastro/verificar-cnpj',

            'config/testarConexao',
            
            'recuperacao-senha/esqueci-senha',
            'recuperacao-senha/resetar-senha',

            'alimentos/buscar',
            'alimentos/informacao',
            'alimentos/testar-traducao',
            'alimentos/listar',
            'alimentos/totais',
            'alimentos/criar-refeicao',
            'alimentos/remover-refeicao',
            'alimentos/listar-refeicoes-simples',
            'alimentos/listar-refeicoes',
            'alimentos/refeicao/alimentos',



            // 'alimentos/diagnosticar',

            'convites/([a-zA-Z0-9]{64})',
            'convites/aluno/([^/]+)',

            'perfil/aluno/(\d+)',
            'perfil/personal/(\d+)',
            'perfil/academia/(\d+)',
            'perfil/dev/(\d+)',
            'perfil/aluno',
            'perfil/personal',
            'perfil/academia',

            'planos',
            'planos/(\d+)',
        ];

        // Se a rota não for pública, exige autenticação
        $isPublicRoute = false;
        foreach ($rotasPublicas as $rotaPublica) {
            if (preg_match('#^' . $rotaPublica . '$#', $clean_path)) {
                $isPublicRoute = true;
                break;
            }
        }

        if (!$isPublicRoute) {
            autenticar();
        }

        // Procura por correspondência exata primeiro
        if (array_key_exists($clean_path, $routes)) {
            $matched_route = $routes[$clean_path];
        } else {
            // Procura por padrões com parâmetros
            foreach ($routes as $pattern => $route) {
                if (preg_match('#^' . $pattern . '$#', $clean_path, $matches)) {
                    $matched_route = $route;
                    array_shift($matches); // Remove a correspondência completa
                    $params = $matches; // Resto são os parâmetros
                    break;
                }
            }
        }

        if ($matched_route) {
            $controller_name = $matched_route['controller'];
            $method_name = $matched_route['method'];

            if (array_key_exists($controller_name, $controller_paths)) {
                $controller_file = $controller_paths[$controller_name];

                if (file_exists($controller_file)) {
                    require_once $controller_file;

                    // Instancia o controlador
                    $controller_instance = new $controller_name();

                    if (method_exists($controller_instance, $method_name)) {
                        // Captura parâmetros da query string
                        parse_str($_SERVER['QUERY_STRING'] ?? '', $query_params);

                        // Prepara os parâmetros para chamar o método
                        $method_params = [];

                        // Adiciona parâmetros da URL (se houver)
                        if (!empty($params)) {
                            $method_params = array_merge($method_params, $params);
                        }

                        // CORREÇÃO: Para métodos específicos que usam query parameters
                        // Se não há parâmetros na URL, tenta pegar da query string
                        if (empty($method_params)) {
                            switch ($method_name) {
                                case 'buscarPorNome':
                                    if (isset($query_params['nome'])) {
                                        $method_params[] = $query_params['nome'];
                                    }
                                    break;
                                case 'buscarPorID':
                                case 'deletarExercicio':
                                    if (isset($query_params['id'])) {
                                        $method_params[] = $query_params['id'];
                                    }
                                    break;
                                case 'verificarEmail':
                                    if (isset($query_params['email'])) {
                                        $method_params[] = ['email' => $query_params['email']];
                                    }
                                    break;
                                case 'verificarCpf':
                                    if (isset($query_params['cpf'])) {
                                        $method_params[] = ['cpf' => $query_params['cpf']];
                                    }
                                    break;
                                case 'verificarCnpj':
                                    if (isset($query_params['cnpj'])) {
                                        $method_params[] = ['cnpj' => $query_params['cnpj']];
                                    }
                                    break;
                                case 'verificarRg':
                                    if (isset($query_params['rg'])) {
                                        $method_params[] = ['rg' => $query_params['rg']];
                                    }
                                    break;
                            }
                        }

                        // Captura os dados do corpo da requisição para POST, PUT, PATCH
                        $data = [];
                        if (in_array($method_http, ['POST', 'PUT', 'PATCH'])) {
                            $content_type = $_SERVER['CONTENT_TYPE'] ?? '';

                            if (strpos($content_type, 'application/json') !== false) {
                                $input = file_get_contents('php://input');
                                $data = json_decode($input, true) ?? [];
                            } else {
                                $data = $_POST;
                            }

                            // Adiciona dados do corpo para POST/PUT/PATCH
                            if (!empty($data)) {
                                $method_params[] = $data;
                            }
                        }

                        // Verifica se os parâmetros necessários estão presentes
                        $reflection = new ReflectionMethod($controller_instance, $method_name);
                        $required_params = $reflection->getNumberOfRequiredParameters();

                        // DEBUG: Log para verificar os parâmetros (remover em produção)
                        error_log("Método: $method_name, Requeridos: $required_params, Fornecidos: " . count($method_params));
                        error_log("Parâmetros: " . print_r($method_params, true));

                        if (count($method_params) >= $required_params) {
                            // Chama o método do controlador com os parâmetros
                            call_user_func_array([$controller_instance, $method_name], $method_params);
                        } else {
                            // Para métodos sem parâmetros obrigatórios, chama sem parâmetros
                            if ($required_params === 0) {
                                call_user_func([$controller_instance, $method_name]);
                            } else {
                                http_response_code(400);
                                echo json_encode([
                                    "error" => "Parâmetros insuficientes para o método '$method_name'",
                                    "required" => $required_params,
                                    "provided" => count($method_params),
                                    "params_received" => $method_params,
                                    "query_params" => $query_params,
                                    "path" => $clean_path
                                ]);
                            }
                        }
                    } else {
                        http_response_code(404);
                        echo json_encode(["error" => "Método '$method_name' não encontrado no controlador '$controller_name'"]);
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "Arquivo do controlador '$controller_file' não encontrado"]);
                }
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Controlador '$controller_name' não mapeado"]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Rota '$clean_path' não encontrada"]);
        }
    }

?>