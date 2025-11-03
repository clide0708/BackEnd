<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class ConectarPersonalController
    {
        private $db;

        public function __construct()
        {
            $this->db = DB::connectDB();
        }

        /**
         * Listar personais disponíveis com filtros avançados
         */
        public function listarPersonais()
        {
            header('Content-Type: application/json');

            try {
                // Obter usuário autenticado
                $usuario = $this->getUsuarioFromToken();
                
                if (!$usuario || !isset($usuario['tipo']) || !in_array($usuario['tipo'], ['aluno', 'personal'])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Apenas alunos e personais podem acessar esta funcionalidade'
                    ]);
                    return;
                }

                // Obter filtros
                $filtros = $this->obterFiltrosDaRequisicao();
                error_log("🎯 Filtros recebidos em listarPersonais: " . json_encode($filtros));

                // ⭐⭐ OTIMIZAÇÃO: Obter localização apenas se necessário
                $localizacaoUsuario = null;
                $usarLocalizacao = !empty($filtros['raio_km']) && $filtros['raio_km'] > 0;
                
                if ($usarLocalizacao) {
                    $localizacaoUsuario = $this->obterLocalizacaoUsuario($usuario, $filtros);
                }

                // ⭐⭐ OTIMIZAÇÃO: Query base simplificada inicialmente
                $sql = "
                    SELECT 
                        p.idPersonal,
                        p.nome,
                        p.foto_perfil,
                        p.genero,
                        p.idade,
                        p.data_nascimento,
                        CONCAT(p.cref_numero, '-', p.cref_categoria, '/', p.cref_regional) as cref,
                        p.cref_categoria as cref_tipo,
                        p.treinos_adaptados,
                        p.sobre,
                        e.cidade,
                        e.estado,
                        e.latitude,
                        e.longitude
                    FROM personal p
                    LEFT JOIN enderecos_usuarios e ON p.idPersonal = e.idUsuario AND e.tipoUsuario = 'personal'
                    WHERE p.status_conta = 'Ativa'
                ";

                $params = [];
                $conditions = [];

                // Aplicar filtros básicos primeiro (mais seletivos)
                $this->aplicarFiltrosComuns($sql, $conditions, $params, $filtros, 'personal');
                $this->aplicarFiltrosPersonal($sql, $conditions, $params, $filtros);

                if (!empty($conditions)) {
                    $sql .= " AND " . implode(" AND ", $conditions);
                }

                // ⭐⭐ OTIMIZAÇÃO: Ordenar por ID inicialmente para resposta rápida
                $sql .= " ORDER BY p.idPersonal DESC LIMIT 100";

                error_log("🔍 SQL Personais (Otimizado): " . $sql);
                error_log("📊 Parâmetros Personais: " . json_encode($params));

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $personais = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ⭐⭐ OTIMIZAÇÃO: Processar modalidades em lote separado
                if (!empty($personais)) {
                    $idsPersonais = array_column($personais, 'idPersonal');
                    $modalidadesPorPersonal = $this->obterModalidadesEmLote($idsPersonais, 'personal');
                    
                    // Buscar contagem de treinos em lote
                    $treinosCountPorPersonal = $this->obterContagemTreinosEmLote($idsPersonais);
                }

                // Processar resultados com cálculo de distância
                $resultados = [];
                foreach ($personais as $personal) {
                    // Calcular distância se necessário
                    $distancia = null;
                    $precisao_distancia = null;
                    
                    if ($usarLocalizacao && $localizacaoUsuario && 
                        $localizacaoUsuario['latitude'] && $localizacaoUsuario['longitude'] &&
                        $personal['latitude'] && $personal['longitude']) {
                        
                        $distancia = $this->calcularDistancia(
                            $localizacaoUsuario['latitude'],
                            $localizacaoUsuario['longitude'],
                            $personal['latitude'],
                            $personal['longitude']
                        );

                        $precisao_distancia = $localizacaoUsuario['precisao'] ?? 'media';

                        // Aplicar filtro de raio
                        if ($distancia > $filtros['raio_km']) {
                            continue;
                        }
                    }

                    // Verificar convite pendente
                    $convitePendente = $this->verificarConvitePendente(
                        $usuario['id'], 
                        'aluno', 
                        $personal['idPersonal'], 
                        'personal'
                    );

                    // Processar modalidades do lote
                    $modalidades = $modalidadesPorPersonal[$personal['idPersonal']] ?? [];

                    $resultados[] = [
                        'idPersonal' => $personal['idPersonal'],
                        'nome' => $personal['nome'],
                        'genero' => $personal['genero'],
                        'idade' => $personal['idade'],
                        'foto_perfil' => $personal['foto_perfil'],
                        'cref' => $personal['cref'],
                        'cref_tipo' => $personal['cref_tipo'],
                        'treinosAdaptados' => (bool)$personal['treinos_adaptados'],
                        'sobre' => $personal['sobre'],
                        'cidade' => $personal['cidade'],
                        'estado' => $personal['estado'],
                        'modalidades' => $modalidades,
                        'treinos_count' => $treinosCountPorPersonal[$personal['idPersonal']] ?? 0,
                        'distancia_km' => $distancia,
                        'precisao_distancia' => $precisao_distancia,
                        'convitePendente' => $convitePendente,
                        'possui_localizacao' => !empty($personal['latitude']) && !empty($personal['longitude'])
                    ];
                }

                // ⭐⭐ OTIMIZAÇÃO: Ordenar por distância se aplicável
                if ($usarLocalizacao && $localizacaoUsuario) {
                    usort($resultados, function($a, $b) {
                        return ($a['distancia_km'] ?? 9999) <=> ($b['distancia_km'] ?? 9999);
                    });
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $resultados,
                    'total' => count($resultados),
                    'localizacao_usuario' => $localizacaoUsuario,
                    'debug' => [
                        'filtros_recebidos' => $filtros,
                        'query_executada' => $sql,
                        'parametros_query' => $params,
                        'tempo_processamento' => microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]
                    ]
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro listarPersonais: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            } catch (Exception $e) {
                error_log("❌ Erro geral listarPersonais: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro interno: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Listar alunos disponíveis para personais
         */
        public function listarAlunos()
        {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                
                if (!$usuario || !isset($usuario['tipo']) || !in_array($usuario['tipo'], ['aluno', 'personal'])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Apenas alunos e personais podem acessar esta funcionalidade'
                    ]);
                    return;
                }

                // ⭐⭐ CORREÇÃO: Obter filtros da query string
                $filtros = $this->obterFiltrosDaRequisicao();
                error_log("🎯 Filtros recebidos em listarAlunos: " . json_encode($filtros));

                // Obter localização do usuário para cálculo de distância
                $localizacaoUsuario = $this->obterLocalizacaoUsuario($usuario, $filtros);

                // Construir query base
                $sql = "
                    SELECT DISTINCT
                        a.idAluno,
                        a.nome,
                        a.foto_perfil,
                        a.genero,
                        a.idade,
                        a.data_nascimento,
                        a.altura,
                        a.peso,
                        a.meta,
                        a.treinos_adaptados,
                        a.treinoTipo,
                        a.idPersonal,
                        a.status_conta,
                        e.cidade,
                        e.estado,
                        e.latitude,
                        e.longitude,
                        GROUP_CONCAT(DISTINCT m.nome) as modalidades_nomes,
                        ac.nome as nome_academia,
                        ac.idAcademia
                    FROM alunos a
                    LEFT JOIN enderecos_usuarios e ON a.idAluno = e.idUsuario AND e.tipoUsuario = 'aluno'
                    LEFT JOIN modalidades_aluno ma ON a.idAluno = ma.idAluno
                    LEFT JOIN modalidades m ON ma.idModalidade = m.idModalidade
                    LEFT JOIN academias ac ON a.idAcademia = ac.idAcademia
                    WHERE a.status_conta = 'Ativa' 
                    AND a.idPersonal IS NULL
                ";

                $params = [];
                $conditions = [];

                // ⭐⭐ CORREÇÃO: Aplicar filtros corretamente
                $this->aplicarFiltrosComuns($sql, $conditions, $params, $filtros, 'aluno');
                $this->aplicarFiltrosAluno($sql, $conditions, $params, $filtros);

                if (!empty($conditions)) {
                    $sql .= " AND " . implode(" AND ", $conditions);
                }

                $sql .= " GROUP BY a.idAluno, e.idEndereco ";
                
                // Ordenar por distância se houver localização de referência
                if ($localizacaoUsuario && $localizacaoUsuario['latitude'] && $localizacaoUsuario['longitude']) {
                    $sql .= " ORDER BY calcular_distancia_aproximada(?, ?, e.latitude, e.longitude) ASC";
                    array_unshift($params, $localizacaoUsuario['latitude'], $localizacaoUsuario['longitude']);
                } else {
                    $sql .= " ORDER BY a.data_cadastro DESC";
                }

                error_log("🔍 SQL Alunos: " . $sql);
                error_log("📊 Parâmetros Alunos: " . json_encode($params));

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Processar resultados com cálculo de distância
                $resultados = [];
                foreach ($alunos as $aluno) {
                    // Calcular distância se tivermos coordenadas
                    $distancia = null;
                    if ($localizacaoUsuario && $localizacaoUsuario['latitude'] && $localizacaoUsuario['longitude'] &&
                        $aluno['latitude'] && $aluno['longitude']) {
                        
                        $distancia = $this->calcularDistancia(
                            $localizacaoUsuario['latitude'],
                            $localizacaoUsuario['longitude'],
                            $aluno['latitude'],
                            $aluno['longitude']
                        );

                        // Aplicar filtro de raio se especificado
                        if (!empty($filtros['raio_km']) && $filtros['raio_km'] > 0 && $distancia > $filtros['raio_km']) {
                            continue; // Pular alunos fora do raio
                        }
                    }

                    // Verificar convite pendente
                    $convitePendente = $this->verificarConvitePendente(
                        $usuario['id'], 
                        'personal', 
                        $aluno['idAluno'], 
                        'aluno'
                    );

                    // Processar modalidades
                    $modalidades = [];
                    if (!empty($aluno['modalidades_nomes'])) {
                        $modalidades = explode(',', $aluno['modalidades_nomes']);
                    }

                    $resultados[] = [
                        'idAluno' => $aluno['idAluno'],
                        'nome' => $aluno['nome'],
                        'genero' => $aluno['genero'],
                        'idade' => $aluno['idade'],
                        'altura' => $aluno['altura'],
                        'peso' => $aluno['peso'],
                        'meta' => $aluno['meta'],
                        'foto_perfil' => $aluno['foto_perfil'],
                        'treinosAdaptados' => (bool)$aluno['treinos_adaptados'],
                        'treinoTipo' => $aluno['treinoTipo'],
                        'cidade' => $aluno['cidade'],
                        'estado' => $aluno['estado'],
                        'modalidades' => $modalidades,
                        'nomeAcademia' => $aluno['nome_academia'],
                        'distancia_km' => $distancia,
                        'convitePendente' => $convitePendente,
                        'possui_localizacao' => !empty($aluno['latitude']) && !empty($aluno['longitude'])
                    ];
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $resultados,
                    'total' => count($resultados),
                    'localizacao_usuario' => $localizacaoUsuario,
                    'debug' => [
                        'filtros_recebidos' => $filtros,
                        'query_executada' => $sql,
                        'parametros_query' => $params
                    ]
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro listarAlunos: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            } catch (Exception $e) {
                error_log("❌ Erro geral listarAlunos: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro interno: ' . $e->getMessage()
                ]);
            }
        }

        private function obterFiltrosDaRequisicao()
        {
            $filtros = [
                'academia_id' => $_GET['academia_id'] ?? '',
                'genero' => $_GET['genero'] ?? '',
                'localizacao' => $_GET['localizacao'] ?? '',
                'modalidades' => isset($_GET['modalidades']) ? explode(',', $_GET['modalidades']) : [],
                'treinosAdaptados' => $_GET['treinosAdaptados'] ?? '',
                
                // ⭐⭐ CORREÇÃO: Garantir que idade_min e idade_max sejam números
                'idade_min' => isset($_GET['idade_min']) && is_numeric($_GET['idade_min']) ? (int)$_GET['idade_min'] : '',
                'idade_max' => isset($_GET['idade_max']) && is_numeric($_GET['idade_max']) ? (int)$_GET['idade_max'] : '',
                
                'meta' => $_GET['meta'] ?? '',
                'cref_tipo' => $_GET['cref_tipo'] ?? '',
                'raio_km' => isset($_GET['raio_km']) ? (int)$_GET['raio_km'] : 50,
                'latitude' => $_GET['latitude'] ?? null,
                'longitude' => $_GET['longitude'] ?? null
            ];

            // Log para debug
            error_log("🎯 Filtros extraídos: " . json_encode($filtros));

            return $filtros;
        }

        /**
         * Enviar convite bidirecional
         */
        public function enviarConvite($data)
        {
            header('Content-Type: application/json');

            try {
                // Validação dos dados
                $required = ['id_remetente', 'tipo_remetente', 'id_destinatario', 'tipo_destinatario'];
                foreach ($required as $field) {
                    if (!isset($data[$field])) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => "Campo obrigatório: {$field}"
                        ]);
                        return;
                    }
                }

                $idRemetente = $data['id_remetente'];
                $tipoRemetente = $data['tipo_remetente'];
                $idDestinatario = $data['id_destinatario'];
                $tipoDestinatario = $data['tipo_destinatario'];
                $mensagem = $data['mensagem'] ?? null;

                // Validar tipos
                if (!in_array($tipoRemetente, ['aluno', 'personal']) || 
                    !in_array($tipoDestinatario, ['aluno', 'personal'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Tipo de remetente/destinatário inválido'
                    ]);
                    return;
                }

                // Verificar se é o mesmo usuário
                if ($idRemetente == $idDestinatario && $tipoRemetente == $tipoDestinatario) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Não é possível enviar convite para si mesmo'
                    ]);
                    return;
                }

                // Verificar se remetente existe e está ativo
                if (!$this->verificarUsuarioAtivo($idRemetente, $tipoRemetente)) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Remetente não encontrado ou inativo'
                    ]);
                    return;
                }

                // Verificar se destinatário existe e está ativo
                if (!$this->verificarUsuarioAtivo($idDestinatario, $tipoDestinatario)) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Destinatário não encontrado ou inativo'
                    ]);
                    return;
                }

                // Verificar se já existe convite pendente
                if ($this->verificarConvitePendente($idRemetente, $tipoRemetente, $idDestinatario, $tipoDestinatario)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Já existe um convite pendente entre estes usuários'
                    ]);
                    return;
                }

                // Preparar dados para inserção
                if ($tipoRemetente === 'personal') {
                    $idPersonal = $idRemetente;
                    $idAluno = $idDestinatario;
                } else {
                    $idPersonal = $idDestinatario;
                    $idAluno = $idRemetente;
                }

                // Gerar token único
                $token = bin2hex(random_bytes(32));

                // Inserir convite
                $stmt = $this->db->prepare("
                    INSERT INTO convites (
                        token, idPersonal, idAluno, email_aluno, status, data_criacao,
                        tipo_remetente, tipo_destinatario, mensagem
                    ) VALUES (?, ?, ?, NULL, 'pendente', NOW(), ?, ?, ?)
                ");

                $success = $stmt->execute([
                    $token,
                    $idPersonal,
                    $idAluno,
                    $tipoRemetente,
                    $tipoDestinatario,
                    $mensagem
                ]);

                if ($success) {
                    $idConvite = $this->db->lastInsertId();

                    // Buscar dados do convite criado
                    $stmt = $this->db->prepare("
                        SELECT c.*, 
                            p.nome as nome_personal,
                            a.nome as nome_aluno
                        FROM convites c
                        LEFT JOIN personal p ON c.idPersonal = p.idPersonal
                        LEFT JOIN alunos a ON c.idAluno = a.idAluno
                        WHERE c.idConvite = ?
                    ");
                    $stmt->execute([$idConvite]);
                    $convite = $stmt->fetch(PDO::FETCH_ASSOC);

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Convite enviado com sucesso',
                        'data' => [
                            'idConvite' => $idConvite,
                            'status' => 'pendente',
                            'data_criacao' => $convite['data_criacao'],
                            'mensagem' => $mensagem,
                            'nome_remetente' => $tipoRemetente === 'personal' ? $convite['nome_personal'] : $convite['nome_aluno'],
                            'nome_destinatario' => $tipoDestinatario === 'personal' ? $convite['nome_personal'] : $convite['nome_aluno']
                        ]
                    ]);
                } else {
                    throw new Exception('Erro ao inserir convite no banco');
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
        }

        private function obterContagemTreinosEmLote($idsPersonais)
        {
            if (empty($idsPersonais)) return [];
            
            $placeholders = str_repeat('?,', count($idsPersonais) - 1) . '?';
            
            $sql = "
                SELECT idPersonal, COUNT(*) as count
                FROM treinos
                WHERE idPersonal IN ({$placeholders})
                GROUP BY idPersonal
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($idsPersonais);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $contagemPorPersonal = [];
            foreach ($resultados as $linha) {
                $contagemPorPersonal[$linha['idPersonal']] = (int)$linha['count'];
            }
            
            return $contagemPorPersonal;
        }

        private function obterModalidadesEmLote($idsUsuarios, $tipoUsuario)
        {
            if (empty($idsUsuarios)) return [];
            
            $placeholders = str_repeat('?,', count($idsUsuarios) - 1) . '?';
            $tabela = $tipoUsuario === 'personal' ? 'modalidades_personal' : 'modalidades_aluno';
            $campoId = $tipoUsuario === 'personal' ? 'idPersonal' : 'idAluno';
            
            $sql = "
                SELECT ma.{$campoId} as idUsuario, m.nome
                FROM {$tabela} ma
                JOIN modalidades m ON ma.idModalidade = m.idModalidade
                WHERE ma.{$campoId} IN ({$placeholders})
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($idsUsuarios);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agrupar por usuário
            $modalidadesPorUsuario = [];
            foreach ($resultados as $linha) {
                $modalidadesPorUsuario[$linha['idUsuario']][] = $linha['nome'];
            }
            
            return $modalidadesPorUsuario;
        }

        /**
         * Buscar modalidades disponíveis
         */
        public function listarModalidades()
        {
            header('Content-Type: application/json');

            try {
                $stmt = $this->db->prepare("
                    SELECT idModalidade, nome, descricao 
                    FROM modalidades 
                    WHERE ativo = 1
                    ORDER BY nome
                ");
                $stmt->execute();
                $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $modalidades
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Helper para verificar se usuário está ativo
         */
        private function verificarUsuarioAtivo($idUsuario, $tipoUsuario)
        {
            $tabela = $tipoUsuario === 'aluno' ? 'alunos' : 'personal';
            $campoId = $tipoUsuario === 'aluno' ? 'idAluno' : 'idPersonal';

            $stmt = $this->db->prepare("
                SELECT {$campoId} FROM {$tabela} 
                WHERE {$campoId} = ? AND status_conta = 'Ativa'
            ");
            $stmt->execute([$idUsuario]);
            return $stmt->fetch() !== false;
        }

        /**
         * Helper para verificar convite pendente
         */
        private function verificarConvitePendente($idRemetente, $tipoRemetente, $idDestinatario, $tipoDestinatario)
        {
            $stmt = $this->db->prepare("
                SELECT idConvite FROM convites 
                WHERE (
                    (idPersonal = ? AND idAluno = ?) OR 
                    (idPersonal = ? AND idAluno = ?)
                ) AND status = 'pendente'
            ");
            
            if ($tipoRemetente === 'personal') {
                $stmt->execute([$idRemetente, $idDestinatario, $idDestinatario, $idRemetente]);
            } else {
                $stmt->execute([$idDestinatario, $idRemetente, $idRemetente, $idDestinatario]);
            }

            return $stmt->fetch() !== false;
        }

        /**
         * Helper para obter usuário do token JWT
         */
        private function getUsuarioFromToken()
        {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (strpos($authHeader, 'Bearer ') === 0) {
                require_once __DIR__ . '/../Config/jwt.config.php';
                $token = str_replace('Bearer ', '', $authHeader);
                
                try {
                    $decoded = decodificarToken($token);
                    if ($decoded) {
                        // ⭐⭐ CORREÇÃO: Converter objeto para array se necessário
                        if (is_object($decoded)) {
                            $decoded = (array)$decoded;
                        }
                        
                        return [
                            'id' => $decoded['sub'] ?? $decoded->sub ?? null,
                            'tipo' => $decoded['tipo'] ?? $decoded->tipo ?? null,
                            'email' => $decoded['email'] ?? $decoded->email ?? null
                        ];
                    }
                } catch (Exception $e) {
                    error_log("❌ Erro ao decodificar token: " . $e->getMessage());
                    return null;
                }
            }
            
            return null;
        }

        private function obterEnderecoCompletoUsuario($usuario)
        {
            $stmt = $this->db->prepare("
                SELECT eu.* 
                FROM enderecos_usuarios eu
                WHERE eu.idUsuario = ? AND eu.tipoUsuario = ?
            ");
            $stmt->execute([$usuario['id'], $usuario['tipo']]);
            $endereco = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($endereco && $endereco['cep'] && $endereco['numero']) {
                // Tentar obter coordenadas se não tiver
                if (!$endereco['latitude'] || !$endereco['longitude']) {
                    $coordenadas = $this->obterCoordenadasPorEnderecoCompleto($endereco);
                    if ($coordenadas) {
                        // Atualizar coordenadas no banco
                        $this->atualizarCoordenadasEndereco(
                            $endereco['idEndereco'], 
                            $coordenadas['latitude'], 
                            $coordenadas['longitude']
                        );
                        $endereco['latitude'] = $coordenadas['latitude'];
                        $endereco['longitude'] = $coordenadas['longitude'];
                    }
                }
            }
            
            return $endereco;
        }

        private function obterCoordenadasPorEnderecoCompleto($endereco)
        {
            try {
                $enderecoCompleto = implode(', ', array_filter([
                    $endereco['logradouro'],
                    $endereco['numero'],
                    $endereco['bairro'],
                    $endereco['cidade'],
                    $endereco['estado'],
                    'Brasil'
                ]));
                
                return $this->geocodificarEndereco($enderecoCompleto);
                
            } catch (Exception $e) {
                error_log("Erro ao geocodificar endereço completo: " . $e->getMessage());
                return null;
            }
        }

        private function atualizarCoordenadasEndereco($idEndereco, $latitude, $longitude)
        {
            try {
                $stmt = $this->db->prepare("
                    UPDATE enderecos_usuarios 
                    SET latitude = ?, longitude = ?, data_atualizacao = NOW()
                    WHERE idEndereco = ?
                ");
                $stmt->execute([$latitude, $longitude, $idEndereco]);
                return true;
            } catch (Exception $e) {
                error_log("Erro ao atualizar coordenadas: " . $e->getMessage());
                return false;
            }
        }

        private function formatarEndereco($endereco)
        {
            return implode(', ', array_filter([
                $endereco['logradouro'],
                $endereco['numero'],
                $endereco['bairro'],
                $endereco['cidade'],
                $endereco['estado']
            ]));
        }

        private function obterLocalizacaoUsuario($usuario, $filtros)
        {
            $localizacao = null;

            // 1. Tentar usar coordenadas fornecidas (geolocalização)
            if (!empty($filtros['latitude']) && !empty($filtros['longitude'])) {
                return [
                    'latitude' => (float)$filtros['latitude'],
                    'longitude' => (float)$filtros['longitude'],
                    'tipo' => 'geolocalizacao',
                    'endereco' => 'Localização atual',
                    'precisao' => 'alta'
                ];
            }

            // 2. Tentar usar endereço cadastrado do usuário COM NÚMERO
            $enderecoUsuario = $this->obterEnderecoCompletoUsuario($usuario);
            
            if ($enderecoUsuario && $enderecoUsuario['cep'] && $enderecoUsuario['numero']) {
                if ($enderecoUsuario['latitude'] && $enderecoUsuario['longitude']) {
                    return [
                        'latitude' => (float)$enderecoUsuario['latitude'],
                        'longitude' => (float)$enderecoUsuario['longitude'],
                        'tipo' => 'endereco_cadastrado',
                        'endereco' => $this->formatarEndereco($enderecoUsuario),
                        'precisao' => 'media'
                    ];
                } else {
                    // Tentar geocodificar endereço completo
                    $coordenadas = $this->obterCoordenadasPorEnderecoCompleto($enderecoUsuario);
                    if ($coordenadas) {
                        return [
                            'latitude' => $coordenadas['latitude'],
                            'longitude' => $coordenadas['longitude'],
                            'tipo' => 'endereco_geocodificado',
                            'endereco' => $this->formatarEndereco($enderecoUsuario),
                            'precisao' => 'media'
                        ];
                    }
                }
            }

            // 3. Tentar geocodificar localização textual do filtro
            if (!empty($filtros['localizacao'])) {
                $coordenadas = $this->geocodificarEndereco($filtros['localizacao']);
                if ($coordenadas) {
                    return [
                        'latitude' => $coordenadas['latitude'],
                        'longitude' => $coordenadas['longitude'],
                        'tipo' => 'endereco_digitado',
                        'endereco' => $filtros['localizacao'],
                        'precisao' => 'baixa'
                    ];
                }
            }

            // 4. Fallback: usar apenas cidade/estado do endereço cadastrado
            if ($enderecoUsuario && $enderecoUsuario['cidade'] && $enderecoUsuario['estado']) {
                $localizacaoFallback = $enderecoUsuario['cidade'] . ', ' . $enderecoUsuario['estado'];
                $coordenadas = $this->geocodificarEndereco($localizacaoFallback);
                
                if ($coordenadas) {
                    return [
                        'latitude' => $coordenadas['latitude'],
                        'longitude' => $coordenadas['longitude'],
                        'tipo' => 'endereco_aproximado',
                        'endereco' => $localizacaoFallback,
                        'precisao' => 'baixa'
                    ];
                }
            }

            return null;
        }

        /**
         * Obter endereço cadastrado do usuário
         */
        private function obterEnderecoUsuario($idUsuario, $tipoUsuario)
        {
            $stmt = $this->db->prepare("
                SELECT logradouro, cidade, estado, latitude, longitude 
                FROM enderecos_usuarios 
                WHERE idUsuario = ? AND tipoUsuario = ?
            ");
            $stmt->execute([$idUsuario, $tipoUsuario]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        /**
         * Geocodificar endereço para coordenadas
         */
        private function geocodificarEndereco($endereco)
        {
            try {
                // Usar API do Nominatim (OpenStreetMap) - gratuita
                $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($endereco) . "&limit=1&countrycodes=br";
                
                $context = stream_context_create([
                    'http' => [
                        'header' => "User-Agent: ClideFit App\r\n"
                    ]
                ]);
                
                $response = file_get_contents($url, false, $context);
                $data = json_decode($response, true);
                
                if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    return [
                        'latitude' => (float)$data[0]['lat'],
                        'longitude' => (float)$data[0]['lon']
                    ];
                }
            } catch (Exception $e) {
                error_log("Erro ao geocodificar endereço: " . $e->getMessage());
            }
            
            return null;
        }

        /**
         * Calcular distância usando fórmula de Haversine
         */
        private function calcularDistancia($lat1, $lon1, $lat2, $lon2)
        {
            $raioTerra = 6371; // Raio da Terra em km

            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);

            $a = sin($dLat/2) * sin($dLat/2) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
                sin($dLon/2) * sin($dLon/2);
            
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            
            return round($raioTerra * $c, 2);
        }

        public function obterCoordenadasPorCEP($cep)
        {
            try {
                // Primeiro buscar endereço via ViaCEP
                $viaCEPResponse = file_get_contents("https://viacep.com.br/ws/{$cep}/json/");
                $viaCEPData = json_decode($viaCEPResponse, true);
                
                if (isset($viaCEPData['erro'])) {
                    return null;
                }
                
                // Montar endereço completo para geocodificação
                $enderecoCompleto = $viaCEPData['logradouro'] . ', ' . $viaCEPData['localidade'] . ', ' . $viaCEPData['uf'] . ', Brasil';
                
                // Geocodificar para obter coordenadas
                return $this->geocodificarEndereco($enderecoCompleto);
                
            } catch (Exception $e) {
                error_log("Erro ao obter coordenadas por CEP: " . $e->getMessage());
                return null;
            }
        }

        private function aplicarFiltrosComuns(&$sql, &$conditions, &$params, $filtros, $tipoUsuario)
        {
            // Filtro por academia
            if (!empty($filtros['academia_id'])) {
                $conditions[] = ($tipoUsuario === 'aluno' ? 'a.idAcademia' : 'p.idAcademia') . " = ?";
                $params[] = $filtros['academia_id'];
            }

            // Filtro por gênero
            if (!empty($filtros['genero'])) {
                $conditions[] = ($tipoUsuario === 'aluno' ? 'a.genero' : 'p.genero') . " = ?";
                $params[] = $filtros['genero'];
            }

            // Filtro por treinos adaptados
            if ($filtros['treinosAdaptados'] !== '') {
                $conditions[] = ($tipoUsuario === 'aluno' ? 'a.treinos_adaptados' : 'p.treinos_adaptados') . " = ?";
                $params[] = $filtros['treinosAdaptados'] === 'true' ? 1 : 0;
            }

            // Filtro por modalidades
            if (!empty($filtros['modalidades'])) {
                $placeholders = implode(',', array_fill(0, count($filtros['modalidades']), '?'));
                $conditions[] = "m.nome IN ($placeholders)";
                $params = array_merge($params, $filtros['modalidades']);
            }

            // Filtro por cidade/estado (quando não há coordenadas)
            if (empty($filtros['latitude']) && empty($filtros['longitude']) && !empty($filtros['localizacao'])) {
                $conditions[] = "(e.cidade LIKE ? OR e.estado LIKE ?)";
                $params[] = '%' . $filtros['localizacao'] . '%';
                $params[] = '%' . $filtros['localizacao'] . '%';
            }
        }

        /**
         * Aplicar filtros específicos para personais
         */
        private function aplicarFiltrosPersonal(&$sql, &$conditions, &$params, $filtros)
        {
            // Filtro por tipo CREF
            if (!empty($filtros['cref_tipo'])) {
                $conditions[] = "p.cref_categoria = ?";
                $params[] = $filtros['cref_tipo'];
            }
        }

        private function aplicarFiltrosAluno(&$sql, &$conditions, &$params, $filtros)
        {
            // Filtro por idade mínima
            if (!empty($filtros['idade_min']) && is_numeric($filtros['idade_min'])) {
                $conditions[] = "a.idade >= ?";
                $params[] = (int)$filtros['idade_min'];
            }

            // ⭐⭐ CORREÇÃO: Filtro por idade máxima
            if (!empty($filtros['idade_max']) && is_numeric($filtros['idade_max'])) {
                $conditions[] = "a.idade <= ?";
                $params[] = (int)$filtros['idade_max'];
            }

            // ⭐⭐ CORREÇÃO: Filtro por meta (busca parcial)
            if (!empty($filtros['meta'])) {
                $conditions[] = "a.meta LIKE ?";
                $params[] = '%' . $filtros['meta'] . '%';
            }
        }
    }

?>