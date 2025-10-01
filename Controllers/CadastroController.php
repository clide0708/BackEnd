<?php
    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Config/jwt.config.php';

    class CadastroController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        public function cadastrarAluno($data) {
            try {
                // Validação dos campos obrigatórios
                $camposObrigatorios = ['nome', 'cpf', 'rg', 'email', 'senha', 'numTel'];
                
                foreach ($camposObrigatorios as $campo) {
                    if (!isset($data[$campo]) || empty(trim($data[$campo]))) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Campo {$campo} é obrigatório"]);
                        return;
                    }
                }

                // Validações específicas
                if (!$this->validarEmail($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email inválido']);
                    return;
                }

                if (!$this->validarCPF($data['cpf'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CPF inválido']);
                    return;
                }

                if (!$this->validarTelefone($data['numTel'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Telefone inválido']);
                    return;
                }

                if (strlen($data['senha']) < 6) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Senha deve ter pelo menos 6 caracteres']);
                    return;
                }

                // Verifica se email já existe
                if ($this->emailExiste($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email já cadastrado']);
                    return;
                }

                // Verifica se CPF já existe
                if ($this->cpfExiste($data['cpf'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CPF já cadastrado']);
                    return;
                }

                // Hash da senha
                $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
                // Removido: $dataCadastro = date('Y-m-d H:i:s');  // Agora usamos NOW() no SQL

                // Formatar CPF e telefone
                $cpfFormatado = $this->formatarCPF($data['cpf']);
                $telefoneFormatado = $this->formatarTelefone($data['numTel']);

                // MUDANÇA: Use NOW() diretamente no SQL para data_cadastro (data atual do BD)
                $stmt = $this->db->prepare("
                    INSERT INTO alunos (nome, cpf, rg, email, senha, numTel, data_cadastro, statusPlano) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 'A verificar')
                ");

                // MUDANÇA: Execute com 6 parâmetros (sem data_cadastro)
                $success = $stmt->execute([
                    trim($data['nome']),
                    $cpfFormatado,
                    trim($data['rg']),
                    trim($data['email']),
                    $senhaHash,
                    $telefoneFormatado
                ]);

                if ($success) {
                    $alunoId = $this->db->lastInsertId();
                    $aluno = $this->buscarAlunoPorId($alunoId);
                    
                    http_response_code(201);
                    echo json_encode([
                        'success' => true, 
                        'idAluno' => $alunoId,
                        'aluno' => $aluno,
                        'message' => 'Aluno cadastrado com sucesso'
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar aluno']);
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Dados já cadastrados no sistema']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar aluno: ' . $e->getMessage()]);
                }
            }
        }

        public function cadastrarPersonal($data) {
            try {
                // Validação dos campos obrigatórios
                $camposObrigatorios = [
                    'nome', 'cpf', 'rg', 'cref_numero', 'cref_categoria', 
                    'cref_regional', 'email', 'senha', 'numTel'
                ];
                
                foreach ($camposObrigatorios as $campo) {
                    if (!isset($data[$campo]) || empty(trim($data[$campo]))) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Campo {$campo} é obrigatório"]);
                        return;
                    }
                }

                // Validações específicas
                if (!$this->validarEmail($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email inválido']);
                    return;
                }

                if (!$this->validarCPF($data['cpf'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CPF inválido']);
                    return;
                }

                if (!$this->validarTelefone($data['numTel'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Telefone inválido']);
                    return;
                }

                if (strlen($data['senha']) < 6) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Senha deve ter pelo menos 6 caracteres']);
                    return;
                }

                // Validações específicas do CREF
                if (!$this->validarCREFNumero($data['cref_numero'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Número CREF inválido (6-9 dígitos)']);
                    return;
                }

                if (!$this->validarCREFCategoria($data['cref_categoria'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Categoria CREF inválida (1 letra)']);
                    return;
                }

                if (!$this->validarCREFRegional($data['cref_regional'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Regional CREF inválida (2-5 letras)']);
                    return;
                }

                // Verifica se email já existe
                if ($this->emailExiste($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email já cadastrado']);
                    return;
                }

                // Verifica se CPF já existe
                if ($this->cpfExiste($data['cpf'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CPF já cadastrado']);
                    return;
                }

                // Verifica se CREF já existe
                if ($this->crefExiste($data['cref_numero'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CREF já cadastrado']);
                    return;
                }

                // Hash da senha
                $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
                // Removido: $dataCadastro = date('Y-m-d H:i:s');  // Agora usamos NOW() no SQL

                // Formatar dados
                $cpfFormatado = $this->formatarCPF($data['cpf']);
                $telefoneFormatado = $this->formatarTelefone($data['numTel']);
                
                // Formatar CREF corretamente
                $crefNumero = $this->formatarCREFNumero($data['cref_numero']);
                $crefCategoria = $this->formatarCREFCategoria($data['cref_categoria']);
                $crefRegional = $this->formatarCREFRegional($data['cref_regional']);

                // MUDANÇA: Use NOW() diretamente no SQL para data_cadastro (data atual do BD)
                $stmt = $this->db->prepare("
                    INSERT INTO personal 
                    (nome, cpf, rg, cref_numero, cref_categoria, cref_regional, email, senha, numTel, data_cadastro, statusPlano) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'A verificar')
                ");

                // MUDANÇA: Execute com 9 parâmetros (sem data_cadastro)
                $success = $stmt->execute([
                    trim($data['nome']),
                    $cpfFormatado,
                    trim($data['rg']),
                    $crefNumero,
                    $crefCategoria,
                    $crefRegional,
                    trim($data['email']),
                    $senhaHash,
                    $telefoneFormatado
                ]);

                if ($success) {
                    $personalId = $this->db->lastInsertId();
                    $personal = $this->buscarPersonalPorId($personalId);
                    
                    http_response_code(201);
                    echo json_encode([
                        'success' => true, 
                        'idPersonal' => $personalId,
                        'personal' => $personal,
                        'message' => 'Personal trainer cadastrado com sucesso'
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar personal trainer']);
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Dados já cadastrados no sistema']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar personal trainer: ' . $e->getMessage()]);
                }
            }
        }

        // Método para buscar aluno por ID
        private function buscarAlunoPorId($id) {
            $stmt = $this->db->prepare("SELECT idAluno, nome, cpf, rg, email, numTel, data_cadastro, statusPlano FROM alunos WHERE idAluno = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Método para buscar personal por ID
        private function buscarPersonalPorId($id) {
            $stmt = $this->db->prepare("
                SELECT idPersonal, nome, cpf, rg, 
                    cref_numero, cref_categoria, cref_regional, 
                    email, numTel, data_cadastro, statusPlano 
                FROM personal 
                WHERE idPersonal = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Validação básica de email
        private function validarEmail($email) {
            return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
        }

        // Validação básica de CPF
        private function validarCPF($cpf) {
            $cpf = preg_replace('/[^0-9]/', '', $cpf);
            return strlen($cpf) === 11;
        }

        // Validação básica de telefone
        private function validarTelefone($telefone) {
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            return strlen($telefone) >= 10 && strlen($telefone) <= 11;
        }

        // Validação básica de RG
        private function validarRG($rg) {
            $rg = preg_replace('/[^0-9]/', '', $rg);
            return strlen($rg) >= 7 && strlen($rg) <= 12;
        }

        // Função para formatar CPF antes de salvar no banco
        private function formatarCPF($cpf) {
            return preg_replace('/[^0-9]/', '', $cpf);
        }

        // Função para formatar telefone antes de salvar no banco
        private function formatarTelefone($telefone) {
            return preg_replace('/[^0-9]/', '', $telefone);
        }

        // Funções para verificar existência de email
        private function emailExiste($email) {
            $stmt = $this->db->prepare("SELECT email FROM alunos WHERE email = ? UNION SELECT email FROM personal WHERE email = ?");
            $stmt->execute([trim($email), trim($email)]);
            return $stmt->fetch() !== false;
        }

        // Função para verificar existência de CPF
        private function cpfExiste($cpf) {
            $cpfNumeros = preg_replace('/[^0-9]/', '', $cpf);
            $stmt = $this->db->prepare("SELECT cpf FROM alunos WHERE cpf = ? UNION SELECT cpf FROM personal WHERE cpf = ?");
            $stmt->execute([$cpfNumeros, $cpfNumeros]);
            return $stmt->fetch() !== false;
        }

        // Método para verificar disponibilidade de email
        public function verificarEmail($data) {
            if (!isset($data['email'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email não fornecido']);
                return;
            }

            $disponivel = !$this->emailExiste($data['email']);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'disponivel' => $disponivel,
                'email' => $data['email']
            ]);
        }

        // Método para verificar disponibilidade de CPF
        public function verificarCpf($data) {
            if (!isset($data['cpf'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'CPF não fornecido']);
                return;
            }

            $disponivel = !$this->cpfExiste($data['cpf']);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'disponivel' => $disponivel,
                'cpf' => $data['cpf']
            ]);
        }

        // Validações específicas do CREF

        // Validação do número CREF (apenas números, 6-9 dígitos)
        private function validarCREFNumero($crefNumero) {
            $crefNumero = preg_replace('/[^0-9]/', '', $crefNumero);
            return strlen($crefNumero) >= 6 && strlen($crefNumero) <= 9;
        }

        // Validação da categoria CREF (1 letra)
        private function validarCREFCategoria($categoria) {
            return preg_match('/^[A-Za-z]{1}$/', trim($categoria)) === 1;
        }

        public function verificarRg($data) {
            if (!isset($data['rg'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'RG não fornecido']);
                return;
            }
        
            $rg = preg_replace('/[^0-9A-Za-z]/', '', $data['rg']); // limpa caracteres
            $disponivel = !$this->rgExiste($rg);
        
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'disponivel' => $disponivel,
                'rg' => $rg
            ]);
        }
        
        // Função privada pra checar se o RG já existe
        private function rgExiste($rg) {
            $stmt = $this->db->prepare("SELECT rg FROM alunos WHERE rg = ? UNION SELECT rg FROM personal WHERE rg = ?");
            $stmt->execute([$rg, $rg]);
            return $stmt->fetch() !== false;
        }

        // Validação da regional CREF (2-5 letras)
        private function validarCREFRegional($regional) {
            $regional = trim($regional);
            return preg_match('/^[A-Za-z]{2,5}$/', $regional) === 1;
        }

        // Formatar número CREF - apenas números
        private function formatarCREFNumero($crefNumero) {
            return preg_replace('/[^0-9]/', '', $crefNumero);
        }

        // Formatar categoria CREF - letra maiúscula
        private function formatarCREFCategoria($categoria) {
            return strtoupper(trim($categoria));
        }

        // Formatar regional CREF - letras maiúsculas
        private function formatarCREFRegional($regional) {
            return strtoupper(trim($regional));
        }

        // Verifica se o número CREF já existe
        private function crefExiste($crefNumero) {
            $crefNumeros = $this->formatarCREFNumero($crefNumero);
            $stmt = $this->db->prepare("SELECT cref_numero FROM personal WHERE cref_numero = ?");
            $stmt->execute([$crefNumeros]);
            return $stmt->fetch() !== false;
        }
    }
?>