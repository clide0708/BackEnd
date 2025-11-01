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
                if (!$usuario || $usuario['tipo'] !== 'aluno') {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Apenas alunos podem buscar personais'
                    ]);
                    return;
                }

                // Obter filtros da query string
                $filtros = [
                    'academia_id' => $_GET['academia_id'] ?? null,
                    'cref_tipo' => $_GET['cref_tipo'] ?? null,
                    'genero' => $_GET['genero'] ?? null,
                    'latitude' => $_GET['latitude'] ?? null,
                    'longitude' => $_GET['longitude'] ?? null,
                    'localizacao' => $_GET['localizacao'] ?? null,
                    'modalidades' => $_GET['modalidades'] ?? [],
                    'treinosAdaptados' => $_GET['treinosAdaptados'] ?? null,
                    'raio_km' => $_GET['raio_km'] ?? 50
                ];

                // Query base com JOINs para modalidades e endereço
                $sql = "
                    SELECT 
                        p.idPersonal,
                        p.nome,
                        p.foto_perfil,
                        p.genero,
                        p.idade,
                        CONCAT(p.cref_numero, '-', p.cref_categoria, '/', p.cref_regional) as cref,
                        p.cref_categoria as cref_tipo,
                        p.treinos_adaptados,
                        p.sobre,
                        a.nome as nomeAcademia,
                        a.idAcademia,
                        e.cidade,
                        e.estado,
                        e.latitude,
                        e.longitude,
                        GROUP_CONCAT(DISTINCT m.nome) as modalidades,
                        COUNT(DISTINCT t.idTreino) as treinos_count,
                        COUNT(DISTINCT c.idConvite) as convites_count
                    FROM personal p
                    LEFT JOIN academias a ON p.idAcademia = a.idAcademia
                    LEFT JOIN enderecos_usuarios e ON p.idPersonal = e.idUsuario AND e.tipoUsuario = 'personal'
                    LEFT JOIN modalidades_personal mp ON p.idPersonal = mp.idPersonal
                    LEFT JOIN modalidades m ON mp.idModalidade = m.idModalidade
                    LEFT JOIN treinos t ON p.idPersonal = t.idPersonal AND t.idAluno IS NULL
                    LEFT JOIN convites c ON p.idPersonal = c.idPersonal AND c.status = 'pendente' AND c.tipo_destinatario = 'personal'
                    WHERE p.status_conta = 'Ativa'
                ";

                $params = [];
                $conditions = [];

                // Aplicar filtros
                if ($filtros['academia_id']) {
                    $conditions[] = "p.idAcademia = ?";
                    $params[] = $filtros['academia_id'];
                }

                if ($filtros['cref_tipo']) {
                    $conditions[] = "p.cref_categoria = ?";
                    $params[] = $filtros['cref_tipo'];
                }

                if ($filtros['genero']) {
                    $conditions[] = "p.genero = ?";
                    $params[] = $filtros['genero'];
                }

                if ($filtros['treinosAdaptados'] !== null) {
                    $conditions[] = "p.treinos_adaptados = ?";
                    $params[] = $filtros['treinosAdaptados'] === 'true' ? 1 : 0;
                }

                // Filtro por localização
                if ($filtros['localizacao']) {
                    $conditions[] = "(e.cidade LIKE ? OR e.estado LIKE ? OR a.endereco LIKE ?)";
                    $localizacaoLike = "%{$filtros['localizacao']}%";
                    $params[] = $localizacaoLike;
                    $params[] = $localizacaoLike;
                    $params[] = $localizacaoLike;
                }

                // Adicionar condições à query
                if (!empty($conditions)) {
                    $sql .= " AND " . implode(" AND ", $conditions);
                }

                // Agrupar e ordenar
                $sql .= " GROUP BY p.idPersonal, a.idAcademia, e.idEndereco";
                $sql .= " ORDER BY p.treinos_adaptados DESC, treinos_count DESC, p.nome ASC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $personais = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Processar resultados e calcular distâncias
                $resultados = [];
                foreach ($personais as $personal) {
                    // Processar modalidades
                    $modalidadesList = [];
                    if ($personal['modalidades']) {
                        $modalidadesList = explode(',', $personal['modalidades']);
                    }

                    // Calcular distância se tiver coordenadas
                    $distancia = null;
                    if ($filtros['latitude'] && $filtros['longitude'] && $personal['latitude'] && $personal['longitude']) {
                        $distancia = $this->calcularDistancia(
                            $filtros['latitude'],
                            $filtros['longitude'],
                            $personal['latitude'],
                            $personal['longitude']
                        );
                        
                        // Filtrar por raio se especificado
                        if ($distancia > $filtros['raio_km']) {
                            continue;
                        }
                    }

                    // Verificar se já existe convite pendente do usuário atual
                    $convitePendente = $this->verificarConvitePendente($usuario['id'], 'aluno', $personal['idPersonal'], 'personal');

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
                        'nomeAcademia' => $personal['nomeAcademia'],
                        'idAcademia' => $personal['idAcademia'],
                        'cidade' => $personal['cidade'],
                        'estado' => $personal['estado'],
                        'treinos_count' => (int)$personal['treinos_count'],
                        'modalidades' => $modalidadesList,
                        'distancia_km' => $distancia,
                        'convitePendente' => $convitePendente
                    ];
                }

                // Aplicar filtro de modalidades
                if (!empty($filtros['modalidades'])) {
                    $modalidadesFiltro = is_array($filtros['modalidades']) ? 
                        $filtros['modalidades'] : [$filtros['modalidades']];
                    
                    $resultados = array_filter($resultados, function($personal) use ($modalidadesFiltro) {
                        if (empty($personal['modalidades'])) return false;
                        
                        foreach ($modalidadesFiltro as $modalidade) {
                            if (in_array($modalidade, $personal['modalidades'])) {
                                return true;
                            }
                        }
                        return false;
                    });
                }

                // Ordenar por distância se disponível
                if ($filtros['latitude'] && $filtros['longitude']) {
                    usort($resultados, function($a, $b) {
                        if ($a['distancia_km'] === null) return 1;
                        if ($b['distancia_km'] === null) return -1;
                        return $a['distancia_km'] <=> $b['distancia_km'];
                    });
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => array_values($resultados),
                    'total' => count($resultados)
                ]);

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

        /**
         * Listar alunos disponíveis para personais
         */
        public function listarAlunos()
        {
            header('Content-Type: application/json');

            try {
                // Obter usuário autenticado
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario || $usuario['tipo'] !== 'personal') {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Apenas personais podem buscar alunos'
                    ]);
                    return;
                }

                // Obter filtros da query string
                $filtros = [
                    'academia_id' => $_GET['academia_id'] ?? null,
                    'genero' => $_GET['genero'] ?? null,
                    'idade_min' => $_GET['idade_min'] ?? null,
                    'idade_max' => $_GET['idade_max'] ?? null,
                    'meta' => $_GET['meta'] ?? null,
                    'latitude' => $_GET['latitude'] ?? null,
                    'longitude' => $_GET['longitude'] ?? null,
                    'localizacao' => $_GET['localizacao'] ?? null,
                    'modalidades' => $_GET['modalidades'] ?? [],
                    'treinosAdaptados' => $_GET['treinosAdaptados'] ?? null,
                    'raio_km' => $_GET['raio_km'] ?? 50
                ];

                // Query base
                $sql = "
                    SELECT 
                        a.idAluno,
                        a.nome,
                        a.foto_perfil,
                        a.genero,
                        a.idade,
                        a.altura,
                        a.peso,
                        a.meta,
                        a.treinos_adaptados,
                        a.treinoTipo,
                        e.cidade,
                        e.estado,
                        e.latitude,
                        e.longitude,
                        GROUP_CONCAT(DISTINCT m.nome) as modalidades,
                        COUNT(DISTINCT c.idConvite) as convites_count
                    FROM alunos a
                    LEFT JOIN enderecos_usuarios e ON a.idAluno = e.idUsuario AND e.tipoUsuario = 'aluno'
                    LEFT JOIN modalidades_aluno ma ON a.idAluno = ma.idAluno
                    LEFT JOIN modalidades m ON ma.idModalidade = m.idModalidade
                    LEFT JOIN convites c ON a.idAluno = c.idAluno AND c.status = 'pendente' AND c.tipo_destinatario = 'aluno'
                    WHERE a.status_conta = 'Ativa' 
                    AND a.idPersonal IS NULL
                ";

                $params = [];
                $conditions = [];

                // Aplicar filtros
                if ($filtros['academia_id']) {
                    // Buscar alunos por academia através do endereço
                    $conditions[] = "EXISTS (
                        SELECT 1 FROM academias ac 
                        WHERE ac.idAcademia = ? 
                        AND (ac.endereco LIKE CONCAT('%', e.cidade, '%') OR ac.endereco LIKE CONCAT('%', e.estado, '%'))
                    )";
                    $params[] = $filtros['academia_id'];
                }

                if ($filtros['genero']) {
                    $conditions[] = "a.genero = ?";
                    $params[] = $filtros['genero'];
                }

                if ($filtros['idade_min']) {
                    $conditions[] = "a.idade >= ?";
                    $params[] = $filtros['idade_min'];
                }

                if ($filtros['idade_max']) {
                    $conditions[] = "a.idade <= ?";
                    $params[] = $filtros['idade_max'];
                }

                if ($filtros['meta']) {
                    $conditions[] = "a.meta LIKE ?";
                    $params[] = "%{$filtros['meta']}%";
                }

                if ($filtros['treinosAdaptados'] !== null) {
                    $conditions[] = "a.treinos_adaptados = ?";
                    $params[] = $filtros['treinosAdaptados'] === 'true' ? 1 : 0;
                }

                // Filtro por localização
                if ($filtros['localizacao']) {
                    $conditions[] = "(e.cidade LIKE ? OR e.estado LIKE ?)";
                    $localizacaoLike = "%{$filtros['localizacao']}%";
                    $params[] = $localizacaoLike;
                    $params[] = $localizacaoLike;
                }

                // Adicionar condições
                if (!empty($conditions)) {
                    $sql .= " AND " . implode(" AND ", $conditions);
                }

                // Agrupar e ordenar
                $sql .= " GROUP BY a.idAluno, e.idEndereco";
                $sql .= " ORDER BY a.data_cadastro DESC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Processar resultados
                $resultados = [];
                foreach ($alunos as $aluno) {
                    // Processar modalidades
                    $modalidadesList = [];
                    if ($aluno['modalidades']) {
                        $modalidadesList = explode(',', $aluno['modalidades']);
                    }

                    // Calcular distância
                    $distancia = null;
                    if ($filtros['latitude'] && $filtros['longitude'] && $aluno['latitude'] && $aluno['longitude']) {
                        $distancia = $this->calcularDistancia(
                            $filtros['latitude'],
                            $filtros['longitude'],
                            $aluno['latitude'],
                            $aluno['longitude']
                        );
                        
                        if ($distancia > $filtros['raio_km']) {
                            continue;
                        }
                    }

                    // Verificar convite pendente
                    $convitePendente = $this->verificarConvitePendente($usuario['id'], 'personal', $aluno['idAluno'], 'aluno');

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
                        'modalidades' => $modalidadesList,
                        'distancia_km' => $distancia,
                        'convitePendente' => $convitePendente
                    ];
                }

                // Aplicar filtro de modalidades
                if (!empty($filtros['modalidades'])) {
                    $modalidadesFiltro = is_array($filtros['modalidades']) ? 
                        $filtros['modalidades'] : [$filtros['modalidades']];
                    
                    $resultados = array_filter($resultados, function($aluno) use ($modalidadesFiltro) {
                        if (empty($aluno['modalidades'])) return false;
                        foreach ($modalidadesFiltro as $modalidade) {
                            if (in_array($modalidade, $aluno['modalidades'])) {
                                return true;
                            }
                        }
                        return false;
                    });
                }

                // Ordenar por distância
                if ($filtros['latitude'] && $filtros['longitude']) {
                    usort($resultados, function($a, $b) {
                        if ($a['distancia_km'] === null) return 1;
                        if ($b['distancia_km'] === null) return -1;
                        return $a['distancia_km'] <=> $b['distancia_km'];
                    });
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => array_values($resultados),
                    'total' => count($resultados)
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
         * Helper para calcular distância entre coordenadas
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
                        return [
                            'id' => $decoded['sub'],
                            'tipo' => $decoded['tipo'],
                            'email' => $decoded['email']
                        ];
                    }
                } catch (Exception $e) {
                    return null;
                }
            }
            
            return null;
        }
    }

?>