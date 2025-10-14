<?php

require_once __DIR__ . '/../Config/db.connect.php';
require_once __DIR__ . '/../Services/TreinosService.php';
require_once __DIR__ . '/../Repositories/TreinosRepository.php';

class TreinosController
{
    private $db;
    private $treinosService;

    public function __construct()
    {
        $this->db = DB::connectDB();
        $this->treinosService = new TreinosService();
    }

    // Método auxiliar para obter usuário do token
    private function obterUsuarioDoToken()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            require_once __DIR__ . '/../Config/jwt.config.php';

            try {
                $decoded = decodificarToken($token);
                return $decoded ? (array)$decoded : null;
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    // Treinos
    public function criarTreino($data)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $idTreino = $this->treinosService->criarTreino($data);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'idTreino' => $idTreino,
                'message' => 'Treino criado com sucesso'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function atualizarTreino($idTreino, $data)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $this->treinosService->atualizarTreino($idTreino, $data, $usuario);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Treino atualizado com sucesso']);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function excluirTreino($idTreino)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            // LOG para debug
            error_log("Tentando excluir treino ID: " . $idTreino . " por usuário: " . $usuario['email']);

            $this->treinosService->excluirTreino($idTreino, $usuario);

            // Verificar se realmente foi excluído
            $treinoRepository = new TreinosRepository();
            $treinoVerificado = $treinoRepository->buscarTreinoPorId($idTreino);

            if ($treinoVerificado) {
                error_log("ERRO: Treino ID " . $idTreino . " ainda existe após exclusão!");
                throw new Exception("Falha na exclusão do treino - treino ainda existe no banco");
            }

            error_log("SUCESSO: Treino ID " . $idTreino . " excluído com sucesso");

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Treino excluído com sucesso']);
        } catch (Exception $e) {
            error_log("ERRO na exclusão do treino " . $idTreino . ": " . $e->getMessage());
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Exercícios no Treino
    public function adicionarExercicioAoTreino($idTreino, $exercicioData)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $this->treinosService->adicionarExercicioAoTreino($idTreino, $exercicioData, $usuario);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Exercício adicionado ao treino']);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function atualizarExercicioNoTreino($idTreinoExercicio, $data)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $this->treinosService->atualizarExercicioNoTreino($idTreinoExercicio, $data, $usuario);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Exercício atualizado com sucesso']);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function removerExercicioDoTreino($idTreinoExercicio)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $this->treinosService->removerExercicioDoTreino($idTreinoExercicio, $usuario);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Exercício removido do treino com sucesso']);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Listagens
    public function listarExerciciosDoTreino($idTreino)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            // CORREÇÃO: Query simplificada usando apenas a tabela exercicios
            $stmt = $this->db->prepare("
                    SELECT 
                        te.idTreino_Exercicio,
                        te.idTreino,
                        te.idExercicio,
                        te.series,
                        te.repeticoes,
                        te.carga,
                        te.descanso,
                        te.ordem,
                        te.observacoes,
                        te.data_criacao,
                        te.data_ultima_modificacao,
                        -- Dados do exercício
                        e.nome,
                        e.grupoMuscular,
                        e.descricao,
                        e.tipo_exercicio,
                        e.visibilidade,
                        e.idPersonal,
                        -- Vídeos
                        v.url as video_url
                    FROM treino_exercicio te
                    INNER JOIN exercicios e ON te.idExercicio = e.idExercicio
                    LEFT JOIN videos v ON te.idExercicio = v.idExercicio
                    WHERE te.idTreino = ?
                    ORDER BY te.ordem, te.idTreino_Exercicio
                ");

            $stmt->execute([$idTreino]);
            $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // CORREÇÃO: Formatar os dados para o frontend
            $exerciciosFormatados = array_map(function ($ex) {
                return [
                    'id' => $ex['idTreino_Exercicio'],
                    'idTreino' => $ex['idTreino'],
                    'idExercicio' => $ex['idExercicio'],
                    'nome' => $ex['nome'],
                    'grupoMuscular' => $ex['grupoMuscular'],
                    'descricao' => $ex['descricao'],
                    'series' => $ex['series'],
                    'repeticoes' => $ex['repeticoes'],
                    'carga' => $ex['carga'],
                    'descanso' => $ex['descanso'],
                    'ordem' => $ex['ordem'],
                    'observacoes' => $ex['observacoes'],
                    'video_url' => $ex['video_url'],
                    'tipo_exercicio' => $ex['tipo_exercicio'],
                    'visibilidade' => $ex['visibilidade'],
                    'idPersonal' => $ex['idPersonal'],
                    'informacoes' => $ex['observacoes'] ?: $ex['descricao']
                ];
            }, $exercicios);

            http_response_code(200);
            echo json_encode(['success' => true, 'exercicios' => $exerciciosFormatados]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function listarTreinosAluno($idAluno = null)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            // Se não passou idAluno, pega do token
            $idAluno = $idAluno ?? ($usuario['idAluno'] ?? null);

            if (!$idAluno) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            $treinos = $this->treinosService->listarTreinosAluno($idAluno, $usuario);

            http_response_code(200);
            echo json_encode(['success' => true, ...$treinos]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function listarTreinosPersonal($idPersonal)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            $treinos = $this->treinosService->listarTreinosPersonal($idPersonal);

            http_response_code(200);
            echo json_encode(['success' => true, 'meusTreinos' => $treinos]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function listarTreinosAlunoComPersonal($idAluno)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['sub'] != $idAluno || $usuario['tipo'] !== 'aluno') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            $treinos = $this->treinosService->listarTreinosAlunoComPersonal($idAluno);

            http_response_code(200);
            echo json_encode(['success' => true, 'treinosAtribuidos' => $treinos]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Vínculos e Atribuições
    public function atribuirTreinoAluno($data)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas personais podem atribuir treinos']);
                return;
            }

            $idTreino = (int)($data['idTreino'] ?? 0);
            $idAluno = (int)($data['idAluno'] ?? 0);

            if ($idTreino === 0 || $idAluno === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'idTreino e idAluno são obrigatórios']);
                return;
            }

            $idPersonal = $usuario['sub'];
            $novoIdTreino = $this->treinosService->atribuirTreinoAluno($idTreino, $idAluno, $idPersonal);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Treino e exercícios duplicados e atribuídos ao aluno com sucesso',
                'idNovoTreino' => $novoIdTreino
            ]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function desvincularAluno($idPersonal, $idAluno)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['tipo'] !== 'personal' || $usuario['sub'] != $idPersonal) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas o personal dono do aluno pode desvincular']);
                return;
            }

            $this->treinosService->desvincularAluno($idAluno, $idPersonal, $usuario);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Aluno desvinculado e treinos atribuídos apagados com sucesso'
            ]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Treino Completo
    public function buscarTreinoCompleto($idTreino)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $treino = $this->treinosService->buscarTreinoCompleto($idTreino, $usuario);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'treino' => $treino,
                'exercicios' => $treino['exercicios']
            ]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Métodos adicionais do controller antigo
    public function listarMeusTreinosPersonal($idPersonal)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            $treinos = $this->treinosService->listarMeusTreinosPersonal($idPersonal);

            http_response_code(200);
            echo json_encode(['success' => true, 'meusTreinos' => $treinos]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function listarAlunosDoPersonal($idPersonal)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes alunos']);
                return;
            }

            $alunos = $this->treinosService->listarAlunosDoPersonal($idPersonal);

            http_response_code(200);
            echo json_encode(['success' => true, 'alunos' => $alunos]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function listarTreinosDoAlunoAtribuidos($idPersonal, $idAluno)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();

            if (!$usuario || $usuario['sub'] != $idPersonal || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver estes treinos']);
                return;
            }

            // Verificar se o aluno pertence a este personal
            $stmt = $this->db->prepare("SELECT idAluno FROM alunos WHERE idAluno = ? AND idPersonal = ?");
            $stmt->execute([$idAluno, $idPersonal]);
            $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$aluno) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Aluno não encontrado ou não vinculado a você']);
                return;
            }

            // Buscar treinos atribuídos a este aluno
            $stmt = $this->db->prepare("
                    SELECT t.* 
                    FROM treinos t
                    WHERE t.idPersonal = ? AND t.idAluno = ?
                    ORDER BY t.data_ultima_modificacao DESC
                ");
            $stmt->execute([$idPersonal, $idAluno]);
            $treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // GARANTIR que sempre retorne um array, mesmo que vazio
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'treinos' => $treinos ?: [] // Array vazio se for null
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'treinos' => [] // Sempre retornar array vazio em caso de erro
            ]);
        }
    }

    public function buscarExercicios($data)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $exercicios = $this->treinosService->buscarExercicios($data);

            http_response_code(200);
            echo json_encode(['success' => true, 'exercicios' => $exercicios]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function listarTreinosUsuario($data)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
                return;
            }

            $treinos = $this->treinosService->listarTreinosUsuario($data, $usuario);

            http_response_code(200);
            echo json_encode(['success' => true, 'treinos' => $treinos]);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function desatribuirTreinoDoAluno($idTreino)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario || $usuario['tipo'] !== 'personal') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas personais podem desatribuir treinos']);
                return;
            }

            // Verificar se o treino pertence a este personal
            $stmt = $this->db->prepare("SELECT idTreino FROM treinos WHERE idTreino = ? AND idPersonal = ?");
            $stmt->execute([$idTreino, $usuario['sub']]);
            $treino = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$treino) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Treino não encontrado ou você não tem permissão para desatribuí-lo']);
                return;
            }

            $this->treinosService->excluirTreino($idTreino, $usuario);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Treino desatribuído do aluno com sucesso']);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getHistoricoTreinos()
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['error' => 'Usuário não autenticado']);
                return;
            }

            $treinos = $this->treinosService->getHistoricoTreinos($usuario, 30);
            http_response_code(200);
            echo json_encode(['success' => true, 'treinos' => $treinos]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao obter histórico: ' . $e->getMessage()]);
        }
    }

    public function criarSessaoTreino()
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['error' => 'Usuário não autenticado']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);  // Dados do corpo da requisição
            if (!isset($data['idTreino'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID do treino é obrigatório']);
                return;
            }
            $idSessao = $this->treinosService->criarSessao($data['idTreino'], $usuario);
            http_response_code(201);
            echo json_encode(['success' => true, 'idSessao' => $idSessao]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function finalizarSessaoTreino($idSessao)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['error' => 'Usuário não autenticado']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);  // Dados do corpo: progresso, duracao, notas
            $success = $this->treinosService->finalizarSessao($idSessao, $data['progresso'] ?? [], $data['duracao'] ?? 0, $data['notas'] ?? null);
            if ($success) {
                http_response_code(200);
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Falha ao finalizar sessão']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getSessaoParaRetomar($idSessao)
    {
        try {
            $usuario = $this->obterUsuarioDoToken();
            if (!$usuario) {
                http_response_code(401);
                echo json_encode(['error' => 'Usuário não autenticado']);
                return;
            }
            $sessao = $this->treinosService->getSessaoParaRetomar($idSessao);
            http_response_code(200);
            echo json_encode(['success' => true, 'sessao' => $sessao]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
