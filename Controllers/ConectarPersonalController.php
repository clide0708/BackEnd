<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class ConnectPersonalController
    {
        private $db;

        public function __construct()
        {
            $this->db = DB::connectDB();
        }

        /**
         * Listar personais disponíveis com filtros
         */
        public function listarPersonais()
        {
            header('Content-Type: application/json');

            try {
                // Obter filtros da query string
                $filtros = [
                    'academia_id' => $_GET['academia_id'] ?? null,
                    'cref_tipo' => $_GET['cref_tipo'] ?? null,
                    'latitude' => $_GET['latitude'] ?? null,
                    'longitude' => $_GET['longitude'] ?? null,
                    'localizacao' => $_GET['localizacao'] ?? null,
                    'modalidades' => $_GET['modalidades'] ?? [],
                    'personalizado' => $_GET['personalizado'] ?? null
                ];

                // Query base
                $sql = "
                    SELECT 
                        p.idPersonal,
                        p.nome,
                        p.foto_perfil,
                        CONCAT(p.cref_numero, '-', p.cref_categoria, '/', p.cref_regional) as cref,
                        p.cref_categoria as cref_tipo,
                        a.nome as nomeAcademia,
                        a.idAcademia,
                        COUNT(DISTINCT t.idTreino) as treinos_count,
                        GROUP_CONCAT(DISTINCT te.grupoMuscular) as modalidades_raw
                    FROM personal p
                    LEFT JOIN academias a ON p.idAcademia = a.idAcademia
                    LEFT JOIN treinos t ON p.idPersonal = t.idPersonal AND t.idAluno IS NULL
                    LEFT JOIN treino_exercicio te ON t.idTreino = te.idTreino
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

                if ($filtros['localizacao']) {
                    // Filtro simplificado por localização (implementação básica)
                    $conditions[] = "(a.endereco LIKE ? OR a.nome LIKE ?)";
                    $params[] = "%{$filtros['localizacao']}%";
                    $params[] = "%{$filtros['localizacao']}%";
                }

                // Adicionar condições à query
                if (!empty($conditions)) {
                    $sql .= " AND " . implode(" AND ", $conditions);
                }

                // Agrupar e ordenar
                $sql .= " GROUP BY p.idPersonal, a.idAcademia";
                
                // Ordenar por número de treinos (como proxy para "personalizado")
                if ($filtros['personalizado'] === 'true') {
                    $sql .= " HAVING treinos_count > 0";
                } elseif ($filtros['personalizado'] === 'false') {
                    $sql .= " HAVING treinos_count = 0";
                }

                $sql .= " ORDER BY treinos_count DESC, p.nome ASC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $personais = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Processar resultados
                $resultados = [];
                foreach ($personais as $personal) {
                    // Processar modalidades
                    $modalidades = [];
                    if ($personal['modalidades_raw']) {
                        $grupos = explode(',', $personal['modalidades_raw']);
                        $modalidades = array_unique(array_filter($grupos));
                    }

                    // Calcular distância (implementação simplificada)
                    $distancia = null;
                    if ($filtros['latitude'] && $filtros['longitude']) {
                        // Em produção, isso seria calculado com coordenadas reais das academias
                        $distancia = rand(1, 50) / 10; // Mock para demonstração
                    }

                    $resultados[] = [
                        'idPersonal' => $personal['idPersonal'],
                        'nome' => $personal['nome'],
                        'foto_perfil' => $personal['foto_perfil'],
                        'cref' => $personal['cref'],
                        'cref_tipo' => $personal['cref_tipo'],
                        'nomeAcademia' => $personal['nomeAcademia'],
                        'idAcademia' => $personal['idAcademia'],
                        'treinos_count' => (int)$personal['treinos_count'],
                        'modalidades' => $modalidades,
                        'distancia_km' => $distancia
                    ];
                }

                // Aplicar filtro de modalidades no PHP (para simplificar a query)
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
         * Listar academias para filtro
         */
        public function listarAcademias()
        {
            header('Content-Type: application/json');

            try {
                $stmt = $this->db->prepare("
                    SELECT idAcademia, nome, endereco 
                    FROM academias 
                    WHERE status_conta = 'Ativa'
                    ORDER BY nome
                ");
                $stmt->execute();
                $academias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $academias
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
         * Enviar convite (bidirecional)
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

                // Verificar se remetente existe e está ativo
                $tabelaRemetente = $tipoRemetente === 'aluno' ? 'alunos' : 'personal';
                $stmt = $this->db->prepare("
                    SELECT id{$tipoRemetente} as id FROM {$tabelaRemetente} 
                    WHERE id{$tipoRemetente} = ? AND status_conta = 'Ativa'
                ");
                $stmt->execute([$idRemetente]);
                if (!$stmt->fetch()) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Remetente não encontrado ou inativo'
                    ]);
                    return;
                }

                // Verificar se destinatário existe e está ativo
                $tabelaDestinatario = $tipoDestinatario === 'aluno' ? 'alunos' : 'personal';
                $stmt = $this->db->prepare("
                    SELECT id{$tipoDestinatario} as id FROM {$tabelaDestinatario} 
                    WHERE id{$tipoDestinatario} = ? AND status_conta = 'Ativa'
                ");
                $stmt->execute([$idDestinatario]);
                if (!$stmt->fetch()) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Destinatário não encontrado ou inativo'
                    ]);
                    return;
                }

                // Verificar se já existe convite pendente
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

                if ($stmt->fetch()) {
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

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Convite enviado com sucesso',
                        'data' => [
                            'idConvite' => $idConvite,
                            'status' => 'pendente',
                            'mensagem' => $mensagem,
                            'token' => $token
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
         * Buscar convites do usuário atual
         */
        public function meusConvites()
        {
            header('Content-Type: application/json');

            try {
                // Obter usuário do JWT (simplificado)
                $usuario = $this->getUsuarioFromToken();
                if (!$usuario) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Não autorizado'
                    ]);
                    return;
                }

                $tipoUsuario = $usuario['tipo'];
                $idUsuario = $usuario['id'];

                if ($tipoUsuario === 'aluno') {
                    $sql = "
                        SELECT c.*, p.nome as nome_personal, p.foto_perfil
                        FROM convites c
                        INNER JOIN personal p ON c.idPersonal = p.idPersonal
                        WHERE c.idAluno = ? AND c.status = 'pendente'
                    ";
                } else {
                    $sql = "
                        SELECT c.*, a.nome as nome_aluno, a.foto_perfil
                        FROM convites c
                        INNER JOIN alunos a ON c.idAluno = a.idAluno
                        WHERE c.idPersonal = ? AND c.status = 'pendente'
                    ";
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute([$idUsuario]);
                $convites = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $convites
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
         * Helper para obter usuário do token JWT (simplificado)
         */
        private function getUsuarioFromToken()
        {
            // Implementação básica - em produção usar JWT real
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (strpos($authHeader, 'Bearer ') === 0) {
                // Simulação - em produção decodificar JWT
                return [
                    'id' => 1,
                    'tipo' => 'aluno',
                    'email' => 'aluno@exemplo.com'
                ];
            }
            
            return null;
        }
    }

?>