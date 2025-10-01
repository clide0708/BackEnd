<?php
// Incluir jwt.config.php uma vez
require_once __DIR__ . '/../Config/jwt.config.php';

// Define as rotas do sistema
$routes = [

    // Rotas para Cadastro
    'cadastro/aluno' => [
        'controller' => 'CadastroController',
        'method' => 'cadastrarAluno'
    ],
    'cadastro/personal' => [
        'controller' => 'CadastroController',
        'method' => 'cadastrarPersonal'
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


    // Rotas para Autenticação
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

    // Rotas para ExercíciosController
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

    // Rota para buscar exercício com vídeos
    'exercicios/exercicioComVideos/(\w+)/(\d+)' => [
        'controller' => 'ExerciciosController',
        'method' => 'buscarExercicioComVideos'
    ],
    // Exemplo de chamada exercícios normais: GET /exercicios/exercicioComVideos/normal/1
    // Exemplo de chamada exercícios adaptados: GET /exercicios/exercicioComVideos/adaptado/2

    // Rotas para TreinosController
    'treinos/criar' => [
        'controller' => 'TreinosController',
        'method' => 'criarTreino'
    ],
    // Exemplo de chamada:
    // POST /treinos/criar
    // {
    //     "nome": "Treino de Peito",
    //     "tipo": "Musculação",
    //     "criadoPor": "personal@example.com",
    //     "idAluno": 1,
    //     "idPersonal": 1,
    //     "descricao": "Treino focado em peitoral",
    //     "exercicios": [
    //         {
    //             "idExercicio": 1,
    //             "series": 3,
    //             "repeticoes": 12,
    //             "carga": 40.5,
    //             "ordem": 1,
    //             "observacoes": "Fazer com controle"
    //         },
    //         {
    //             "idExercAdaptado": 2,
    //             "series": 4,
    //             "repeticoes": 10,
    //             "carga": 0,
    //             "ordem": 2,
    //             "observacoes": "Adaptado para lesão"
    //         }
    //     ]
    // }

    'treinos/atualizar/(\d+)' => [
        'controller' => 'TreinosController',
        'method' => 'atualizarTreino'
    ],
    // Exemplo de chamada: PUT /treinos/atualizar/123
    // Corpo:  {
    //             "nome": "Treino de Peito Atualizado",
    //             "tipo": "Musculação",
    //             "descricao": "Treino revisado",
    //             "exercicios": [
    //                 {
    //                     "idExercicio": 1,
    //                     "series": 4,
    //                     "repeticoes": 10,
    //                     "carga": 45.0,
    //                     "ordem": 1,
    //                     "observacoes": "Aumentar carga"
    //                 }
    //             ]
    //         }

    'treinos/buscarExercicios' => [
        'controller' => 'TreinosController',
        'method' => 'buscarExercicios'
    ],
    // Exemplo de chamada: POST /treinos/buscarExercicios
    // Corpo: {
    //              "nome": "supino",
    //              "grupoMuscular": "peitoral"
    //         }

    // Rota para listar treinos do usuário autenticado
    'treinos/listarUsuario' => [
        'controller' => 'TreinosController',
        'method' => 'listarTreinosUsuario'
    ],
    // Exemplo de chamada: 
    // POST /treinos/listarUsuario
    // Corpo: {
    //              "tipo": "aluno",
    //              "id": 1,
    //              "email": "aluno@example.com"
    //         }


    'treinos/atribuirAluno' => [
        'controller' => 'TreinosController',
        'method' => 'atribuirTreinoAluno'
    ],
    // Exemplo de chamada:
    // POST /treinos/atribuirAluno
    // {
    //     "idTreino": 123,
    //     "idAluno": 2
    // }

    'treinos/excluir/(\d+)' => [
        'controller' => 'TreinosController',
        'method' => 'excluirTreino'
    ],
    // Exemplo de chamada:
    // DELETE /treinos/excluir/123
    // {
    //     "tipo": "personal",
    //     "id": 1,
    //     "email": "personal@example.com"
    // }

    // Rota para buscar treino completo com exercícios e vídeos
    'treinos/buscarCompleto/(\d+)' => [
        'controller' => 'TreinosController',
        'method' => 'buscarTreinoCompleto'
    ],
    // Exemplo de chamada: GET /treinos/buscarCompleto/123






    // Rotas para Alimentos
    'alimentos/buscar' => [
        'controller' => 'AlimentosController',
        'method' => 'buscarAlimentos'
    ],
    //Exemplo de chamada: GET http://localhost/BackEnd/alimentos/buscar?nome=banana
    // Retorna lista de alimentos com tradução PT-BR:
    //         {
    //     "success": true,
    //     "resultados": [
    //         {
    //             "id": 9003,
    //             "nome": "banana",
    //             "nome_original": "apple banana",
    //             "imagem": "https://spoonacular.com/cdn/ingredients_100x100/apple-banana.png"
    //         }
    //     ]
    // }

    // Informação detalhada do alimento é buscada em outra rota (ver abaixo)

    // Rota para buscar informação nutricional detalhada de um alimento por ID Spoonacular

    'alimentos/informacao' => [
        'controller' => 'AlimentosController',
        'method' => 'buscarInformacaoAlimento'
    ],
    // Exemplo de chamada: GET http://localhost/api/alimentos/informacao?id=9003&quantidade=100&unidade=g
    // Retorna:
    // {
    //     "success": true,
    //     "alimento": {
    //         "id": 9003,
    //         "nome": "banana",
    //         "nome_original": "apple banana",
    //         "categoria": "fruit",
    //         "imagem": "https://spoonacular.com/cdn/ingredients_250x250/apple-banana.png",
    //         "nutrientes": [
    //             {
    //                 "nome": "calorias",
    //                 "nome_original": "calories",
    //                 "quantidade": 89,
    //                 "unidade": "kcal",
    //                 "percentual_diario": 4.45
    //             },
    //             {
    //                 "nome": "proteínas",
    //                 "nome_original": "protein",
    //                 "quantidade": 1.1,
    //                 "unidade": "g",
    //                 "percentual_diario": 2.2
    //             }
    //         ],
    //         "quantidade_consulta": 100,
    //         "unidade_consulta": "g"
    //     }
    // }


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



    // Rota para testar conexão
    'config/testarConexao' => [
        'controller' => 'ConfigController',
        'method' => 'testarConexaoDB'
    ],

    // Rotas para ConvitesController (exigem autenticação, exceto getConvite para o link público)

    // Como Usar (Exemplos de Chamadas)
    // Criar Convite (Personal logado):

    // POST /convites/criar
    // Body: {"email": "aluno@example.com"} ou {"idAluno": 1}
    // Resposta: {"success": true, "link": "https://sua-api.com/convites/abc123..."}
    // Acessar Link (Aluno):

    // GET /convites/abc123...
    // Resposta: {"success": true, "data": {"nomePersonal": "João", "nomeAluno": "Maria", ...}}
    // Frontend: Mostra "João quer ser seu Personal. Aceitar? Negar?"
    // Aceitar:

    // POST /convites/abc123.../aceitar
    // Resposta: {"success": true, "message": "Convite aceito!"}
    // Negar:

    // POST /convites/abc123.../negar
    // Resposta: {"success": true, "message": "Convite negado."}

    'convites/criar' => [
        'controller' => 'ConvitesController',
        'method' => 'criarConvite'
    ],
    'convites/([a-zA-Z0-9]{64})' => [  // Captura token de 64 chars (bin2hex(random_bytes(32)))
        'controller' => 'ConvitesController',
        'method' => 'getConvite'
    ],
    'convites/([a-zA-Z0-9]{64})/aceitar' => [
        'controller' => 'ConvitesController',
        'method' => 'aceitarConvite'
    ],
    'convites/([a-zA-Z0-9]{64})/negar' => [
        'controller' => 'ConvitesController',
        'method' => 'negarConvite'
    ],

    // Rotas para Recuperação de Senha
    'recuperacao-senha/esqueci-senha' => [
        'controller' => 'RecuperacaoSenhaController',
        'method' => 'esqueciSenha'
    ],
    // Exemplo de chamada:
    // POST /recuperacao-senha/esqueci-senha
    // {
    //     "email": example.@gmail.com"
    // }

    'recuperacao-senha/resetar-senha' => [
        'controller' => 'RecuperacaoSenhaController',
        'method' => 'resetarSenha'
    ],

    // Exemplo de chamada:
    // POST /recuperacao-senha/resetar-senha
    // {
    //     "email": example.@gmail.com",
    //     "codigo": "123456",
    //     "novaSenha": "novaSenha123"
    // }
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
        'cadastro/verificar-email',
        'cadastro/verificar-cpf',
        'config/testarConexao',
        'recuperacao-senha/esqueci-senha',
        'recuperacao-senha/resetar-senha',

        //Todas as rotas GET para buscar alimentos e informações nutricionais
        'alimentos/buscar',
        'alimentos/informacao',
        'alimentos/testar-traducao',
        // 'alimentos/listar', // Manter protegida, só usuários autenticados podem ver alimentos salvos
        // 'alimentos/totais', // Manter protegida, só usuários autenticados
        // 'alimentos/adicionar', // Manter protegida, só usuários autenticados
        // 'alimentos/remover', // Manter protegida, só usuários autenticados
        // 'alimentos/atualizar', // Manter protegida, só usuários autenticados

        // Rota pública para acessar convite
        // O convite em si é protegido por token único no link
        'convites/([a-zA-Z0-9]{64})',
    ];
    // Se a rota não for pública, exige autenticação
    if (!in_array($clean_path, $rotasPublicas)) {
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
                    // Captura parâmetros da query string se não houver parâmetros na URL
                    if (empty($params)) {
                        parse_str($_SERVER['QUERY_STRING'] ?? '', $query_params);

                        // Para métodos GET, pega parâmetros específicos baseados no método
                        if ($method_http === 'GET') {
                            switch ($method_name) {
                                case 'buscarPorID':
                                    if (isset($query_params['id'])) {
                                        $params[] = $query_params['id'];
                                    }
                                    break;
                                case 'buscarPorNome':
                                    if (isset($query_params['nome'])) {
                                        $params[] = $query_params['nome'];
                                    }
                                    break;
                                case 'deletarExercicio':
                                    if (isset($query_params['id'])) {
                                        $params[] = $query_params['id'];
                                    }
                                    break;
                                case 'verificarEmail':
                                    if (isset($query_params['email'])) {
                                        $params[] = ['email' => $query_params['email']];
                                    }
                                    break;
                                case 'verificarCpf':
                                    if (isset($query_params['cpf'])) {
                                        $params[] = ['cpf' => $query_params['cpf']];
                                    }
                                    break;
                            }
                        }
                    }

                    // Captura os dados do corpo da requisição para POST, PUT
                    $data = [];
                    if (in_array($method_http, ['POST', 'PUT'])) {
                        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';

                        if (strpos($content_type, 'application/json') !== false) {
                            $data = json_decode(file_get_contents('php://input'), true);
                            if ($data === null) {
                                $data = [];
                            }
                        } else {
                            $data = $_POST;
                        }
                    }

                    // Prepara os parâmetros para chamar o método
                    $method_params = [];

                    // Adiciona parâmetros da URL ou query string
                    if (!empty($params)) {
                        $method_params = array_merge($method_params, $params);
                    }

                    // Adiciona dados do corpo para POST/PUT
                    if (in_array($method_http, ['POST', 'PUT']) && !empty($data)) {
                        $method_params[] = $data;
                    }

                    // Verifica se os parâmetros necessários estão presentes
                    $reflection = new ReflectionMethod($controller_instance, $method_name);
                    $required_params = $reflection->getNumberOfRequiredParameters();

                    if (count($method_params) >= $required_params) {
                        // Chama o método do controlador com os parâmetros
                        call_user_func_array([$controller_instance, $method_name], $method_params);
                    } else {
                        // Para métodos sem parâmetros obrigatórios, chama sem parâmetros
                        if ($required_params === 0) {
                            call_user_func([$controller_instance, $method_name]);
                        } else {
                            http_response_code(400);
                            echo json_encode(["error" => "Parâmetros insuficientes para o método '$method_name'"]);
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
