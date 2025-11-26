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

                // Query base CORRIGIDA
                $sql = "
                     SELECT DISTINCT
                        p.idPersonal,
                        p.nome,
                        p.foto_url as foto_perfil,
                        p.genero,
                        TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) as idade,
                        p.data_nascimento,
                        CONCAT(p.cref_numero, '-', p.cref_categoria, '/', p.cref_regional) as cref,
                        p.cref_categoria as cref_tipo,
                        p.treinos_adaptados,
                        p.sobre,
                        e.cidade,
                        e.estado,
                        e.latitude,
                        e.longitude,
                        ac.nome as nome_academia,
                        ac.idAcademia,
                        (SELECT COUNT(*) FROM treinos t WHERE t.idPersonal = p.idPersonal) as treinos_count,
                        p.data_cadastro
                    FROM personal p
                    LEFT JOIN enderecos_usuarios e ON p.idPersonal = e.idUsuario AND e.tipoUsuario = 'personal'
                    LEFT JOIN academias ac ON p.idAcademia = ac.idAcademia
                    WHERE p.status_conta = 'Ativa'
                    AND p.cadastro_completo = 1
                ";

                $params = [];
                $conditions = [];

                // Aplicar filtros básicos
                $this->aplicarFiltrosComuns($sql, $conditions, $params, $filtros, 'personal');
                $this->aplicarFiltrosPersonal($sql, $conditions, $params, $filtros);

                if (!empty($conditions)) {
                    $sql .= " AND " . implode(" AND ", $conditions);
                }

                $sql .= " ORDER BY p.data_cadastro DESC";

                error_log("🔍 SQL Personais Final: " . $sql);
                error_log("📊 Parâmetros Personais: " . json_encode($params));

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $personais = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Processar resultados
                $resultados = [];
                foreach ($personais as $personal) {
                    // Buscar modalidades do personal
                    $modalidades = $this->obterModalidadesPersonal($personal['idPersonal']);

                    // Verificar convite pendente
                    $convitePendente = $this->verificarConvitePendente(
                        $usuario['id'], 
                        'aluno', 
                        $personal['idPersonal'], 
                        'personal'
                    );

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
                        'treinos_count' => $personal['treinos_count'],
                        'nomeAcademia' => $personal['nome_academia'],
                        'convitePendente' => $convitePendente,
                        'latitude' => $personal['latitude'],
                        'longitude' => $personal['longitude']
                    ];
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $resultados,
                    'total' => count($resultados)
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro listarPersonais: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        private function obterModalidadesAluno($idAluno)
        {
            try {
                $stmt = $this->db->prepare("
                    SELECT m.nome 
                    FROM modalidades_aluno ma
                    JOIN modalidades m ON ma.idModalidade = m.idModalidade
                    WHERE ma.idAluno = ?
                ");
                $stmt->execute([$idAluno]);
                $modalidades = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                
                return $modalidades;
            } catch (Exception $e) {
                error_log("Erro ao buscar modalidades do aluno: " . $e->getMessage());
                return [];
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
                
                if (!$usuario || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Apenas personais podem acessar esta funcionalidade'
                    ]);
                    return;
                }

                // Obter filtros da query string
                $filtros = $this->obterFiltrosDaRequisicao();
                error_log("🎯 Filtros recebidos em listarAlunos: " . json_encode($filtros));

                // Construir query base CORRIGIDA
                $sql = "
                    SELECT DISTINCT
                        a.idAluno,
                        a.nome,
                        a.foto_url as foto_perfil,
                        a.genero,
                        TIMESTAMPDIFF(YEAR, a.data_nascimento, CURDATE()) as idade,
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
                        ac.nome as nome_academia,
                        ac.idAcademia,
                        a.data_cadastro
                    FROM alunos a
                    LEFT JOIN enderecos_usuarios e ON a.idAluno = e.idUsuario AND e.tipoUsuario = 'aluno'
                    LEFT JOIN academias ac ON a.idAcademia = ac.idAcademia
                    WHERE a.status_conta = 'Ativa' 
                    AND a.cadastro_completo = 1
                    AND a.idPersonal IS NULL
                ";

                $params = [];
                $conditions = [];

                // Aplicar filtros
                $this->aplicarFiltrosComuns($sql, $conditions, $params, $filtros, 'aluno');
                $this->aplicarFiltrosAluno($sql, $conditions, $params, $filtros);

                if (!empty($conditions)) {
                    $sql .= " AND " . implode(" AND ", $conditions);
                }

                $sql .= " ORDER BY a.data_cadastro DESC";

                error_log("🔍 SQL Alunos Final: " . $sql);
                error_log("📊 Parâmetros Alunos: " . json_encode($params));

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Processar resultados
                $resultados = [];
                foreach ($alunos as $aluno) {
                    // Buscar modalidades do aluno
                    $modalidades = $this->obterModalidadesAluno($aluno['idAluno']);

                    // 🔥 CORREÇÃO: Usar o método correto
                    $convitePendente = $this->verificarConvitePendente(
                        $usuario['id'], 
                        'personal', 
                        $aluno['idAluno'], 
                        'aluno'
                    );

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
                        'convitePendente' => $convitePendente,
                        'latitude' => $aluno['latitude'],
                        'longitude' => $aluno['longitude']
                    ];
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $resultados,
                    'total' => count($resultados)
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro listarAlunos: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
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
            try {
                $stmt = $this->db->prepare("
                    SELECT idConvite FROM convites 
                    WHERE (
                        (idPersonal = ? AND idAluno = ?) OR 
                        (idPersonal = ? AND idAluno = ?)
                    ) AND status = 'pendente'
                    LIMIT 1
                ");
                
                if ($tipoRemetente === 'personal') {
                    $stmt->execute([$idRemetente, $idDestinatario, $idDestinatario, $idRemetente]);
                } else {
                    $stmt->execute([$idDestinatario, $idRemetente, $idRemetente, $idDestinatario]);
                }

                return $stmt->fetch() !== false;
            } catch (PDOException $e) {
                error_log("Erro ao verificar convite pendente: " . $e->getMessage());
                return false;
            }
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

        public function estatisticasConvites() {
            header('Content-Type: application/json');

            try {
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                    return;
                }

                $idUsuario = $usuario['id'];
                $tipoUsuario = $usuario['tipo'];

                if ($tipoUsuario === 'personal') {
                    // Estatísticas para personal
                    $sql = "
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                            SUM(CASE WHEN status = 'aceito' THEN 1 ELSE 0 END) as aceitos,
                            SUM(CASE WHEN status = 'recusado' THEN 1 ELSE 0 END) as recusados
                        FROM convites 
                        WHERE idPersonal = ?
                    ";
                } else {
                    // Estatísticas para aluno
                    $sql = "
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                            SUM(CASE WHEN status = 'aceito' THEN 1 ELSE 0 END) as aceitos,
                            SUM(CASE WHEN status = 'recusado' THEN 1 ELSE 0 END) as recusados
                        FROM convites 
                        WHERE idAluno = ?
                    ";
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute([$idUsuario]);
                $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $estatisticas
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        private function obterModalidadesPersonal($idPersonal)
        {
            try {
                $stmt = $this->db->prepare("
                    SELECT m.nome 
                    FROM modalidades_personal mp
                    JOIN modalidades m ON mp.idModalidade = m.idModalidade
                    WHERE mp.idPersonal = ?
                ");
                $stmt->execute([$idPersonal]);
                $modalidades = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                
                return $modalidades;
            } catch (Exception $e) {
                error_log("Erro ao buscar modalidades do personal: " . $e->getMessage());
                return [];
            }
        }
    }

?>