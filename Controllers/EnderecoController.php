<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class EnderecoController
    {
        private $db;

        public function __construct()
        {
            $this->db = DB::connectDB();
        }

        /**
         * Obter endereço completo do usuário por email
         */
        public function getEnderecoPorEmail($email)
        {
            header('Content-Type: application/json');
            
            try {
                // Buscar usuário nas três tabelas
                $query = "
                    SELECT 'aluno' as tipo, idAluno as id, email FROM alunos WHERE email = ?
                    UNION 
                    SELECT 'personal' as tipo, idPersonal as id, email FROM personal WHERE email = ?
                    UNION
                    SELECT 'academia' as tipo, idAcademia as id, email FROM academias WHERE email = ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([$email, $email, $email]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$usuario) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                    return;
                }
                
                // Buscar endereço
                $tipoUsuario = $usuario['tipo'];
                $idUsuario = $usuario['id'];
                
                $stmt = $this->db->prepare("
                    SELECT * FROM enderecos_usuarios 
                    WHERE idUsuario = ? AND tipoUsuario = ?
                ");
                $stmt->execute([$idUsuario, $tipoUsuario]);
                $endereco = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($endereco) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'data' => $endereco
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Endereço não encontrado para este usuário'
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro no banco: ' . $e->getMessage()
                ]);
            }
        }

        /**
         * Atualizar endereço do usuário
         */
        public function atualizarEndereco($data)
        {
            header('Content-Type: application/json');
            
            try {
                $required = ['email', 'cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado'];
                foreach ($required as $field) {
                    if (empty($data[$field])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Campo obrigatório: {$field}"]);
                        return;
                    }
                }
                
                // Buscar usuário
                $query = "
                    SELECT 'aluno' as tipo, idAluno as id FROM alunos WHERE email = ?
                    UNION 
                    SELECT 'personal' as tipo, idPersonal as id FROM personal WHERE email = ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([$data['email'], $data['email']]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$usuario) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                    return;
                }
                
                $tipoUsuario = $usuario['tipo'];
                $idUsuario = $usuario['id'];
                
                // Verificar se endereço já existe
                $stmt = $this->db->prepare("
                    SELECT idEndereco FROM enderecos_usuarios 
                    WHERE idUsuario = ? AND tipoUsuario = ?
                ");
                $stmt->execute([$idUsuario, $tipoUsuario]);
                $enderecoExistente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($enderecoExistente) {
                    // Atualizar endereço existente
                    $stmt = $this->db->prepare("
                        UPDATE enderecos_usuarios 
                        SET cep = ?, logradouro = ?, numero = ?, complemento = ?, 
                            bairro = ?, cidade = ?, estado = ?, pais = ?,
                            data_atualizacao = NOW()
                        WHERE idEndereco = ?
                    ");
                    
                    $success = $stmt->execute([
                        $data['cep'],
                        $data['logradouro'],
                        $data['numero'],
                        $data['complemento'] ?? '',
                        $data['bairro'],
                        $data['cidade'],
                        $data['estado'],
                        $data['pais'] ?? 'Brasil',
                        $enderecoExistente['idEndereco']
                    ]);
                } else {
                    // Inserir novo endereço
                    $stmt = $this->db->prepare("
                        INSERT INTO enderecos_usuarios 
                        (idUsuario, tipoUsuario, cep, logradouro, numero, complemento, 
                        bairro, cidade, estado, pais, data_criacao)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $success = $stmt->execute([
                        $idUsuario,
                        $tipoUsuario,
                        $data['cep'],
                        $data['logradouro'],
                        $data['numero'],
                        $data['complemento'] ?? '',
                        $data['bairro'],
                        $data['cidade'],
                        $data['estado'],
                        $data['pais'] ?? 'Brasil'
                    ]);
                }
                
                if ($success) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Endereço atualizado com sucesso'
                    ]);
                } else {
                    throw new Exception('Erro ao salvar endereço');
                }
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro interno: ' . $e->getMessage()
                ]);
            }
        }
    }

?>