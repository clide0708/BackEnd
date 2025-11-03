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
                
                // ⭐⭐ CORREÇÃO: Verificação mais robusta
                if (!$usuario || !isset($usuario['tipo']) || !in_array($usuario['tipo'], ['aluno', 'personal'])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Apenas alunos e personais podem acessar esta funcionalidade'
                    ]);
                    return;
                }

                error_log("🎯 listarPersonais chamado por: " . $usuario['tipo'] . " ID: " . $usuario['id']);

                // Query SIMPLIFICADA para testar
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
                        e.cidade,
                        e.estado
                    FROM personal p
                    LEFT JOIN enderecos_usuarios e ON p.idPersonal = e.idUsuario AND e.tipoUsuario = 'personal'
                    WHERE p.status_conta = 'Ativa'
                    ORDER BY p.nome ASC
                ";

                error_log("🔍 SQL Personais Simplificado: " . $sql);

                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $personais = $stmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("✅ Personais encontrados no BD: " . count($personais));
                
                if (count($personais) > 0) {
                    error_log("📝 Primeiro personal: " . json_encode($personais[0]));
                } else {
                    error_log("📝 Nenhum personal encontrado");
                }

                // Processar resultados
                $resultados = [];
                foreach ($personais as $personal) {
                    // Verificar convite pendente de forma segura
                    $convitePendente = false;
                    try {
                        $convitePendente = $this->verificarConvitePendente(
                            $usuario['id'], 
                            'aluno', 
                            $personal['idPersonal'], 
                            'personal'
                        );
                    } catch (Exception $e) {
                        error_log("⚠️ Erro ao verificar convite: " . $e->getMessage());
                    }

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
                        'modalidades' => [], // Simplificado por enquanto
                        'distancia_km' => null, // Simplificado por enquanto
                        'convitePendente' => $convitePendente
                    ];
                }

                error_log("🎯 Personais resultados finais: " . count($resultados));

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $resultados,
                    'total' => count($resultados),
                    'debug' => [
                        'query_simplificada' => true,
                        'personais_encontrados' => count($personais),
                        'resultados_processados' => count($resultados)
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
                // Obter usuário autenticado
                $usuario = $this->getUsuarioFromToken();
                
                // ⭐⭐ CORREÇÃO: Verificação mais robusta
                if (!$usuario || !isset($usuario['tipo']) || !in_array($usuario['tipo'], ['aluno', 'personal'])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Apenas alunos e personais podem acessar esta funcionalidade'
                    ]);
                    return;
                }

                error_log("🎯 listarAlunos chamado por: " . $usuario['tipo'] . " ID: " . $usuario['id']);

                // Query SIMPLIFICADA para testar
                $sql = "
                    SELECT DISTINCT
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
                        a.idPersonal,
                        a.status_conta,
                        e.cidade,
                        e.estado
                    FROM alunos a
                    LEFT JOIN enderecos_usuarios e ON a.idAluno = e.idUsuario AND e.tipoUsuario = 'aluno'
                    WHERE a.status_conta = 'Ativa' 
                    AND a.idPersonal IS NULL
                    ORDER BY a.data_cadastro DESC
                ";

                error_log("🔍 SQL Alunos Simplificado: " . $sql);

                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("✅ Alunos encontrados no BD: " . count($alunos));
                
                // Log dos primeiros alunos para debug
                if (count($alunos) > 0) {
                    error_log("📝 Primeiro aluno: " . json_encode($alunos[0]));
                } else {
                    error_log("📝 Nenhum aluno encontrado com os critérios");
                }

                // Processar resultados
                $resultados = [];
                foreach ($alunos as $aluno) {
                    // Verificar convite pendente de forma segura
                    $convitePendente = false;
                    try {
                        $convitePendente = $this->verificarConvitePendente(
                            $usuario['id'], 
                            'personal', 
                            $aluno['idAluno'], 
                            'aluno'
                        );
                    } catch (Exception $e) {
                        error_log("⚠️ Erro ao verificar convite: " . $e->getMessage());
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
                        'modalidades' => [], // Simplificado por enquanto
                        'distancia_km' => null, // Simplificado por enquanto
                        'convitePendente' => $convitePendente
                    ];
                }

                error_log("🎯 Resultados finais para retorno: " . count($resultados));

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $resultados,
                    'total' => count($resultados),
                    'debug' => [
                        'query_simplificada' => true,
                        'alunos_encontrados' => count($alunos),
                        'resultados_processados' => count($resultados),
                        'usuario_requisitante' => [
                            'id' => $usuario['id'],
                            'tipo' => $usuario['tipo']
                        ]
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
    }

?>