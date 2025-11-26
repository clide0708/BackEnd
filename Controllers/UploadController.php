<?php

    require_once __DIR__ . '/../Config/db.connect.php';

    class UploadController {
        private $db;

        public function __construct() {
            $this->db = DB::connectDB();
        }

        private function getBaseUrl() {
            // 🔥 CORREÇÃO: Usar URL fixa da API em produção
            if (isset($_SERVER['HTTP_HOST'])) {
                $host = $_SERVER['HTTP_HOST'];
                
                // Se estiver em produção, usar URL fixa
                if (strpos($host, 'clidefit.com.br') !== false) {
                    return 'https://api.clidefit.com.br';
                }
                
                // Para desenvolvimento local
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                return "{$protocol}://{$host}";
            }
            
            // Fallback para produção
            return 'https://api.clidefit.com.br';
        }

        public function uploadFotoPerfil($data = null) {
            try {
                // Verificar se é um upload de arquivo via POST
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    http_response_code(405);
                    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
                    return;
                }

                // Verificar se há arquivo enviado
                if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado ou erro no upload']);
                    return;
                }

                $arquivo = $_FILES['foto'];
                
                // Validar tipo de arquivo
                $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($arquivo['type'], $tiposPermitidos)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use: JPG, PNG, GIF ou WebP']);
                    return;
                }

                // Validar tamanho (máximo 5MB)
                if ($arquivo['size'] > 5 * 1024 * 1024) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo: 5MB']);
                    return;
                }

                // Criar diretório se não existir
                $diretorioDestino = __DIR__ . '/../assets/images/uploads/';
                if (!is_dir($diretorioDestino)) {
                    mkdir($diretorioDestino, 0755, true);
                }

                // Gerar nome único para o arquivo
                $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
                $nomeArquivo = 'perfil_' . time() . '_' . uniqid() . '.' . $extensao;
                $caminhoCompleto = $diretorioDestino . $nomeArquivo;

                // Mover arquivo para o diretório de destino
                if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                    // 🔥 CORREÇÃO: URL relativa consistente
                    $urlRelativa = 'assets/images/uploads/' . $nomeArquivo;
                    
                    // 🔥 CORREÇÃO: URL completa usando base fixa
                    $baseUrl = $this->getBaseUrl();
                    $urlCompleta = $baseUrl . '/' . $urlRelativa;
                    
                    // 🔥 CORREÇÃO: Log para debug
                    error_log("📸 URL da foto gerada: " . $urlCompleta);
                    
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'url' => $urlRelativa, // Para compatibilidade
                        'url_completa' => $urlCompleta, // Nova URL completa
                        'nome_arquivo' => $nomeArquivo,
                        'message' => 'Foto uploadada com sucesso'
                    ]);
                } else {
                    error_log("❌ Erro ao mover arquivo uploadado");
                    error_log("❌ Caminho destino: " . $caminhoCompleto);
                    error_log("❌ Caminho temporário: " . $arquivo['tmp_name']);
                    error_log("❌ Erro upload: " . $arquivo['error']);
                    
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo no servidor']);
                }

            } catch (Exception $e) {
                error_log("❌ Exception no upload: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
            }
        }

        // Método para salvar URL da foto no banco de dados
        public function salvarFotoUsuario($data) {
            try {
                if (!isset($data['idUsuario']) || !isset($data['tipoUsuario']) || !isset($data['foto_url'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                    return;
                }

                $idUsuario = $data['idUsuario'];
                $tipoUsuario = $data['tipoUsuario'];
                $fotoUrl = $data['foto_url'];

                // Determinar a tabela e coluna baseado no tipo de usuário
                switch ($tipoUsuario) {
                    case 'aluno':
                        $tabela = 'alunos';
                        $colunaId = 'idAluno';
                        $colunaFoto = 'foto_url';
                        break;
                    case 'personal':
                        $tabela = 'personal';
                        $colunaId = 'idPersonal';
                        $colunaFoto = 'foto_url';
                        break;
                    case 'academia':
                        $tabela = 'academias';
                        $colunaId = 'idAcademia';
                        $colunaFoto = 'foto_url';
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                        return;
                }

                // Atualizar com nova foto
                $stmt = $this->db->prepare("UPDATE {$tabela} SET {$colunaFoto} = ? WHERE {$colunaId} = ?");
                $success = $stmt->execute([$fotoUrl, $idUsuario]);

                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Foto salva com sucesso',
                        'foto_url' => $fotoUrl
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao salvar foto no banco de dados']);
                }

            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
            }
        }

        // Método para obter foto atual do usuário
        public function obterFotoUsuario($data) {
            try {
                if (!isset($data['idUsuario']) || !isset($data['tipoUsuario'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                    return;
                }

                $idUsuario = $data['idUsuario'];
                $tipoUsuario = $data['tipoUsuario'];

                // Determinar a tabela e coluna baseado no tipo de usuário
                switch ($tipoUsuario) {
                    case 'aluno':
                        $tabela = 'alunos';
                        $colunaId = 'idAluno';
                        $colunaFoto = 'foto_url';
                        break;
                    case 'personal':
                        $tabela = 'personal';
                        $colunaId = 'idPersonal';
                        $colunaFoto = 'foto_url';
                        break;
                    case 'academia':
                        $tabela = 'academias';
                        $colunaId = 'idAcademia';
                        $colunaFoto = 'foto_url';
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Tipo de usuário inválido']);
                        return;
                }

                // Buscar foto atual
                $stmt = $this->db->prepare("SELECT {$colunaFoto} FROM {$tabela} WHERE {$colunaId} = ?");
                $stmt->execute([$idUsuario]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($usuario) {
                    echo json_encode([
                        'success' => true,
                        'foto_url' => $usuario[$colunaFoto],
                        'tem_foto' => !empty($usuario[$colunaFoto])
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'foto_url' => null,
                        'tem_foto' => false
                    ]);
                }

            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
            }
        }

        // Método para deletar arquivo físico
        private function deletarFotoArquivo($fotoUrl) {
            try {
                if (empty($fotoUrl)) return;

                // Extrair nome do arquivo da URL
                $nomeArquivo = basename($fotoUrl);
                $diretorioDestino = __DIR__ . '/../assets/images/uploads/';
                $caminhoCompleto = $diretorioDestino . $nomeArquivo;

                if (file_exists($caminhoCompleto) && is_file($caminhoCompleto)) {
                    unlink($caminhoCompleto);
                }
            } catch (Exception $e) {
                // Logar erro mas não interromper o processo
                error_log("Erro ao deletar arquivo: " . $e->getMessage());
            }
        }

        // Método para deletar foto via API
        public function deletarFotoPerfil($data) {
            try {
                if (!isset($data['nome_arquivo'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nome do arquivo não fornecido']);
                    return;
                }

                $diretorioDestino = __DIR__ . '/../assets/images/uploads/';
                $caminhoCompleto = $diretorioDestino . $data['nome_arquivo'];

                if (file_exists($caminhoCompleto) && unlink($caminhoCompleto)) {
                    echo json_encode(['success' => true, 'message' => 'Foto deletada com sucesso']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Arquivo não encontrado ou erro ao deletar']);
                }

            } catch (Exception $e) {
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
                    'caminho' => $caminhoCompleto,
                    'url_relativa' => '/assets/images/uploads/' . $nomeArquivo
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function uploadDocumentoCREF($data = null) {
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    http_response_code(405);
                    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
                    return;
                }

                if (!isset($_FILES['cref_documento']) || $_FILES['cref_documento']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nenhum documento enviado']);
                    return;
                }

                $arquivo = $_FILES['cref_documento'];
                $crefNumero = $_POST['cref_numero'] ?? 'unknown';
                
                // Validar tipos
                $tiposPermitidos = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                if (!in_array($arquivo['type'], $tiposPermitidos)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Tipo de documento não permitido']);
                    return;
                }

                // Validar tamanho
                if ($arquivo['size'] > 5 * 1024 * 1024) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Documento muito grande. Máximo: 5MB']);
                    return;
                }

                // Criar diretório específico para documentos CREF
                $diretorioDestino = __DIR__ . '/../assets/documents/cref/';
                if (!is_dir($diretorioDestino)) {
                    mkdir($diretorioDestino, 0755, true);
                }

                // Gerar nome único
                $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
                $nomeArquivo = 'cref_' . $crefNumero . '_' . time() . '.' . $extensao;
                $caminhoCompleto = $diretorioDestino . $nomeArquivo;

                if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                    $urlRelativa = '/assets/documents/cref/' . $nomeArquivo;
                    
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'url' => $urlRelativa,
                        'nome_arquivo' => $nomeArquivo,
                        'message' => 'Documento CREF enviado para análise'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao salvar documento']);
                }

            } catch (Exception $e) {
                error_log("Erro upload documento CREF: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
            }
        }
    }

?>