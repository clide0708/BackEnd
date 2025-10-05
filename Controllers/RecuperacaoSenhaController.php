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

                $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Código para recuperação de senha';
                $mail->Body = "
                    <p>Olá,</p>
                    <p>Você solicitou a recuperação de senha. Use o código abaixo para redefinir sua senha:</p>
                    <h2 style='font-family: monospace; letter-spacing: 3px;'>$codigo</h2>
                    <p>Este código é válido por 15 minutos.</p>
                    <p>Se você não solicitou, ignore este e-mail.</p>
                    <p>Atenciosamente,<br>Equipe CLIDE Fit</p>
                ";

                $mail->send();
            } catch (Exception $e) {
                error_log("Erro ao enviar email de recuperação: " . $mail->ErrorInfo);
                throw new Exception("Não foi possível enviar o email de recuperação.");
            }
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