<?php
    
    require_once __DIR__ . '/../Config/db.connect.php';
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../Config/jwt.config.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    class RecuperacaoSenhaController {
        private $pdo;
        private $tokenSecret;

        public function __construct() {
            $this->pdo = DB::connectDB();
            $this->tokenSecret = $_ENV['TOKEN_SECRET'] ?? 'fallback_secret';
        }

        /**
         * Endpoint POST /esqueci-senha
         */
        public function esqueciSenha($data) {
            header('Content-Type: application/json');

            $email = trim(strtolower($data['email'] ?? ''));

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email inválido ou não fornecido']);
                return;
            }

            $existe = $this->emailExiste($email);

            if (!$existe) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'O e-mail informado não foi encontrado :(!']);
                return;
            }

            $codigo = $this->gerarCodigo(6);
            $tokenHash = hash_hmac('sha256', $codigo, $this->tokenSecret);
            $expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            try {
                $this->limparTokensAntigos($email);

                $stmt = $this->pdo->prepare("
                    INSERT INTO recuperacao_senha (email, token_hash, expiraEM, tentativas, usado, criadoEm)
                    VALUES (?, ?, ?, 0, 0, NOW())
                ");
                $stmt->execute([$email, $tokenHash, $expiresAt]);

                $this->enviarEmailCodigo($email, $codigo);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => "Uma mensagem com o código e instruções foi enviada para '$email'."
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
            }
        }

        /**
         * Endpoint POST /resetar-senha
         */
        public function resetarSenha($data) {
            header('Content-Type: application/json');

            $email = trim(strtolower($data['email'] ?? ''));
            $codigo = trim($data['codigo'] ?? '');
            $novaSenha = $data['nova_senha'] ?? '';

            if (!$email || !$codigo || !$novaSenha) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email inválido']);
                return;
            }

            if (strlen($novaSenha) < 6) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres']);
                return;
            }

            try {
                $stmt = $this->pdo->prepare("
                    SELECT id, token_hash, expiraEm, tentativas, usado
                    FROM recuperacao_senha 
                    WHERE email = ? AND usado= 0 AND expiraEm > NOW()
                    ORDER BY criadoEm DESC
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $registro = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$registro) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Código inválido ou expirado']);
                    return;
                }

                if ($registro['tentativas'] >= 3) {
                    http_response_code(429);
                    echo json_encode(['success' => false, 'error' => 'Número máximo de tentativas excedido. Solicite um novo código.']);
                    return;
                }

                $codigoHash = hash_hmac('sha256', $codigo, $this->tokenSecret);
                if (!hash_equals($registro['token_hash'], $codigoHash)) {
                    $stmt = $this->pdo->prepare("UPDATE recuperacao_senha SET tentativas = tentativas + 1 WHERE id = ?");
                    $stmt->execute([$registro['id']]);

                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Código incorreto']);
                    return;
                }

                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $atualizado = $this->atualizarSenhaUsuario($email, $senhaHash);

                if (!$atualizado) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar a senha']);
                    return;
                }

                $stmt = $this->pdo->prepare("UPDATE recuperacao_senha SET usado= 1 WHERE id = ?");
                $stmt->execute([$registro['id']]);

                // Envia email de confirmação de senha alterada
                $this->enviarEmailConfirmacaoSenha($email);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Senha atualizada com sucesso']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
            }
        }

        // --- Funções auxiliares ---

        private function emailExiste(string $email): bool {
            $stmt = $this->pdo->prepare("SELECT 1 FROM alunos WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) return true;

            $stmt = $this->pdo->prepare("SELECT 1 FROM personal WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) return true;

            $stmt = $this->pdo->prepare("SELECT 1 FROM devs WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) return true;

            return false;
        }

        private function gerarCodigo(int $length): string {
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $codigo = '';
            $maxIndex = strlen($chars) - 1;
            for ($i = 0; $i < $length; $i++) {
                $codigo .= $chars[random_int(0, $maxIndex)];
            }
            return $codigo;
        }

        private function limparTokensAntigos(string $email): void {
            $stmt = $this->pdo->prepare("DELETE FROM recuperacao_senha WHERE email = ? AND usado= 1");
            $stmt->execute([$email]);
        }

        private function enviarEmailCodigo(string $email, string $codigo): void {
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = $_ENV['SMTP_HOST'];
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['SMTP_USER'];
                $mail->Password = $_ENV['SMTP_PASS'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = intval($_ENV['SMTP_PORT']);

                // CONFIGURAÇÕES DE CHARSET (ADICIONE ESTAS LINHAS)
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->setLanguage('pt_br', __DIR__ . '/../vendor/phpmailer/phpmailer/language/');

                $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = '🔐 Código de Recuperação de Senha - CLIDE Fit';
                
                // Template HTML bonito para o código de recuperação
                $mail->Body = $this->criarTemplateCodigoRecuperacao($codigo);
                $mail->AltBody = "Seu código de recuperação é: $codigo - Válido por 15 minutos.";

                $mail->send();
            } catch (Exception $e) {
                error_log("Erro ao enviar email de recuperação: " . $mail->ErrorInfo);
                throw new Exception("Não foi possível enviar o email de recuperação.");
            }
        }

        private function enviarEmailConfirmacaoSenha(string $email): void {
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = $_ENV['SMTP_HOST'];
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['SMTP_USER'];
                $mail->Password = $_ENV['SMTP_PASS'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = intval($_ENV['SMTP_PORT']);

                // CONFIGURAÇÕES DE CHARSET (ADICIONE ESTAS LINHAS)
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->setLanguage('pt_br', __DIR__ . '/../vendor/phpmailer/phpmailer/language/');

                $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = '✅ Senha Alterada com Sucesso - CLIDE Fit';
                
                // Template HTML para confirmação de senha alterada
                $mail->Body = $this->criarTemplateConfirmacaoSenha();
                $mail->AltBody = "Sua senha foi alterada com sucesso. Se você não realizou esta ação, entre em contato conosco imediatamente.";

                $mail->send();
            } catch (Exception $e) {
                error_log("Erro ao enviar email de confirmação: " . $mail->ErrorInfo);
                // Não lança exceção para não afetar o fluxo principal
            }
        }

        /**
         * Cria template HTML para email de código de recuperação
         */
        private function criarTemplateCodigoRecuperacao($codigo) {
            $expiraEm = 15; // minutos
            $appName = "CLIDE Fit";
            
            return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Recuperação de Senha</title>
                <style>
                    body { 
                        font-family: 'Arial', sans-serif; 
                        line-height: 1.6; 
                        color: #333; 
                        margin: 0; 
                        padding: 0; 
                        background-color: #f4f4f4;
                    }
                    .container { 
                        max-width: 600px; 
                        margin: 0 auto; 
                        background: #ffffff;
                        border-radius: 10px;
                        overflow: hidden;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    }
                    .header { 
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white; 
                        padding: 40px 30px; 
                        text-align: center; 
                    }
                    .header h1 {
                        margin: 0;
                        font-size: 28px;
                        font-weight: bold;
                    }
                    .header p {
                        margin: 10px 0 0 0;
                        opacity: 0.9;
                    }
                    .content { 
                        background: white; 
                        padding: 40px 30px; 
                    }
                    .codigo-container {
                        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                        color: white;
                        padding: 25px;
                        border-radius: 10px;
                        text-align: center;
                        margin: 25px 0;
                        font-family: 'Courier New', monospace;
                        letter-spacing: 8px;
                        font-size: 32px;
                        font-weight: bold;
                        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
                    }
                    .detalhes { 
                        background: #f8f9fa; 
                        padding: 20px; 
                        border-radius: 8px; 
                        margin: 20px 0; 
                        border-left: 4px solid #667eea; 
                    }
                    .footer { 
                        text-align: center; 
                        margin-top: 30px; 
                        color: #666; 
                        font-size: 14px; 
                        padding: 30px; 
                        background: #f1f1f1;
                        border-top: 1px solid #e0e0e0;
                    }
                    .info-item { 
                        margin-bottom: 12px; 
                    }
                    .info-item strong { 
                        color: #2c3e50; 
                    }
                    .contato-item { 
                        margin: 8px 0; 
                        color: #555;
                    }
                    .warning {
                        background: #fff3cd;
                        border: 1px solid #ffeaa7;
                        color: #856404;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 15px 0;
                    }
                    .steps {
                        background: #e8f4fd;
                        padding: 20px;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                    .step {
                        display: flex;
                        align-items: center;
                        margin-bottom: 15px;
                    }
                    .step-number {
                        background: #667eea;
                        color: white;
                        width: 30px;
                        height: 30px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin-right: 15px;
                        font-weight: bold;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔐 Recuperação de Senha</h1>
                        <p>$appName</p>
                    </div>
                    <div class='content'>
                        <p>Olá,</p>
                        <p>Recebemos uma solicitação para redefinir sua senha. Use o código abaixo para continuar o processo:</p>
                        
                        <div class='codigo-container'>
                            $codigo
                        </div>
                        
                        <div class='warning'>
                            <strong>⚠️ Importante:</strong> Este código é válido por <strong>$expiraEm minutos</strong>. 
                            Após este período, será necessário solicitar um novo código.
                        </div>
                        
                        <div class='steps'>
                            <h3 style='color: #2c3e50; margin-top: 0;'>📝 Como usar este código:</h3>
                            <div class='step'>
                                <div class='step-number'>1</div>
                                <div>Volte para o aplicativo $appName</div>
                            </div>
                            <div class='step'>
                                <div class='step-number'>2</div>
                                <div>Insira o código acima no campo indicado</div>
                            </div>
                            <div class='step'>
                                <div class='step-number'>3</div>
                                <div>Crie sua nova senha</div>
                            </div>
                        </div>
                        
                        <div class='detalhes'>
                            <h3 style='color: #2c3e50; margin-top: 0;'>🛡️ Segurança</h3>
                            <p>Se você não solicitou a recuperação de senha, por favor:</p>
                            <ul>
                                <li>Ignore este email</li>
                                <li>Verifique a segurança da sua conta</li>
                                <li>Entre em contato conosco se notar atividades suspeitas</li>
                            </ul>
                        </div>
                        
                        <div class='footer'>
                            <p><strong>Atenciosamente,</strong><br>
                            Equipe $appName</p>
                            <p style='font-size: 12px; color: #999; margin-top: 15px;'>
                                Este é um email automático, por favor não responda.<br>
                                Se precisar de ajuda, entre em contato com nosso suporte.
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
        }

        /**
         * Cria template HTML para confirmação de senha alterada
         */
        private function criarTemplateConfirmacaoSenha() {
            $appName = "CLIDE Fit";
            
            return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Senha Alterada</title>
                <style>
                    body { 
                        font-family: 'Arial', sans-serif; 
                        line-height: 1.6; 
                        color: #333; 
                        margin: 0; 
                        padding: 0; 
                        background-color: #f4f4f4;
                    }
                    .container { 
                        max-width: 600px; 
                        margin: 0 auto; 
                        background: #ffffff;
                        border-radius: 10px;
                        overflow: hidden;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    }
                    .header { 
                        background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
                        color: white; 
                        padding: 40px 30px; 
                        text-align: center; 
                    }
                    .header h1 {
                        margin: 0;
                        font-size: 28px;
                        font-weight: bold;
                    }
                    .content { 
                        background: white; 
                        padding: 40px 30px; 
                    }
                    .success-icon {
                        font-size: 80px;
                        text-align: center;
                        margin: 20px 0;
                        color: #27ae60;
                    }
                    .detalhes { 
                        background: #f8f9fa; 
                        padding: 20px; 
                        border-radius: 8px; 
                        margin: 20px 0; 
                        border-left: 4px solid #27ae60; 
                    }
                    .footer { 
                        text-align: center; 
                        margin-top: 30px; 
                        color: #666; 
                        font-size: 14px; 
                        padding: 30px; 
                        background: #f1f1f1;
                        border-top: 1px solid #e0e0e0;
                    }
                    .info-item { 
                        margin-bottom: 12px; 
                    }
                    .security-notice {
                        background: #d4edda;
                        border: 1px solid #c3e6cb;
                        color: #155724;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                    .next-steps {
                        background: #d1ecf1;
                        border: 1px solid #bee5eb;
                        color: #0c5460;
                        padding: 20px;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>✅ Senha Alterada</h1>
                        <p>$appName</p>
                    </div>
                    <div class='content'>
                        <div class='success-icon'>✓</div>
                        
                        <p style='text-align: center; font-size: 18px; color: #27ae60;'><strong>Senha alterada com sucesso!</strong></p>
                        
                        <p>Olá,</p>
                        <p>Sua senha foi atualizada com sucesso. A partir de agora, use sua nova senha para acessar sua conta.</p>
                        
                        <div class='security-notice'>
                            <h3 style='color: #155724; margin-top: 0;'>🛡️ Medida de Segurança</h3>
                            <p>Por segurança, todas as sessões ativas em outros dispositivos podem ter sido encerradas.</p>
                        </div>
                        
                        <div class='next-steps'>
                            <h3 style='color: #0c5460; margin-top: 0;'>📱 Próximos Passos</h3>
                            <ul>
                                <li>Faça login em todos os seus dispositivos com a nova senha</li>
                                <li>Verifique as configurações de segurança da sua conta</li>
                                <li>Mantenha sua senha em local seguro</li>
                            </ul>
                        </div>
                        
                        <div class='detalhes'>
                            <h3 style='color: #2c3e50; margin-top: 0;'>❌ Não foi você?</h3>
                            <p>Se você não realizou esta alteração:</p>
                            <ul>
                                <li>Entre em contato conosco IMEDIATAMENTE</li>
                                <li>Altere sua senha novamente</li>
                                <li>Verifique as atividades recentes da sua conta</li>
                            </ul>
                        </div>
                        
                        <div class='footer'>
                            <p><strong>Atenciosamente,</strong><br>
                            Equipe $appName</p>
                            <p style='font-size: 12px; color: #999; margin-top: 15px;'>
                                Este é um email automático de segurança.<br>
                                Para sua proteção, não responda este email.
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
        }

        private function atualizarSenhaUsuario(string $email, string $senhaHash): bool {
            $stmt = $this->pdo->prepare("UPDATE alunos SET senha = ? WHERE email = ?");
            $stmt->execute([$senhaHash, $email]);
            if ($stmt->rowCount() > 0) return true;

            $stmt = $this->pdo->prepare("UPDATE personal SET senha = ? WHERE email = ?");
            $stmt->execute([$senhaHash, $email]);
            if ($stmt->rowCount() > 0) return true;
            
            $stmt = $this->pdo->prepare("UPDATE devs SET senha = ? WHERE email = ?");
            $stmt->execute([$senhaHash, $email]);
            if ($stmt->rowCount() > 0) return true;

            return false;
        }
    }

?>