<?php

    require_once __DIR__ . '/../Repositories/TreinosRepository.php';

    class TreinosService {
        private $repository;

        public function __construct() {
            $this->repository = new TreinosRepository();
        }

        // Validações
        public function validarDadosTreino($data) {
            $errors = [];

            if (empty(trim($data['nome'] ?? ''))) {
                $errors[] = "Nome é obrigatório";
            }

            if (empty(trim($data['criadoPor'] ?? ''))) {
                $errors[] = "CriadoPor é obrigatório";
            }

            if (empty(trim($data['tipo'] ?? ''))) {
                $errors[] = "Tipo é obrigatório";
            }

            $tiposValidos = ['Musculação', 'CrossFit', 'Calistenia', 'Pilates', 'Aquecimento', 'Treino Específico', 'Outros'];
            if (!in_array($data['tipo'], $tiposValidos)) {
                $errors[] = "Tipo inválido. Use: " . implode(', ', $tiposValidos);
            }

            if (is_null($data['idAluno'] ?? null) && is_null($data['idPersonal'] ?? null)) {
                $errors[] = "Informe idAluno ou idPersonal";
            }

            // Validar tipo de treino
            $tiposTreinoValidos = ['normal', 'adaptado'];
            $tipoTreino = $data['tipo_treino'] ?? 'normal';
            if (!in_array($tipoTreino, $tiposTreinoValidos)) {
                $errors[] = "Tipo de treino inválido. Use: " . implode(', ', $tiposTreinoValidos);
            }

            return $errors;
        }

        public function validarDadosExercicioTreino($data) {
            $errors = [];

            if (empty($data['idExercicio']) && empty($data['idExercAdaptado'])) {
                $errors[] = "Informe idExercicio ou idExercAdaptado";
            }

            if (empty($data['series']) || $data['series'] <= 0) {
                $errors[] = "Séries devem ser maior que zero";
            }

            if (empty($data['repeticoes']) || $data['repeticoes'] <= 0) {
                $errors[] = "Repetições devem ser maior que zero";
            }

            return $errors;
        }

        public function validarCompatibilidadeTreinoExercicio($idTreino, $exercicioData) {
            // Buscar tipo do treino
            $treino = $this->repository->buscarTreinoPorId($idTreino);
            if (!$treino) {
                return "Treino não encontrado";
            }

            $tipoTreino = $treino['tipo_treino'];
            
            // Buscar tipo do exercício
            if (empty($exercicioData['idExercicio'])) {
                return "ID do exercício não fornecido";
            }
            
            $exercicio = $this->repository->buscarExercicioPorId($exercicioData['idExercicio']);
            if (!$exercicio) {
                return "Exercício não encontrado";
            }
            
            $tipoExercicio = $exercicio['tipo_exercicio'] ?? 'normal';

            // Validar compatibilidade
            if ($tipoTreino === 'adaptado' && $tipoExercicio !== 'adaptado') {
                return "Treinos adaptados só podem conter exercícios adaptados";
            }

            if ($tipoTreino === 'normal' && $tipoExercicio === 'adaptado') {
                return "Treinos normais só podem conter exercícios normais";
            }

            return null; // Sem erros
        }

        // Operações de Treino
        public function criarTreino($data) {
            $errors = $this->validarDadosTreino($data);
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }

            $now = date('Y-m-d H:i:s');
            $dadosTreino = [
                'idAluno' => $data['idAluno'] ?? null,
                'idPersonal' => $data['idPersonal'] ?? null,
                'criadoPor' => trim($data['criadoPor']),
                'nome' => trim($data['nome']),
                'tipo' => $data['tipo'],
                'descricao' => $data['descricao'] ?? null,
                'data_criacao' => $now,
                'data_ultima_modificacao' => $now,
                'tipo_treino' => $data['tipo_treino'] ?? 'normal'
            ];

            $idTreino = $this->repository->criarTreino($dadosTreino);
            if (!$idTreino) {
                throw new Exception("Falha ao criar treino no banco de dados");
            }

            return $idTreino;
        }

        public function atualizarTreino($idTreino, $data, $usuario) {
            // Verificar permissão
            if (!$this->repository->verificarPermissaoTreino($idTreino, $usuario)) {
                throw new Exception("Você não tem permissão para editar este treino");
            }

            $errors = $this->validarDadosTreino(array_merge(
                $this->repository->buscarTreinoPorId($idTreino),
                $data
            ));
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }

            $now = date('Y-m-d H:i:s');
            $dadosAtualizacao = [
                'nome' => trim($data['nome']),
                'tipo' => $data['tipo'],
                'descricao' => $data['descricao'] ?? null,
                'data_ultima_modificacao' => $now,
                'tipo_treino' => $data['tipo_treino'] ?? 'normal'
            ];

            $success = $this->repository->atualizarTreino($idTreino, $dadosAtualizacao);
            if (!$success) {
                throw new Exception("Falha ao atualizar treino");
            }

            return true;
        }

        public function excluirTreino($idTreino, $usuario){
            // Verificar se o treino existe
            $treino = $this->repository->buscarTreinoPorId($idTreino);
            if (!$treino) {
                throw new Exception("Treino não encontrado", 404);
            }

            // Verificar permissão
            if (!$this->repository->verificarPermissaoTreino($idTreino, $usuario)) {
                throw new Exception("Você não tem permissão para excluir este treino");
            }

            // Iniciar transação para excluir exercícios e treino
            $this->repository->beginTransaction();

            try {
                error_log("🗑️  Iniciando exclusão do treino {$idTreino}...");

                // 1. Primeiro excluir os exercícios do treino
                $exercicios = $this->repository->buscarExerciciosDoTreino($idTreino);
                error_log("📊 Exercícios a excluir: " . count($exercicios));
                
                foreach ($exercicios as $exercicio) {
                    $success = $this->repository->removerExercicioDoTreino($exercicio['idTreino_Exercicio']);
                    if (!$success) {
                        throw new Exception("Falha ao excluir exercício ID: " . $exercicio['idTreino_Exercicio']);
                    }
                }
                error_log("✅ Exercícios excluídos");

                // 2. Agora excluir o treino
                $success = $this->repository->excluirTreino($idTreino);
                if (!$success) {
                    throw new Exception("Falha ao excluir treino do banco de dados");
                }
                error_log("✅ Treino excluído da tabela treinos");

                $this->repository->commit();
                error_log("🎉 Transação concluída com sucesso");

                return true;

            } catch (Exception $e) {
                $this->repository->rollBack();
                error_log("❌ Rollback realizado devido a erro: " . $e->getMessage());
                throw $e;
            }
        }

        public function desatribuirTreinoComHistorico($idTreino, $usuario){
            // Verificar se o treino existe
            $treino = $this->repository->buscarTreinoPorId($idTreino);
            if (!$treino) {
                throw new Exception("Treino não encontrado", 404);
            }

            // Verificar permissão
            if (!$this->repository->verificarPermissaoTreino($idTreino, $usuario)) {
                throw new Exception("Você não tem permissão para desatribuir este treino");
            }

            // Verificar se existem sessões/histórico
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM treino_sessao WHERE idTreino = ?");
            $stmt->execute([$idTreino]);
            $temHistorico = $stmt->fetchColumn() > 0;

            if ($temHistorico) {
                // Se tem histórico, apenas desvincular o aluno
                return $this->repository->desvincularTreinoDoAluno($idTreino);
            } else {
                // Se não tem histórico, excluir completamente
                return $this->excluirTreino($idTreino, $usuario);
            }
        }

        private function excluirTreinoHard($idTreino, $usuario) {
            // Exclusão completa (apenas se não tiver sessões)
            $this->repository->beginTransaction();

            try {
                // Excluir exercícios relacionados
                $exercicios = $this->repository->buscarExerciciosDoTreino($idTreino);
                foreach ($exercicios as $exercicio) {
                    $success = $this->repository->removerExercicioDoTreino($exercicio['idTreino_Exercicio']);
                    if (!$success) {
                        throw new Exception("Falha ao excluir exercícios do treino");
                    }
                }

                // Excluir treino
                $success = $this->repository->excluirTreino($idTreino);
                if (!$success) {
                    throw new Exception("Falha ao excluir treino");
                }

                $this->repository->commit();
                return true;

            } catch (Exception $e) {
                $this->repository->rollBack();
                throw $e;
            }
        }

        // Operações de Exercícios no Treino
        public function adicionarExercicioAoTreino($idTreino, $exercicioData, $usuario) {
            // Verificar permissão
            if (!$this->repository->verificarPermissaoTreino($idTreino, $usuario)) {
                throw new Exception("Você não tem permissão para modificar este treino");
            }

            // Validar compatibilidade
            $erroCompatibilidade = $this->validarCompatibilidadeTreinoExercicio($idTreino, $exercicioData);
            if ($erroCompatibilidade) {
                throw new Exception($erroCompatibilidade);
            }

            $errors = $this->validarDadosExercicioTreino($exercicioData);
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }

            $now = date('Y-m-d H:i:s');
            
            // CORREÇÃO: Usar apenas idExercicio, sem idExercAdaptado
            $dadosExercicio = [
                'idTreino' => $idTreino,
                'idExercicio' => $exercicioData['idExercicio'],
                'data_criacao' => $now,
                'data_ultima_modificacao' => $now,
                'series' => $exercicioData['series'],
                'repeticoes' => $exercicioData['repeticoes'],
                'carga' => $exercicioData['carga'] ?? null,
                'descanso' => $exercicioData['descanso'] ?? null,
                'ordem' => $exercicioData['ordem'] ?? 0,
                'observacoes' => $exercicioData['observacoes'] ?? null
            ];

            $success = $this->repository->adicionarExercicioAoTreino($dadosExercicio);
            if (!$success) {
                throw new Exception("Falha ao adicionar exercício ao treino");
            }

            // Atualizar data de modificação do treino
            $this->repository->atualizarDataModificacaoTreino($idTreino, $now);

            return true;
        }
        public function atualizarExercicioNoTreino($idTreinoExercicio, $data, $usuario) {
            // Buscar exercício e verificar permissão
            $exercicio = $this->repository->buscarExercicioTreinoPorId($idTreinoExercicio);
            if (!$exercicio) {
                throw new Exception("Exercício no treino não encontrado");
            }

            if (!$this->repository->verificarPermissaoTreino($exercicio['idTreino'], $usuario)) {
                throw new Exception("Você não tem permissão para editar este exercício");
            }

            $now = date('Y-m-d H:i:s');
            $dadosAtualizacao = [
                'series' => $data['series'] ?? $exercicio['series'],
                'repeticoes' => $data['repeticoes'] ?? $exercicio['repeticoes'],
                'carga' => $data['carga'] ?? $exercicio['carga'],
                'descanso' => $data['descanso'] ?? $exercicio['descanso'],
                'ordem' => $data['ordem'] ?? $exercicio['ordem'],
                'observacoes' => $data['observacoes'] ?? $exercicio['observacoes'],
                'data_ultima_modificacao' => $now
            ];

            $success = $this->repository->atualizarExercicioNoTreino($idTreinoExercicio, $dadosAtualizacao);
            if (!$success) {
                throw new Exception("Falha ao atualizar exercício");
            }

            // Atualizar data de modificação do treino
            $this->repository->atualizarDataModificacaoTreino($exercicio['idTreino'], $now);

            return true;
        }

        public function removerExercicioDoTreino($idTreinoExercicio, $usuario) {
            // Buscar exercício e verificar permissão
            $exercicio = $this->repository->buscarExercicioTreinoPorId($idTreinoExercicio);
            if (!$exercicio) {
                throw new Exception("Exercício não encontrado no treino");
            }

            if (!$this->repository->verificarPermissaoTreino($exercicio['idTreino'], $usuario)) {
                throw new Exception("Você não tem permissão para remover este exercício");
            }

            $success = $this->repository->removerExercicioDoTreino($idTreinoExercicio);
            if (!$success) {
                throw new Exception("Falha ao remover exercício");
            }

            // Atualizar data de modificação do treino
            $this->repository->atualizarDataModificacaoTreino($exercicio['idTreino'], date('Y-m-d H:i:s'));

            return true;
        }

        // Listagens
        public function listarTreinosAluno($idAluno) {
            return $this->repository->listarTreinosAluno($idAluno);
        }

        public function listarTreinosPersonal($idPersonal) {
            return $this->repository->listarTreinosPersonal($idPersonal);
        }

        public function listarTreinosAlunoComPersonal($idAluno) {
            return $this->repository->listarTreinosAlunoComPersonal($idAluno);
        }

        public function buscarTreinoCompleto($idTreino, $usuario) {
            $treino = $this->repository->buscarTreinoPorId($idTreino);
            if (!$treino) {
                throw new Exception("Treino não encontrado");
            }

            $exercicios = $this->repository->buscarExerciciosDoTreino($idTreino);
            $treino['exercicios'] = $exercicios;

            return $treino;
        }

        // Operações de Vínculo
        public function atribuirTreinoAluno($idTreino, $idAluno, $idPersonal) {
            // Verificar se aluno pertence ao personal
            $aluno = $this->repository->verificarVinculoAlunoPersonal($idAluno, $idPersonal);
            if (!$aluno) {
                throw new Exception("Aluno não encontrado ou não vinculado a você");
            }

            // Buscar treino original
            $treinoOriginal = $this->repository->buscarTreinoPorId($idTreino);
            if (!$treinoOriginal) {
                throw new Exception("Treino não encontrado");
            }

            // Iniciar transação
            $this->repository->beginTransaction();

            try {
                // Duplicar treino
                unset($treinoOriginal['idTreino']);
                $treinoOriginal['idAluno'] = $idAluno;
                $treinoOriginal['data_ultima_modificacao'] = date('Y-m-d H:i:s');

                $novoIdTreino = $this->repository->duplicarTreino($treinoOriginal, $treinoOriginal);
                if (!$novoIdTreino) {
                    throw new Exception("Falha ao duplicar treino");
                }

                // Duplicar exercícios
                $this->repository->duplicarExerciciosTreino($idTreino, $novoIdTreino);

                $this->repository->commit();
                return $novoIdTreino;

            } catch (Exception $e) {
                $this->repository->rollBack();
                throw $e;
            }
        }

        public function listarTreinosDoAlunoAtribuidos($idPersonal, $idAluno) {
            // Buscar treinos onde idPersonal é o personal logado E idAluno é o aluno específico
            $stmt = $this->db->prepare("
                SELECT t.* 
                FROM treinos t
                WHERE t.idPersonal = ? AND t.idAluno = ?
                ORDER BY t.data_ultima_modificacao DESC
            ");
            $stmt->execute([$idPersonal, $idAluno]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function desvincularAluno($idAluno, $idPersonal, $usuario) {
            // Verificar permissão
            if ($usuario['tipo'] !== 'personal' || $usuario['sub'] != $idPersonal) {
                throw new Exception("Apenas o personal dono do aluno pode desvincular");
            }

            // Verificar vínculo
            $aluno = $this->repository->verificarVinculoAlunoPersonal($idAluno, $idPersonal);
            if (!$aluno) {
                throw new Exception("Aluno não encontrado ou não vinculado a você");
            }

            // Iniciar transação
            $this->repository->beginTransaction();

            try {
                // Buscar e excluir treinos atribuídos
                $treinos = $this->repository->buscarTreinosAtribuidosAluno($idAluno, $idPersonal);
                foreach ($treinos as $treino) {
                    $this->excluirTreino($treino['idTreino'], $usuario);
                }

                // Desvincular aluno
                $success = $this->repository->desvincularAluno($idAluno);
                if (!$success) {
                    throw new Exception("Falha ao desvincular aluno");
                }

                $this->repository->commit();
                return true;

            } catch (Exception $e) {
                $this->repository->rollBack();
                throw $e;
            }
        }

        public function criarSessao($idTreino, $usuario) {
            $data = [
                'idTreino' => $idTreino,
                'idUsuario' => $usuario['sub'],
                'tipo_usuario' => $usuario['tipo'],
                'progresso_json' => ['exIndex' => 0, 'serieAtual' => 1, 'exercicios_concluidos' => []]
            ];
            $idSessao = $this->repository->criarSessaoTreino($data);
            if (!$idSessao) {
                throw new Exception("Falha ao criar sessão de treino");
            }
            return $idSessao;
        }

        public function finalizarSessao($idSessao, $progresso, $duracao, $notas = null) {
            $sessao = $this->repository->buscarSessaoPorId($idSessao);
            if (!$sessao) {
                throw new Exception("Sessão não encontrada");
            }
            
            // CORREÇÃO: Calcular porcentagem corretamente
            $porcentagem = $this->calcularPorcentagemProgresso($progresso, $sessao['idTreino']);
            
            // CORREÇÃO: Definir status baseado na porcentagem
            $status = ($porcentagem >= 90) ? 'concluido' : 'em_progresso'; // 90% ou mais = concluído
            
            $data = [
                'status' => $status,
                'progresso_json' => $progresso,
                'duracao_total' => $duracao,
                'notas' => $notas,
                'porcentagem_concluida' => $porcentagem  // CORREÇÃO: Garantir que seja salvo
            ];
            
            $success = $this->repository->atualizarSessaoTreino($idSessao, $data);
            if (!$success) {
                throw new Exception("Falha ao finalizar sessão");
            }
            
            // CORREÇÃO: Atualizar também a última sessão do treino
            $this->repository->atualizarUltimaSessaoTreino($sessao['idTreino'], $idSessao);
            
            return true;
        }

        private function calcularPorcentagemProgresso($progresso, $idTreino) {
            try {
                // Buscar exercícios do treino
                $exercicios = $this->repository->buscarExerciciosDoTreino($idTreino);
                
                if (empty($exercicios)) {
                    return 0;
                }
                
                $totalExercicios = count($exercicios);
                $exIndex = $progresso['exIndex'] ?? 0;
                $serieAtual = $progresso['serieAtual'] ?? 1;
                $exerciciosConcluidos = count($progresso['exercicios_concluidos'] ?? []);
                
                // CORREÇÃO: Se todos os exercícios estão concluídos, é 100%
                if ($exerciciosConcluidos >= $totalExercicios) {
                    return 100;
                }
                
                // CORREÇÃO: Se está no último exercício e na última série, é 100%
                if ($exIndex >= $totalExercicios) {
                    return 100;
                }
                
                // Progresso por exercícios concluídos
                $progressoExercicios = ($exerciciosConcluidos / $totalExercicios) * 100;
                
                // Progresso dentro do exercício atual
                $progressoNoExercicioAtual = 0;
                if ($exIndex < $totalExercicios && $exIndex >= 0) {
                    $exercicioAtual = $exercicios[$exIndex];
                    $seriesTotais = $exercicioAtual['series'] ?? 1;
                    
                    if ($seriesTotais > 0) {
                        // CORREÇÃO: sérieAtual - 1 porque começamos na série 1
                        $progressoNoExercicioAtual = (($serieAtual - 1) / $seriesTotais) * 100;
                    }
                }
                
                // CORREÇÃO: Cálculo mais preciso
                // Peso maior para exercícios concluídos, menor para progresso dentro do exercício
                if ($exerciciosConcluidos > 0) {
                    $porcentagemTotal = $progressoExercicios + ($progressoNoExercicioAtual * 0.3);
                } else {
                    $porcentagemTotal = $progressoNoExercicioAtual;
                }
                
                // CORREÇÃO: Garantir que não passe de 100% e arredondar
                return min(round($porcentagemTotal), 100);
                
            } catch (Exception $e) {
                error_log("Erro ao calcular porcentagem: " . $e->getMessage());
                return 0;
            }
        }

        public function getHistoricoTreinos($usuario, $dias = 30) {
            return $this->repository->buscarHistoricoTreinos($usuario['sub'], $usuario['tipo'], $dias);
        }

        public function getSessaoParaRetomar($idSessao) {
            try {
                error_log("Buscando sessão: " . $idSessao);
        
                $sessao = $this->repository->buscarSessaoPorId($idSessao);
                error_log("Sessão encontrada: " . json_encode($sessao));
                
                if (!$sessao) {
                    throw new Exception("Sessão não encontrada");
                }
                
                // Buscar treino
                $treino = $this->repository->buscarTreinoPorId($sessao['idTreino']);
                error_log("Treino encontrado: " . json_encode($treino));
                
                if (!$treino) {
                    throw new Exception("Treino não encontrado");
                }
                
                // Buscar exercícios
                $exercicios = $this->repository->buscarExerciciosDoTreino($sessao['idTreino']);
                error_log("Exercícios encontrados: " . count($exercicios));
                
                $treino['exercicios'] = $exercicios;
                
                // Processar progresso
                $progresso = json_decode($sessao['progresso_json'], true) ?? [
                    'exIndex' => 0, 
                    'serieAtual' => 1, 
                    'exercicios_concluidos' => []
                ];
                
                return [
                    'sessao' => $sessao,
                    'treino' => $treino,
                    'progresso' => $progresso
                ];
                
            } catch (Exception $e) {
                error_log("Erro em getSessaoParaRetomar: " . $e->getMessage());
                throw $e;
            }
        }
    }

?>