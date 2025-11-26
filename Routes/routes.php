<?php

    // Incluir jwt.config.php uma vez
    require_once __DIR__ . '/../Config/jwt.config.php';

    // Define as rotas do sistema
    $routes = [

        // Rota para raiz (URL vazia)
        '' =>[
            'controller' => 'ConfigController',
            'method' => 'bemVindo'
        ],
        //Rota Padrão
        '/' =>[
            'controller' => 'ConfigController',
            'method' => 'bemVindo'
        ],

        // Rotas para Cadastro
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
        'cadastro/processar-completo' => [
            'controller' => 'CadastroController',
            'method' => 'processarCadastroCompleto'
        ],
        'cadastro/completar-aluno' => [
            'controller' => 'CadastroController',
            'method' => 'completarCadastroAluno'
        ],
        'cadastro/completar-personal' => [
            'controller' => 'CadastroController',
            'method' => 'completarCadastroPersonal'
        ],
        'cadastro/completar-academia' => [
            'controller' => 'CadastroController',
            'method' => 'completarCadastroAcademia'
        ],
        'cadastro/modalidades' => [
            'controller' => 'CadastroController',
            'method' => 'listarModalidades'
        ],

        // Rota para upload de foto de perfil
        'upload/foto-perfil' => [
            'controller' => 'UploadController',
            'method' => 'uploadFotoPerfil'
        ],
        'upload/salvar-foto-usuario' => [
            'controller' => 'UploadController',
            'method' => 'salvarFotoUsuario'
        ],
        'upload/obter-foto-usuario' => [
            'controller' => 'UploadController',
            'method' => 'obterFotoUsuario'
        ],
        'upload/deletar-foto' => [
            'controller' => 'UploadController',
            'method' => 'deletarFotoPerfil'
        ],
        // Rota para verificar arquivo enviado
        'upload/verificar-arquivo' => [
            'controller' => 'UploadController',
            'method' => 'verificarArquivo'
        ],
        'upload/cref-documento' => [
            'controller' => 'UploadController',
            'method' => 'uploadDocumentoCREF'
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

        // =============================
        // ROTAS PARA EXERCÍCIOS CONTROLLER 
        // =============================

        // Exercícios - Buscas gerais
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
        'exercicios/por-tipo/([a-zA-Z]+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExerciciosPorTipo'
        ],

        // Exercícios - CRUD tradicional (para admins)
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

        // Exercícios - Funcionalidades para Personais

        'exercicios/normais' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExerciciosNormais'
        ],
        'exercicios/adaptados' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExerciciosAdaptados'
        ],
        'exercicios/meus-exercicios' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarMeusExercicios'
        ],
        'exercicios/por-tipo/([a-zA-Z]+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExerciciosPorTipo'
        ],
        'exercicios/cadastrar-personal' => [
            'controller' => 'ExerciciosController',
            'method' => 'cadastrarExercicioPersonal'
        ],

        // Exercícios - Buscas específicas
        'exercicios/globais' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExerciciosGlobais'
        ],
        'exercicios/para-aluno' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExerciciosParaAluno'
        ],

        // Rota para buscar exercício com vídeos
        'exercicios/exercicioComVideos/(\w+)/(\d+)' => [
            'controller' => 'ExerciciosController',
            'method' => 'buscarExercicioComVideos'
        ],

        // =============================
        // ROTAS PARA TREINOS CONTROLLER 
        // =============================

        // Criar treino (básico)
        'treinos/criar' => [
            'controller' => 'TreinosController',
            'method' => 'criarTreino'
        ],

        // Atualizar treino (nome, tipo, descrição)
        'treinos/atualizar/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'atualizarTreino'
        ],

        // Adicionar exercício ao treino
        'treinos/(\d+)/adicionar-exercicio' => [
            'controller' => 'TreinosController',
            'method' => 'adicionarExercicioAoTreino'
        ],

        // Listar treinos do aluno
        'treinos/aluno/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosAluno'
        ],

        // Listar treinos do personal
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

        // Atribuir treino a aluno
        'treinos/atribuir' => [
            'controller' => 'TreinosController',
            'method' => 'atribuirTreinoAluno'
        ],

        // Excluir treino
        'treinos/excluir/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'excluirTreino',
            'http_method' => 'DELETE'
        ],

        // Listar alunos do personal
        'treinos/personal/(\d+)/alunos' => [
            'controller' => 'TreinosController',
            'method' => 'listarAlunosDoPersonal'
        ],

        // Listar treinos atribuídos a um aluno específico
        'treinos/personal/(\d+)/aluno/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosDoAlunoAtribuidos'
        ],

        // Atualizar treino atribuído
        'treinos/atribuido/(\d+)/atualizar' => [
            'controller' => 'TreinosController',
            'method' => 'atualizarTreinoAtribuido'
        ],

        // Desatribuir treino do aluno
        'treinos/atribuido/(\d+)/desatribuir' => [
            'controller' => 'TreinosController',
            'method' => 'desatribuirTreinoDoAluno'
        ],

        // Desvincular aluno do personal
        'personal/(\d+)/desvincular-aluno/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'desvincularAluno'
        ],

        // Listar meus treinos (personal)
        'treinos/personal/(\d+)/meus-treinos' => [
            'controller' => 'TreinosController',
            'method' => 'listarMeusTreinosPersonal'
        ],

        // Buscar exercícios para treino
        'treinos/buscarExercicios' => [
            'controller' => 'TreinosController',
            'method' => 'buscarExercicios'
        ],

        'treinos/(\d+)/exercicios' => [
            'controller' => 'TreinosController',
            'method' => 'listarExerciciosDoTreino'
        ],

        // Atualizar exercício no treino
        'treinos/exercicio/(\d+)/atualizar' => [
            'controller' => 'TreinosController',
            'method' => 'atualizarExercicioNoTreino'
        ],

        // Remover exercício do treino
        'treinos/exercicio/(\d+)/remover' => [
            'controller' => 'TreinosController',
            'method' => 'removerExercicioDoTreino'
        ],

        // Listar treinos do usuário autenticado
        'treinos/listarUsuario' => [
            'controller' => 'TreinosController',
            'method' => 'listarTreinosUsuario'
        ],

        // Buscar treino completo com exercícios e vídeos
        'treinos/buscarCompleto/(\d+)' => [
            'controller' => 'TreinosController',
            'method' => 'buscarTreinoCompleto'
        ],

        // Rotas para sessões/histórico de treinos
        'treinos/historico' => [
            'controller' => 'TreinosController',
            'method' => 'getHistoricoTreinos'
        ],
        'treinos/criar-sessao' => [
            'controller' => 'TreinosController',
            'method' => 'criarSessaoTreino'
        ],
        'treinos/finalizar-sessao/(\d+)' => [  // (\d+) para capturar o ID como parâmetro
            'controller' => 'TreinosController',
            'method' => 'finalizarSessaoTreino'
        ],
        'treinos/retomar-sessao/(\d+)' => [  // (\d+) para capturar o ID
            'controller' => 'TreinosController',
            'method' => 'getSessaoParaRetomar'
        ],

        // =============================
        // ROTAS PARA VÍDEOS CONTROLLER
        // =============================
        
        'videos/upload' => [
            'controller' => 'VideosController',
            'method' => 'uploadVideo'
        ],
        'videos/associar-exercicio' => [
            'controller' => 'VideosController',
            'method' => 'associarVideoExercicio'
        ],
        'videos/exercicio/(\d+)' => [
            'controller' => 'VideosController',
            'method' => 'buscarVideosPorExercicio'
        ],

        // =============================
        // ROTAS PARA ALIMENTOS CONTROLLER
        // =============================

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

        'alimentos/diagnosticar-busca' => [
            'controller' => 'AlimentosController',
            'method' => 'diagnosticarBusca'
        ],

        // Rotas para Refeições
        'alimentos/listar-refeicoes' => [
            'controller' => 'AlimentosController',
            'method' => 'listarRefeicoes'
        ],
        'alimentos/listar-refeicoes-simples' => [
            'controller' => 'AlimentosController',
            'method' => 'listarRefeicoesSimples'
        ],
        'alimentos/criar-refeicao' => [
            'controller' => 'AlimentosController',
            'method' => 'criarRefeicao'
        ],
        'alimentos/remover-refeicao' => [
            'controller' => 'AlimentosController',
            'method' => 'removerRefeicao'
        ],
        'alimentos/refeicoes-hoje' => [
            'controller' => 'AlimentosController',
            'method' => 'listarRefeicoesHoje'
        ],
        'alimentos/adicionar-refeicao' => [
            'controller' => 'AlimentosController',
            'method' => 'adicionarAlimentoRefeicao'
        ],
        'alimentos/refeicao/(\d+)' => [
            'controller' => 'AlimentosController',
            'method' => 'listarAlimentosRefeicao'
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

        // Rota para testar conexão
        'config/testarConexao' => [
            'controller' => 'ConfigController',
            'method' => 'testarConexaoDB'
        ],

        // Rotas para ConvitesController
        'convites/criar' => [
            'controller' => 'ConvitesController',
            'method' => 'criarConvite'
        ],
        'convites/email/([^/]+)' => [
            'controller' => 'ConvitesController', 
            'method' => 'getConvitesByEmail'
        ],
        'convites/([^/]+)' => [
            'controller' => 'ConvitesController',
            'method' => 'getConvites'
        ],

        'convites/(\d+)/aceitar' => [
            'controller' => 'ConvitesController',
            'method' => 'aceitarConvite'
        ],
        'convites/(\d+)/negar' => [
            'controller' => 'ConvitesController',
            'method' => 'negarConvite'
        ],

        // Rotas para Recuperação de Senha
        'recuperacao-senha/esqueci-senha' => [
            'controller' => 'RecuperacaoSenhaController',
            'method' => 'esqueciSenha'
        ],
        'recuperacao-senha/resetar-senha' => [
            'controller' => 'RecuperacaoSenhaController',
            'method' => 'resetarSenha'
        ],

        // Rotas para Perfil

        'perfil/completo/([^/]+)/(\d+)' => [
            'controller' => 'PerfilController', 
            'method' => 'getPerfilCompleto'
        ],
        'perfil/(.+)' => [
            'controller' => 'PerfilController',
            'method' => 'getPerfilPorEmail'
        ],
        'perfil/usuario/([A-Za-z0-9@._-]+)' => [
            'controller' => 'PerfilController',
            'method' => 'getUsuarioPorEmail'
        ],
        'perfil/atualizar' => [
            'controller' => 'PerfilController',
            'method' => 'atualizarPerfil'
        ],
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

        'perfil/personalNM/(\d+)' => [
            'controller' => 'PerfilController', // ou PersonalController
            'method' => 'getPersonalPorId'
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
        'perfil/atualizar-completo' => [
            'controller' => 'PerfilController',
            'method' => 'atualizarPerfilCompleto'
        ],

        'academia/solicitacao/status/([^/]+)/(\d+)' => [
            'controller' => 'AcademiasController',
            'method' => 'getStatusSolicitacao'
        ],

        // Rotas para Planos
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

        // Rotas para Pagamentos
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

        // Rotas Conectar Aluno/Personal

        'academias' => [
            'controller' => 'ConnectPersonalController', 
            'method' => 'listarAcademias'
        ],
        'academias-ativas' => [
            'controller' => 'CadastroController',
            'method' => 'listarAcademiasAtivas'
        ],
        'personais' => [
            'controller' => 'ConectarPersonalController',
            'method' => 'listarPersonais'
        ],
        'alunos' => [
            'controller' => 'ConectarPersonalController', 
            'method' => 'listarAlunos'
        ],
        'convite' => [
            'controller' => 'ConectarPersonalController',
            'method' => 'enviarConvite'
        ],
        'modalidades' => [
            'controller' => 'ConectarPersonalController',
            'method' => 'listarModalidades'
        ],
        'meus-convites' => [
            'controller' => 'ConectarPersonalController',
            'method' => 'meusConvites'
        ],

        // Rotas para AcademiasController
        'academia/painel' => [
            'controller' => 'AcademiasController',
            'method' => 'getPainelControle'
        ],
        'academia/solicitacao/enviar' => [
            'controller' => 'AcademiasController',
            'method' => 'enviarSolicitacaoVinculacao'
        ],
        'academia/solicitacao/(\d+)/aceitar' => [
            'controller' => 'AcademiasController',
            'method' => 'aceitarSolicitacao'
        ],
        'academia/solicitacao/(\d+)/recusar' => [
            'controller' => 'AcademiasController',
            'method' => 'recusarSolicitacao'
        ],
        'academia/desvincular' => [
            'controller' => 'AcademiasController',
            'method' => 'desvincularUsuario'
        ],

        // Rotas para Endereço
        'endereco/(.+)' => [
            'controller' => 'EnderecoController',
            'method' => 'getEnderecoPorEmail'
        ],
        'endereco/atualizar' => [
            'controller' => 'EnderecoController',
            'method' => 'atualizarEndereco'
        ],

    ];

    // Mapeamento de controladores - ADICIONAR VideosController
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
        'VideosController' => __DIR__ . '/../Controllers/VideosController.php',
        'ConectarPersonalController' => __DIR__ . '/../Controllers/ConectarPersonalController.php',
        'UploadController' => __DIR__ . '/../Controllers/UploadController.php',
        'AcademiasController' => __DIR__ . '/../Controllers/AcademiasController.php',
        'EnderecoController' => __DIR__ . '/../Controllers/EnderecoController.php',
    ];

    // ATUALIZAR Rotas Públicas - Adicionar novas rotas públicas
    $rotasPublicas = [
        '',
        '/',
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
        'convites/email/([^/]+)',
        'convites/([a-zA-Z0-9]{64})',
        'perfil/aluno/(\d+)',
        'perfil/personal/(\d+)',
        'perfil/academia/(\d+)',
        'perfil/dev/(\d+)',
        'perfil/aluno',
        'perfil/personal',
        'perfil/academia',
        'planos',
        'planos/(\d+)',
        'exercicios/buscarTodos',
        'exercicios/globais',
        'academias-ativas',
        'modalidades',
        'personais',
        'alunos',
        'academias',
        'meus-convites',
        'convite',
        'upload/foto-perfil',
        'upload/salvar-foto-usuario',
        'upload/obter-foto-usuario',
        'upload/deletar-foto',
        'cadastro/modalidades',
        'cadastro/processar-completo',
        'cadastro/completar-aluno',
        'cadastro/completar-personal', 
        'cadastro/completar-academia',
        'academia/painel',
        'academia/solicitacao/enviar',
        'academia/solicitacao/(\d+)/aceitar', 
        'academia/solicitacao/(\d+)/recusar',
        'academia/desvincular',
        'endereco/([^/]+)',
        'endereco/atualizar',
        'upload/verificar-arquivo',
        'upload/cref-documento',
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
            '',
            '/',
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
            'convites/email/([^/]+)',
            'convites/([a-zA-Z0-9]{64})',
            'perfil/aluno/(\d+)',
            'perfil/personal/(\d+)',
            'perfil/academia/(\d+)',
            'perfil/dev/(\d+)',
            'perfil/aluno',
            'perfil/personal',
            'perfil/academia',
            'planos',
            'planos/(\d+)',
            'academias-ativas',
            'modalidades',
            'personais',
            'alunos',
            'academias',
            'meus-convites',
            'convite',
            'upload/foto-perfil',
            'upload/salvar-foto-usuario',
            'upload/obter-foto-usuario',
            'upload/deletar-foto',
            'cadastro/modalidades',
            'cadastro/processar-completo',
            'cadastro/completar-aluno',
            'cadastro/completar-personal', 
            'cadastro/completar-academia',
            'academia/painel',
            'academia/solicitacao/enviar',
            'academia/solicitacao/(\d+)/aceitar', 
            'academia/solicitacao/(\d+)/recusar',
            'academia/desvincular',
            'endereco/([^/]+)',
            'endereco/atualizar',
            'upload/verificar-arquivo',
            'upload/cref-documento',
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
                                    case 'verificarCnpj':
                                        if (isset($query_params['cnpj'])) {
                                            $params[] = ['cnpj' => $query_params['cnpj']];
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

?>