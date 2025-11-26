<?php

    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../Config/jwt.config.php';

    class CadastroController{
        private $db;

        public function __construct(){
            $this->db = DB::connectDB();
        }

        // Método auxiliar para buscar um plano pelo nome e tipo de usuário
        private function buscarPlanoId($nomePlano, $tipoUsuario){
            $stmt = $this->db->prepare("SELECT idPlano FROM planos WHERE nome = ? AND tipo_usuario = ?");
            $stmt->execute([$nomePlano, $tipoUsuario]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['idPlano'] : null;
        }

        public function cadastrarAluno($data){
            try {
                error_log("📥 Dados recebidos no cadastro aluno: " . json_encode($data));
                
                // Validação dos campos obrigatórios
                $camposObrigatorios = ['nome', 'cpf', 'rg', 'email', 'senha', 'numTel'];
                foreach ($camposObrigatorios as $campo) {
                    if (!isset($data[$campo]) || empty(trim($data[$campo]))) {
                        error_log("❌ Campo obrigatório faltando: " . $campo);
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

                // Verifica duplicidade
                if ($this->emailExiste($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email já cadastrado']);
                    return;
                }
                if ($this->cpfExiste($data['cpf'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CPF já cadastrado']);
                    return;
                }

                // Hash da senha
                $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
                $cpfFormatado = $this->formatarCPF($data['cpf']);
                $telefoneFormatado = $this->formatarTelefone($data['numTel']);

                // plano padrão (id 1) – mas pode buscar por nome se quiser
                $idPlanoBasico = 1;

                $stmt = $this->db->prepare("
                    INSERT INTO alunos (nome, cpf, rg, email, senha, numTel, data_cadastro, idPlano, status_conta) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'Ativa')
                ");

                $success = $stmt->execute([
                    trim($data['nome']),
                    $cpfFormatado,
                    trim($data['rg']),
                    trim($data['email']),
                    $senhaHash,
                    $telefoneFormatado,
                    $idPlanoBasico
                ]);

                if ($success) {
                    $alunoId = $this->db->lastInsertId();
                    $aluno = $this->buscarAlunoPorId($alunoId);

                    
                    if (isset($data['idAcademia']) && !empty($data['idAcademia'])) {
                        $this->enviarSolicitacaoVinculacaoAcademia($alunoId, 'aluno', $data['idAcademia']);
                    }

                    // Inserir endereço do aluno
                    if (isset($data['cep']) && !empty($data['cep'])) {
                        $errosEndereco = $this->validarDadosEndereco($data);
                        if (!empty($errosEndereco)) {
                            // Se endereço inválido, apenas log o erro mas não impede o cadastro
                            error_log("⚠️ Endereço inválido, mas cadastro realizado: " . implode(', ', $errosEndereco));
                        } else {
                            $this->cadastrarEnderecoUsuario($alunoId, 'aluno', $data);
                        }
                    }

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'idAluno' => $alunoId,
                        'aluno' => $aluno,
                        'message' => 'Aluno cadastrado com sucesso no plano básico.'
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

        public function cadastrarPersonal($data){
            try {
                // Validação dos campos obrigatórios
                $camposObrigatorios = [
                    'nome',
                    'cpf',
                    'rg',
                    'cref_numero',
                    'cref_categoria',
                    'cref_regional',
                    'email',
                    'senha',
                    'numTel'
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

                // Formatar dados
                $cpfFormatado = $this->formatarCPF($data['cpf']);
                $telefoneFormatado = $this->formatarTelefone($data['numTel']);

                // Formatar CREF corretamente
                $crefNumero = $this->formatarCREFNumero($data['cref_numero']);
                $crefCategoria = $this->formatarCREFCategoria($data['cref_categoria']);
                $crefRegional = $this->formatarCREFRegional($data['cref_regional']);

                // Buscar ID do plano 'Personal Básico'
                $idPlanoBasico = $this->buscarPlanoId('Personal Básico', 'personal');
                if (!$idPlanoBasico) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Plano padrão para personal não encontrado.']);
                    return;
                }

                $stmt = $this->db->prepare("
                    INSERT INTO personal 
                    (nome, cpf, rg, cref_numero, cref_categoria, cref_regional, email, senha, numTel, data_cadastro, idPlano, status_conta, idAcademia) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'Ativa', ?)
                ");

                $success = $stmt->execute([
                    trim($data['nome']),
                    $cpfFormatado,
                    trim($data['rg']),
                    $crefNumero,
                    $crefCategoria,
                    $crefRegional,
                    trim($data['email']),
                    $senhaHash,
                    $telefoneFormatado,
                    $idPlanoBasico,
                    $data['idAcademia'] ?? null
                ]);

                if ($success) {
                    $personalId = $this->db->lastInsertId();
                    
                    if (isset($data['idAcademia']) && !empty($data['idAcademia'])) {
                        $this->enviarSolicitacaoVinculacaoAcademia($personalId, 'personal', $data['idAcademia']);
                    }
                    
                    // Criar assinatura para o plano básico
                    $this->criarAssinatura($personalId, 'personal', $idPlanoBasico, 'ativa');

                    $personal = $this->buscarPersonalPorId($personalId);

                    // Inserir endereço do personal
                    if (isset($data['cep']) && !empty($data['cep'])) {
                        $errosEndereco = $this->validarDadosEndereco($data);
                        if (!empty($errosEndereco)) {
                            error_log("⚠️ Endereço inválido para personal, mas cadastro realizado: " . implode(', ', $errosEndereco));
                        } else {
                            $this->cadastrarEnderecoUsuario($personalId, 'personal', $data);
                        }
                    }

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'idPersonal' => $personalId,
                        'personal' => $personal,
                        'message' => 'Personal trainer cadastrado com sucesso e plano básico atribuído.'
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

        public function cadastrarAcademia($data){
            try {
                error_log("📥 Dados recebidos no cadastro academia: " . json_encode($data));

                $camposObrigatorios = ['nome_fantasia', 'razao_social', 'cnpj', 'email', 'senha'];
                foreach ($camposObrigatorios as $campo) {
                    if (!isset($data[$campo]) || empty(trim($data[$campo]))) {
                        error_log("❌ Campo obrigatório faltando ou vazio: " . $campo);
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Campo {$campo} é obrigatório"]);
                        return;
                    }
                }
                if (!$this->validarEmail($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email inválido']);
                    return;
                }

                if (!$this->validarCNPJ($data['cnpj'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CNPJ inválido']);
                    return;
                }

                if (strlen($data['senha']) < 6) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Senha deve ter pelo menos 6 caracteres']);
                    return;
                }

                if ($this->emailExiste($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email já cadastrado']);
                    return;
                }

                if ($this->cnpjExiste($data['cnpj'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'CNPJ já cadastrado']);
                    return;
                }

                $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
                $cnpjFormatado = $this->formatarCNPJ($data['cnpj']);

                // Buscar ID do plano 'Academia Premium'
                $idPlanoAcademia = $this->buscarPlanoId('Academia Premium', 'academia');
                if (!$idPlanoAcademia) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Plano padrão para academia não encontrado.']);
                    return;
                }
                
                error_log("🎯 Inserindo academia no banco...");
                
                $stmt = $this->db->prepare("
                    INSERT INTO academias (
                        nome, nome_fantasia, razao_social, cnpj, email, senha, telefone, 
                        tamanho_estrutura, capacidade_maxima, ano_fundacao, 
                        estacionamento, vestiario, ar_condicionado, wifi, 
                        totem_de_carregamento_usb, area_descanso, avaliacao_fisica, 
                        data_cadastro, idPlano, status_conta
                    ) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'Ativa')
                ");

                $success = $stmt->execute([
                    trim($data['nome_fantasia']),
                    trim($data['nome_fantasia']),
                    trim($data['razao_social']),
                    $cnpjFormatado,
                    trim($data['email']),
                    $senhaHash,
                    isset($data['telefone']) ? $this->formatarTelefone($data['telefone']) : null,
                    $data['tamanho_estrutura'] ?? null,
                    $data['capacidade_maxima'] ?? null,
                    $data['ano_fundacao'] ?? null,
                    $data['estacionamento'] ?? 0,
                    $data['vestiario'] ?? 0,
                    $data['ar_condicionado'] ?? 0,
                    $data['wifi'] ?? 0,
                    $data['totem_de_carregamento_usb'] ?? 0,
                    $data['area_descanso'] ?? 0,
                    $data['avaliacao_fisica'] ?? 0,
                    $idPlanoAcademia
                ]);

                if ($success) {
                    $academiaId = $this->db->lastInsertId();
                    error_log("✅ Academia cadastrada com ID: " . $academiaId);
                    
                    // 🔥 CORREÇÃO CRÍTICA: Salvar endereço para academia
                    if (isset($data['cep']) && !empty(trim($data['cep']))) {
                        error_log("💾 Salvando endereço para academia ID: " . $academiaId);
                        $errosEndereco = $this->validarDadosEndereco($data);
                        if (empty($errosEndereco)) {
                            // 🔥 CORREÇÃO: Usar a função correta para salvar endereço de academia
                            $this->cadastrarEnderecoAcademia($academiaId, $data);
                            error_log("✅ Endereço salvo com sucesso");
                        } else {
                            error_log("⚠️ Endereço inválido: " . implode(', ', $errosEndereco));
                        }
                    }
                    
                    // 🔥 CORREÇÃO: Salvar modalidades se existirem
                    if (isset($data['modalidades']) && is_array($data['modalidades']) && !empty($data['modalidades'])) {
                        error_log("💾 Salvando modalidades para academia ID: " . $academiaId);
                        $this->vincularModalidadesAcademia($academiaId, $data['modalidades']);
                        error_log("✅ Modalidades salvas: " . count($data['modalidades']));
                    }
                    
                    // Salvar horários se existirem
                    if (isset($data['horarios']) && is_array($data['horarios'])) {
                        error_log("💾 Salvando horários para academia ID: " . $academiaId);
                        $this->salvarHorariosAcademia($academiaId, $data['horarios']);
                        error_log("✅ Horários salvos");
                    }
                    
                    // Criar assinatura para o plano
                    $this->criarAssinatura($academiaId, 'academia', $idPlanoAcademia, 'ativa');

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'idAcademia' => $academiaId,
                        'message' => 'Academia cadastrada com sucesso e plano atribuído.'
                    ]);
                } else {
                    error_log("❌ Erro ao executar INSERT na tabela academias");
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar academia no banco de dados']);
                }
            } catch (PDOException $e) {
                error_log("❌ PDOException no cadastro academia: " . $e->getMessage());
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Dados já cadastrados no sistema']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar academia: ' . $e->getMessage()]);
                }
            }
        }

        private function cadastrarEnderecoAcademia($idAcademia, $data) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO enderecos_usuarios (
                        idUsuario, tipoUsuario, cep, logradouro, numero, complemento,
                        bairro, cidade, estado, pais, data_criacao
                    ) VALUES (?, 'academia', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $success = $stmt->execute([
                    $idAcademia,
                    $data['cep'] ?? null,
                    $data['logradouro'] ?? null,
                    $data['numero'] ?? null,
                    $data['complemento'] ?? null,
                    $data['bairro'] ?? null,
                    $data['cidade'] ?? null,
                    $data['estado'] ?? null,
                    $data['pais'] ?? 'Brasil'
                ]);

                return $success;
            } catch (PDOException $e) {
                error_log("Erro ao cadastrar endereço da academia: " . $e->getMessage());
                return false;
            }
        }

        private function calcularIdade($dataNascimento) {
            $hoje = new DateTime();
            $nascimento = new DateTime($dataNascimento);
            $idade = $hoje->diff($nascimento);
            return $idade->y;
        }

        private function salvarModalidadesUsuario($idUsuario, $tipoUsuario, $modalidades) {
            if (empty($modalidades)) return;
            
            $tabela = $tipoUsuario === 'aluno' ? 'modalidades_aluno' : 'modalidades_personal';
            $campoId = $tipoUsuario === 'aluno' ? 'idAluno' : 'idPersonal';
            
            foreach ($modalidades as $idModalidade) {
                $stmt = $this->db->prepare("INSERT INTO {$tabela} ({$campoId}, idModalidade) VALUES (?, ?)");
                $stmt->execute([$idUsuario, $idModalidade]);
            }
        }

        // Método para buscar aluno por ID
        private function buscarAlunoPorId($id){
            $stmt = $this->db->prepare("SELECT idAluno, nome, cpf, rg, email, numTel, data_cadastro, idPlano, status_conta FROM alunos WHERE idAluno = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }


        // Método para buscar personal por ID
        private function buscarPersonalPorId($id){
            $stmt = $this->db->prepare("
                    SELECT idPersonal, nome, cpf, rg, 
                        cref_numero, cref_categoria, cref_regional, 
                        email, numTel, data_cadastro, idPlano, status_conta 
                    FROM personal 
                    WHERE idPersonal = ?
                ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Validação básica de email
        private function validarEmail($email){
            return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
        }

        // Validação básica de CPF
        private function validarCPF($cpf){
            $cpf = preg_replace('/[^0-9]/', '', $cpf);
            return strlen($cpf) === 11;
        }

        // Validação básica de telefone
        private function validarTelefone($telefone){
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            return strlen($telefone) >= 10 && strlen($telefone) <= 11;
        }

        // Validação básica de RG
        private function validarRG($rg){
            $rg = preg_replace('/[^0-9]/', '', $rg);
            return strlen($rg) >= 7 && strlen($rg) <= 12;
        }

        // Validação de CNPJ
        private function validarCNPJ($cnpj){
            $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
            return strlen($cnpj) === 14;
        }

        // Função para formatar CPF antes de salvar no banco
        private function formatarCPF($cpf){
            return preg_replace('/[^0-9]/', '', $cpf);
        }

        // Função para formatar telefone antes de salvar no banco
        private function formatarTelefone($telefone){
            return preg_replace('/[^0-9]/', '', $telefone);
        }

        // Função para formatar CNPJ antes de salvar no banco
        private function formatarCNPJ($cnpj){
            return preg_replace('/[^0-9]/', '', $cnpj);
        }

        // Funções para verificar existência de email
        private function emailExiste($email){
            $stmt = $this->db->prepare("SELECT email FROM alunos WHERE email = ? UNION SELECT email FROM personal WHERE email = ? UNION SELECT email FROM academias WHERE email = ?");
            $stmt->execute([trim($email), trim($email), trim($email)]);
            return $stmt->fetch() !== false;
        }

        // Função para verificar existência de CPF
        private function cpfExiste($cpf){
            $cpfNumeros = preg_replace('/[^0-9]/', '', $cpf);
            $stmt = $this->db->prepare("SELECT cpf FROM alunos WHERE cpf = ? UNION SELECT cpf FROM personal WHERE cpf = ?");
            $stmt->execute([$cpfNumeros, $cpfNumeros]);
            return $stmt->fetch() !== false;
        }

        // Função para verificar existência de CNPJ
        private function cnpjExiste($cnpj){
            $cnpjNumeros = preg_replace('/[^0-9]/', '', $cnpj);
            $stmt = $this->db->prepare("SELECT cnpj FROM academias WHERE cnpj = ?");
            $stmt->execute([$cnpjNumeros]);
            return $stmt->fetch() !== false;
        }

        // Método para verificar disponibilidade de email
        public function verificarEmail($data){
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
        public function verificarCpf($data){
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

        // Método para verificar disponibilidade de CNPJ
        public function verificarCnpj($data){
            if (!isset($data['cnpj'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'CNPJ não fornecido']);
                return;
            }

            $disponivel = !$this->cnpjExiste($data['cnpj']);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'disponivel' => $disponivel,
                'cnpj' => $data['cnpj']
            ]);
        }

        // Validações específicas do CREF

        // Validação do número CREF (apenas números, 6-9 dígitos)
        private function validarCREFNumero($crefNumero){
            $crefNumero = preg_replace('/[^0-9]/', '', $crefNumero);
            return strlen($crefNumero) >= 6 && strlen($crefNumero) <= 9;
        }

        // Validação da categoria CREF (1 letra)
        private function validarCREFCategoria($categoria){
            return preg_match('/^[A-Za-z]{1}$/', trim($categoria)) === 1;
        }

        // Validação da regional CREF (2-5 letras)
        private function validarCREFRegional($regional){
            return preg_match('/^[A-Za-z]{2,5}$/', trim($regional)) === 1;
        }

        // Função para verificar existência de CREF
        private function crefExiste($crefNumero){
            $crefNumeros = preg_replace('/[^0-9]/', '', $crefNumero);
            $stmt = $this->db->prepare("SELECT cref_numero FROM personal WHERE cref_numero = ?");
            $stmt->execute([$crefNumeros]);
            return $stmt->fetch() !== false;
        }

        public function verificarRg($data){
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
        private function rgExiste($rg){
            $stmt = $this->db->prepare("SELECT rg FROM alunos WHERE rg = ? UNION SELECT rg FROM personal WHERE rg = ?");
            $stmt->execute([$rg, $rg]);
            return $stmt->fetch() !== false;
        }

        // Método para criar uma assinatura inicial
        private function criarAssinatura($idUsuario, $tipoUsuario, $idPlano, $status){
            $stmt = $this->db->prepare("
                    INSERT INTO assinaturas (idUsuario, tipo_usuario, idPlano, data_inicio, status)
                    VALUES (?, ?, ?, NOW(), ?)
                ");
            $stmt->execute([$idUsuario, $tipoUsuario, $idPlano, $status]);
        }
        // funções de formatação do CREF
        private function formatarCREFNumero($crefNumero){
            return preg_replace('/[^0-9]/', '', $crefNumero);
        }

        private function formatarCREFCategoria($categoria){
            return strtoupper(trim($categoria)); // deixa maiúscula e limpa espaços
        }

        private function formatarCREFRegional($regional){
            return strtoupper(trim($regional)); // deixa maiúscula e limpa espaços
        }

        private function academiaExiste($idAcademia){
            if (!$idAcademia) return true; // Academia é opcional
            
            $stmt = $this->db->prepare("SELECT idAcademia FROM academias WHERE idAcademia = ? AND status_conta = 'Ativa'");
            $stmt->execute([$idAcademia]);
            return $stmt->fetch() !== false;
        }

        public function listarAcademiasAtivas(){
            header('Content-Type: application/json');
            
            error_log("🎯 listarAcademiasAtivas() chamada");
            
            try {
                // 🔥 CORREÇÃO: Query sem GROUP BY problemático
                $stmt = $this->db->prepare("
                    SELECT 
                        a.idAcademia,
                        a.nome,
                        a.sobre,
                        a.foto_url,
                        a.telefone,
                        a.estacionamento,
                        a.vestiario,
                        a.ar_condicionado,
                        a.wifi,
                        a.avaliacao_fisica,
                        CONCAT(
                            COALESCE(eu.logradouro, ''), 
                            CASE WHEN eu.numero IS NOT NULL THEN CONCAT(', ', eu.numero) ELSE '' END,
                            CASE WHEN eu.bairro IS NOT NULL THEN CONCAT(' - ', eu.bairro) ELSE '' END,
                            CASE WHEN eu.cidade IS NOT NULL THEN CONCAT(', ', eu.cidade) ELSE '' END,
                            CASE WHEN eu.estado IS NOT NULL THEN CONCAT(' - ', eu.estado) ELSE '' END
                        ) as endereco_completo
                    FROM academias a
                    LEFT JOIN enderecos_usuarios eu ON a.idAcademia = eu.idUsuario AND eu.tipoUsuario = 'academia'
                    WHERE a.status_conta = 'Ativa'
                    ORDER BY a.nome
                ");
                $stmt->execute();
                $academias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // 🔥 CORREÇÃO: Buscar modalidades separadamente
                foreach ($academias as &$academia) {
                    $stmtModalidades = $this->db->prepare("
                        SELECT GROUP_CONCAT(m.nome SEPARATOR ', ') as modalidades
                        FROM modalidades_academia ma
                        JOIN modalidades m ON ma.idModalidade = m.idModalidade
                        WHERE ma.idAcademia = ?
                    ");
                    $stmtModalidades->execute([$academia['idAcademia']]);
                    $modalidades = $stmtModalidades->fetch(PDO::FETCH_ASSOC);
                    $academia['modalidades'] = $modalidades['modalidades'] ?? '';
                }

                // 🔥 CORREÇÃO: Log para verificar as URLs das fotos
                foreach ($academias as $academia) {
                    error_log("📸 Academia: {$academia['nome']} - Foto URL: {$academia['foto_url']}");
                }

                error_log("✅ Academias encontradas: " . count($academias));
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $academias
                ]);

            } catch (PDOException $e) {
                error_log("❌ Erro no listarAcademiasAtivas: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro ao buscar academias: ' . $e->getMessage()
                ]);
            }
        }

        private function cadastrarEnderecoUsuario($idUsuario, $tipoUsuario, $data){
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO enderecos_usuarios (
                        idUsuario, tipoUsuario, cep, logradouro, numero, complemento,
                        bairro, cidade, estado, pais, data_criacao
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $idUsuario,
                    $tipoUsuario,
                    $data['cep'] ?? null,
                    $data['logradouro'] ?? null,
                    $data['numero'] ?? null,
                    $data['complemento'] ?? null,
                    $data['bairro'] ?? null,
                    $data['cidade'] ?? null,
                    $data['estado'] ?? null,
                    $data['pais'] ?? 'Brasil'
                ]);

                return true;
            } catch (PDOException $e) {
                error_log("Erro ao cadastrar endereço: " . $e->getMessage());
                return false;
            }
        }

        private function validarDadosEndereco($data){
            $errors = [];

            if (empty($data['cep'])) {
                $errors['cep'] = 'CEP é obrigatório';
            } elseif (strlen(preg_replace('/[^0-9]/', '', $data['cep'])) !== 8) {
                $errors['cep'] = 'CEP deve ter 8 dígitos';
            }

            if (empty($data['cidade'])) {
                $errors['cidade'] = 'Cidade é obrigatória';
            }

            if (empty($data['estado'])) {
                $errors['estado'] = 'Estado é obrigatório';
            } elseif (strlen($data['estado']) !== 2) {
                $errors['estado'] = 'Estado deve ter 2 caracteres';
            }

            return $errors;
        }

        public function processarCadastroCompleto($data) {
            try {
                // 🔥 CORREÇÃO: Verificar se é FormData ou JSON
                $dados = $data;
        
                // Se veio como FormData, processar corretamente
                if (empty($data) && isset($_POST) && !empty($_POST)) {
                    $dados = $_POST;
                    
                    // Processar modalidades do FormData (que vem como array)
                    if (isset($_POST['modalidades']) && is_array($_POST['modalidades'])) {
                        $dados['modalidades'] = $_POST['modalidades'];
                    } else if (isset($_POST['modalidades[]'])) {
                        // Alternativa: modalidades podem vir como modalidades[]
                        $dados['modalidades'] = is_array($_POST['modalidades[]']) ? $_POST['modalidades[]'] : [$_POST['modalidades[]']];
                    }
                }

                error_log("📥 Dados recebidos para cadastro completo: " . json_encode($dados));
                
                // Debug específico para modalidades
                if (isset($dados['modalidades'])) {
                    error_log("🎯 Modalidades recebidas: " . json_encode($dados['modalidades']));
                } else {
                    error_log("⚠️ Nenhuma modalidade recebida");
                    // Tentar buscar modalidades de outras formas
                    if (isset($dados['modalidades[]'])) {
                        $dados['modalidades'] = is_array($dados['modalidades[]']) ? $dados['modalidades[]'] : [$dados['modalidades[]']];
                        error_log("🎯 Modalidades encontradas em modalidades[]: " . json_encode($dados['modalidades']));
                    }
                }
                
                // Debug específico para endereço
                if (isset($data['cep'])) {
                error_log("📍 Dados de endereço recebidos - CEP: " . $data['cep']);
                }

                // Determinar tipo de usuário e ID
                $tipoUsuario = null;
                $idUsuario = null;

                if (isset($dados['idAluno'])) {
                $tipoUsuario = 'aluno';
                $idUsuario = $dados['idAluno'];
                
                // 🔥 APENAS para aluno: validar dados completos
                $erros = $this->validarDadosPerfilAluno($dados);
                if (!empty($erros)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => implode(', ', $erros)]);
                    return;
                }
                } elseif (isset($dados['idPersonal'])) {
                $tipoUsuario = 'personal';
                $idUsuario = $dados['idPersonal'];
                // 🔥 Para personal: NÃO validar peso, altura, etc.
                } elseif (isset($dados['idAcademia'])) {
                $tipoUsuario = 'academia';
                $idUsuario = $dados['idAcademia'];
                }

                if (!$idUsuario || !$tipoUsuario) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID ou tipo de usuário não identificado']);
                    return;
                }

                // 🔥 PROCESSAR FOTO: Se já veio com URL do upload anterior, usar ela
                $fotoUrl = $dados['foto_url'] ?? null;

                // Chamar método específico para cada tipo
                switch ($tipoUsuario) {
                    case 'aluno':
                        return $this->completarCadastroAluno($dados);
                    case 'personal':
                        return $this->completarCadastroPersonal($dados);
                    case 'academia':
                        return $this->completarCadastroAcademia($dados);
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                        return;
                }

            } catch (Exception $e) {
                error_log("❌ Erro geral no processarCadastroCompleto: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
            }
        }

        public function verificarArquivo($data) {
            try {
                $nomeArquivo = $data['nome_arquivo'] ?? '';
                
                if (!$nomeArquivo) {
                    echo json_encode(['success' => false, 'error' => 'Nome do arquivo não fornecido']);
                    return;
                }
                
                $diretorioDestino = __DIR__ . '/../assets/images/uploads/';
                $caminhoCompleto = $diretorioDestino . $nomeArquivo;
                
                $existe = file_exists($caminhoCompleto);
                $tamanho = $existe ? filesize($caminhoCompleto) : 0;
                $acessivel = $existe ? is_readable($caminhoCompleto) : false;
                
                echo json_encode([
                    'success' => true,
                    'existe' => $existe,
                    'tamanho' => $tamanho,
                    'acessivel' => $acessivel,
                    'caminho' => $caminhoCompleto
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function completarCadastroAcademia($data) {
            try {
                $idAcademia = $data['idAcademia'] ?? null;
                if (!$idAcademia) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID da academia é obrigatório']);
                    return;
                }

                // 🔥 PROCESSAR FOTO: Se já veio com URL do upload anterior, usar ela
                $fotoUrl = $data['foto_url'] ?? null;
        
                // Se a foto_url é relativa, converter para absoluta
                if ($fotoUrl && !str_starts_with($fotoUrl, 'http')) {
                    $fotoUrl = $this->getBaseUrl() . '/' . ltrim($fotoUrl, '/');
                }

                // Atualizar dados principais
                $stmt = $this->db->prepare("
                    UPDATE academias 
                    SET sobre = ?, foto_url = ?, treinos_adaptados = ?, 
                        tamanho_estrutura = ?, capacidade_maxima = ?, ano_fundacao = ?, 
                        estacionamento = ?, vestiario = ?, ar_condicionado = ?, wifi = ?, 
                        totem_de_carregamento_usb = ?, area_descanso = ?, avaliacao_fisica = ?,
                        cadastro_completo = 1
                    WHERE idAcademia = ?
                ");

                $success = $stmt->execute([
                    $data['sobre'] ?? null,
                    $fotoUrl, // 🔥 URL da foto
                    $data['treinos_adaptados'] ?? 0,
                    $data['tamanho_estrutura'] ?? null,
                    $data['capacidade_maxima'] ?? null,
                    $data['ano_fundacao'] ?? null,
                    $data['estacionamento'] ?? 0,
                    $data['vestiario'] ?? 0,
                    $data['ar_condicionado'] ?? 0,
                    $data['wifi'] ?? 0,
                    $data['totem_de_carregamento_usb'] ?? 0,
                    $data['area_descanso'] ?? 0,
                    $data['avaliacao_fisica'] ?? 0,
                    $idAcademia
                ]);

                if ($success) {
                    // Atualizar horários
                    if (isset($data['horarios']) && is_array($data['horarios'])) {
                        $this->atualizarHorariosAcademia($idAcademia, $data['horarios']);
                    }
                    
                    // Processar modalidades da academia
                    if (isset($data['modalidades']) && is_array($data['modalidades'])) {
                        $this->vincularModalidadesAcademia($idAcademia, $data['modalidades']);
                    }

                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cadastro completado com sucesso',
                        'foto_url' => $fotoUrl
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Erro ao completar cadastro']);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao completar cadastro: ' . $e->getMessage()]);
            }
        }

        public function completarCadastroAluno($data) {
            try {
                // 🔥 CORREÇÃO: Verificar se é FormData ou JSON
                $dados = $data;
                if (empty($data) && isset($_POST) && !empty($_POST)) {
                    $dados = $_POST;
                    
                    // Processar modalidades do FormData
                    if (isset($_POST['modalidades']) && is_array($_POST['modalidades'])) {
                        $dados['modalidades'] = $_POST['modalidades'];
                    }
                }

                $idAluno = $dados['idAluno'] ?? null;
                if (!$idAluno) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID do aluno é obrigatório']);
                    return;
                }

                $erros = [];
                if (isset($data['idAluno'])) {
                $erros = $this->validarDadosPerfilAluno($data);
                }

                if (!empty($erros)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => implode(', ', $erros)]);
                return;
                }

                // 🔥 PROCESSAR FOTO: Se já veio com URL do upload anterior, usar ela
                $fotoUrl = $data['foto_url'] ?? null;
        
                // Se a foto_url é relativa, converter para absoluta
                if ($fotoUrl && !str_starts_with($fotoUrl, 'http')) {
                    $fotoUrl = $this->getBaseUrl() . '/' . ltrim($fotoUrl, '/');
                }

                $stmt = $this->db->prepare("
                    UPDATE alunos 
                    SET data_nascimento = ?, genero = ?, altura = ?, peso = ?, 
                        meta = ?, treinoTipo = ?, treinos_adaptados = ?, foto_url = ?, cadastro_completo = 1
                    WHERE idAluno = ?
                ");

                $success = $stmt->execute([
                    $dados['data_nascimento'] ?? null,
                    $dados['genero'] ?? null,
                    $dados['altura'] ?? null,
                    $dados['peso'] ?? null,
                    $dados['meta'] ?? null,
                    $dados['treinoTipo'] ?? null,
                    $dados['treinos_adaptados'] ?? 0,
                    $fotoUrl, // 🔥 Agora com URL absoluta
                    $idAluno
                ]);

                if ($success) {
                    // Processar modalidades
                    if (isset($dados['modalidades']) && is_array($dados['modalidades'])) {
                        $this->vincularModalidadesAluno($idAluno, $dados['modalidades']);
                    }

                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cadastro completado com sucesso',
                        'foto_url' => $fotoUrl
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Erro ao completar cadastro']);
                }

            } catch (PDOException $e) {
                error_log("❌ PDOException: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao completar cadastro: ' . $e->getMessage()]);
            }
        }

        public function completarCadastroPersonal($data) {
            try {
                $idPersonal = $data['idPersonal'] ?? null;
                if (!$idPersonal) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID do personal é obrigatório']);
                    return;
                }

                // Validar dados do perfil
                $erros = $this->validarDadosPerfil($data);
                if (!empty($erros)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => implode(', ', $erros)]);
                    return;
                }

                // 🔥 PROCESSAR FOTO: Se já veio com URL do upload anterior, usar ela
                $fotoUrl = $data['foto_url'] ?? null;
        
                // Se a foto_url é relativa, converter para absoluta
                if ($fotoUrl && !str_starts_with($fotoUrl, 'http')) {
                    $fotoUrl = $this->getBaseUrl() . '/' . ltrim($fotoUrl, '/');
                }

                // Atualizar dados principais
                $stmt = $this->db->prepare("
                    UPDATE personal 
                    SET data_nascimento = ?, genero = ?, foto_url = ?, 
                        sobre = ?, treinos_adaptados = ?, cadastro_completo = 1
                    WHERE idPersonal = ?
                ");

                $success = $stmt->execute([
                    $data['data_nascimento'] ?? null,
                    $data['genero'] ?? null,
                    $fotoUrl, // 🔥 URL da foto
                    $data['sobre'] ?? null,
                    $data['treinos_adaptados'] ?? 0,
                    $idPersonal
                ]);

                if ($success) {
                    // Processar modalidades
                    if (isset($data['modalidades']) && is_array($data['modalidades'])) {
                        $this->vincularModalidadesPersonal($idPersonal, $data['modalidades']);
                    }

                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cadastro completado com sucesso',
                        'foto_url' => $fotoUrl
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Erro ao completar cadastro']);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao completar cadastro: ' . $e->getMessage()]);
            }
        }


        /**
         * LISTAR MODALIDADES
         */
        public function listarModalidades() {
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
                    'error' => 'Erro ao buscar modalidades: ' . $e->getMessage()
                ]);
            }
        }

        private function vincularModalidadesAcademia($idAcademia, $modalidades) {
            try {
                // Limpar modalidades existentes
                $stmt = $this->db->prepare("DELETE FROM modalidades_academia WHERE idAcademia = ?");
                $stmt->execute([$idAcademia]);

                // Inserir novas modalidades
                $stmt = $this->db->prepare("INSERT INTO modalidades_academia (idAcademia, idModalidade) VALUES (?, ?)");
                
                foreach ($modalidades as $idModalidade) {
                    if (is_numeric($idModalidade)) {
                        $stmt->execute([$idAcademia, $idModalidade]);
                    }
                }
                
                error_log("✅ Modalidades vinculadas: " . count($modalidades));
                return true;
            } catch (PDOException $e) {
                error_log("❌ Erro ao vincular modalidades: " . $e->getMessage());
                return false;
            }
        }

        /**
         * VINCULAR MODALIDADES AO ALUNO
         */
        private function vincularModalidadesAluno($idAluno, $modalidades) {
            // Limpar modalidades existentes
            $stmt = $this->db->prepare("DELETE FROM modalidades_aluno WHERE idAluno = ?");
            $stmt->execute([$idAluno]);

            // Inserir novas modalidades
            $stmt = $this->db->prepare("INSERT INTO modalidades_aluno (idAluno, idModalidade) VALUES (?, ?)");
            
            foreach ($modalidades as $idModalidade) {
                if (is_numeric($idModalidade)) {
                    $stmt->execute([$idAluno, $idModalidade]);
                }
            }
        }

        /**
         * VINCULAR MODALIDADES AO PERSONAL
         */
        private function vincularModalidadesPersonal($idPersonal, $modalidades) {
            // Limpar modalidades existentes
            $stmt = $this->db->prepare("DELETE FROM modalidades_personal WHERE idPersonal = ?");
            $stmt->execute([$idPersonal]);

            // Inserir novas modalidades
            $stmt = $this->db->prepare("INSERT INTO modalidades_personal (idPersonal, idModalidade) VALUES (?, ?)");
            
            foreach ($modalidades as $idModalidade) {
                if (is_numeric($idModalidade)) {
                    $stmt->execute([$idPersonal, $idModalidade]);
                }
            }
        }

        /**
         * VALIDAR DADOS DO PERFIL
         */
        private function validarDadosPerfil($data) {
            $erros = [];

            if (isset($data['data_nascimento'])) {
                $dataNasc = DateTime::createFromFormat('Y-m-d', $data['data_nascimento']);
                $hoje = new DateTime();
                
                if (!$dataNasc || $dataNasc > $hoje) {
                    $erros[] = 'Data de nascimento inválida';
                } else {
                    $idade = $hoje->diff($dataNasc)->y;
                    if ($idade < 12 || $idade > 120) {
                        $erros[] = 'Idade deve estar entre 12 e 120 anos';
                    }
                }
            }

            if (isset($data['altura']) && ($data['altura'] < 100 || $data['altura'] > 250)) {
                $erros[] = 'Altura deve estar entre 100cm e 250cm';
            }

            if (isset($data['genero']) && !in_array($data['genero'], ['Masculino', 'Feminino', 'Outro'])) {
                $erros[] = 'Gênero inválido';
            }

            return $erros;
        }

        private function salvarHorariosAcademia($academiaId, $horarios) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO academia_horarios 
                    (idAcademia, dia_semana, aberto_24h, horario_abertura, horario_fechamento, fechado) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($horarios as $horario) {
                    $stmt->execute([
                        $academiaId,
                        $horario['dia_semana'],
                        $horario['aberto_24h'] ? 1 : 0,
                        $horario['horario_abertura'] ?: null,
                        $horario['horario_fechamento'] ?: null,
                        $horario['fechado'] ? 1 : 0
                    ]);
                }
                
                return true;
            } catch (PDOException $e) {
                error_log("Erro ao salvar horários da academia: " . $e->getMessage());
                return false;
            }
        }

        private function atualizarHorariosAcademia($academiaId, $horarios) {
            try {
                // Primeiro deleta os horários existentes
                $deleteStmt = $this->db->prepare("DELETE FROM academia_horarios WHERE idAcademia = ?");
                $deleteStmt->execute([$academiaId]);
                
                // Depois insere os novos
                return $this->salvarHorariosAcademia($academiaId, $horarios);
            } catch (PDOException $e) {
                error_log("Erro ao atualizar horários da academia: " . $e->getMessage());
                return false;
            }
        }
        
        private function enviarSolicitacaoVinculacaoAcademia($idUsuario, $tipoUsuario, $idAcademia) {
            try {
                // Verificar se academia existe e está ativa
                $stmt = $this->db->prepare("SELECT idAcademia FROM academias WHERE idAcademia = ? AND status_conta = 'Ativa'");
                $stmt->execute([$idAcademia]);
                
                if (!$stmt->fetch()) {
                    error_log("⚠️ Academia ID $idAcademia não encontrada ou inativa");
                    return false;
                }

                // Gerar token único
                $token = bin2hex(random_bytes(32));

                // Inserir solicitação
                $stmt = $this->db->prepare("
                    INSERT INTO solicitacoes_academia 
                    (token, idAcademia, idUsuario, tipo_usuario, data_criacao) 
                    VALUES (?, ?, ?, ?, NOW())
                ");

                $success = $stmt->execute([
                    $token,
                    $idAcademia,
                    $idUsuario,
                    $tipoUsuario
                ]);

                if ($success) {
                    error_log("✅ Solicitação de vinculação enviada para academia ID: $idAcademia");
                    return true;
                } else {
                    error_log("❌ Erro ao enviar solicitação de vinculação");
                    return false;
                }
            } catch (PDOException $e) {
                error_log("❌ PDOException ao enviar solicitação: " . $e->getMessage());
                return false;
            }
        }

        private function validarDadosPerfilAluno($data) {
            $erros = [];

            if (empty($data['genero'])) {
                $erros[] = 'Gênero é obrigatório';
            } else if (!in_array($data['genero'], ['Masculino', 'Feminino', 'Outro'])) {
                $erros[] = 'Gênero inválido';
            }

            if (empty($data['altura'])) {
                $erros[] = 'Altura é obrigatória';
            } else if ($data['altura'] < 100 || $data['altura'] > 250) {
                $erros[] = 'Altura deve estar entre 100cm e 250cm';
            }

            if (empty($data['peso'])) {
                $erros[] = 'Peso é obrigatório';
            } else if ($data['peso'] < 30 || $data['peso'] > 300) {
                $erros[] = 'Peso deve estar entre 30kg e 300kg';
            }

            if (empty($data['data_nascimento'])) {
                $erros[] = 'Data de nascimento é obrigatória';
            } else {
                $dataNasc = DateTime::createFromFormat('Y-m-d', $data['data_nascimento']);
                $hoje = new DateTime();
                
                if (!$dataNasc || $dataNasc > $hoje) {
                $erros[] = 'Data de nascimento inválida';
                } else {
                $idade = $hoje->diff($dataNasc)->y;
                if ($idade < 10 || $idade > 120) {
                    $erros[] = 'Idade deve estar entre 10 e 120 anos';
                }
                }
            }

            if (empty($data['treinoTipo'])) {
                $erros[] = 'Nível de atividade é obrigatório';
            } else if (!in_array($data['treinoTipo'], ['Sedentário', 'Leve', 'Moderado', 'Intenso'])) {
                $erros[] = 'Nível de atividade inválido';
            }

            if (empty($data['modalidades']) || !is_array($data['modalidades']) || count($data['modalidades']) === 0) {
                $erros[] = 'Selecione pelo menos uma modalidade';
            }
            
            return $erros;
        }

        private function validarCREFCompleto($crefNumero, $categoria, $regional) {
            // Validar formato do número (6-9 dígitos)
            if (!preg_match('/^\d{6,9}$/', $crefNumero)) {
                return false;
            }
            
            // Validar categoria (uma letra)
            if (!preg_match('/^[A-Z]$/', $categoria)) {
                return false;
            }
            
            // Validar regional (2 letras - sigla do estado)
            $regionaisValidas = [
                'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
                'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
                'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
            ];
            
            if (!in_array($regional, $regionaisValidas)) {
                return false;
            }
            
            return true;
        }

        private function verificarSituacaoCREF($crefCompleto) {
            // TODO: Implementar integração com API dos CREFs
            // Por enquanto, retorna true para testes
            return true;
        }

        private function getBaseUrl() {
            // 🔥 CORREÇÃO: URL fixa para produção
            if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'clidefit.com.br') !== false) {
                return 'https://api.clidefit.com.br';
            }
            
            // Para desenvolvimento
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return "{$protocol}://{$host}";
        }

    }
