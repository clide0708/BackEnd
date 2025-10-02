<?php

    namespace App\Services;

    use App\Repositories\PerfilRepository;

    class PerfilService {
        private $perfilRepository;

        public function __construct() {
            $this->perfilRepository = new PerfilRepository();
        }

        // Métodos GET para perfis
        public function getPerfilAluno($idAluno, $idUsuarioLogado, $tipoUsuarioLogado) {
            // Lógica de autorização para PerfilService
            // Um aluno só pode ver o próprio perfil
            // Um personal pode ver o perfil de seus alunos vinculados
            // Um dev/academia pode ver qualquer perfil de aluno
            if ($tipoUsuarioLogado === 'aluno' && $idUsuarioLogado != $idAluno) {
                return null; // Acesso negado
            }
            if ($tipoUsuarioLogado === 'personal') {
                // Verificar se o aluno está vinculado a este personal
                if (!$this->perfilRepository->isAlunoVinculadoAoPersonal($idAluno, $idUsuarioLogado)) {
                    return null; // Acesso negado
                }
            }
            return $this->perfilRepository->getAlunoById($idAluno);
        }

        public function getPerfilPersonal($idPersonal) {
            return $this->perfilRepository->getPersonalById($idPersonal);
        }

        public function getPerfilAcademia($idAcademia) {
            return $this->perfilRepository->getAcademiaById($idAcademia);
        }

        public function getPerfilDev($idDev) {
            return $this->perfilRepository->getDevById($idDev);
        }

        // Métodos POST/PUT para perfis (completar/atualizar)
        public function createOrUpdatePerfilAluno($idAluno, $data) {
            // Validação e lógica de negócio antes de salvar/atualizar
            // Ex: Validar altura, gênero, meta, etc.
            if (isset($data['altura']) && !is_numeric($data['altura'])) {
                return ['success' => false, 'error' => 'Altura deve ser um número.'];
            }
            if (isset($data['genero']) && !in_array($data['genero'], ['Masculino', 'Feminino', 'Outro'])) {
                return ['success' => false, 'error' => 'Gênero inválido.'];
            }
            // Adicione mais validações conforme necessário

            return $this->perfilRepository->updateAlunoPerfil($idAluno, $data);
        }

        public function createOrUpdatePerfilPersonal($idPersonal, $data) {
            // Validação e lógica de negócio antes de salvar/atualizar
            if (isset($data['idade']) && !is_numeric($data['idade'])) {
                return ['success' => false, 'error' => 'Idade deve ser um número.'];
            }
            if (isset($data['genero']) && !in_array($data['genero'], ['Masculino', 'Feminino', 'Outro'])) {
                return ['success' => false, 'error' => 'Gênero inválido.'];
            }
            // Adicione mais validações conforme necessário

            return $this->perfilRepository->updatePersonalPerfil($idPersonal, $data);
        }

        public function createOrUpdatePerfilAcademia($idAcademia, $data) {
            // Validação e lógica de negócio antes de salvar/atualizar
            // Ex: Validar endereço, telefone, etc.
            return $this->perfilRepository->updateAcademiaPerfil($idAcademia, $data);
        }

        public function updatePerfilDev($idDev, $data) {
            // Validação e lógica de negócio antes de salvar/atualizar
            return $this->perfilRepository->updateDevPerfil($idDev, $data);
        }

        // Métodos para gerenciamento de planos
        public function getPlanoUsuario($idUsuario, $tipoUsuario) {
            return $this->perfilRepository->getPlanoUsuario($idUsuario, $tipoUsuario);
        }

        public function trocarPlano($idUsuario, $tipoUsuario, $idNovoPlano) {
            // Lógica de negócio para troca de plano
            // 1. Verificar se o novo plano existe e é válido para o tipo de usuário
            $novoPlano = $this->perfilRepository->getPlanoById($idNovoPlano);
            if (!$novoPlano || $novoPlano['tipo_usuario'] !== $tipoUsuario) {
                return ['success' => false, 'error' => 'Plano inválido ou não disponível para seu tipo de usuário.'];
            }

            // 2. Cancelar a assinatura atual (se houver)
            $this->perfilRepository->cancelarAssinaturaAtual($idUsuario, $tipoUsuario);

            // 3. Criar nova assinatura
            $result = $this->perfilRepository->criarNovaAssinatura($idUsuario, $tipoUsuario, $idNovoPlano, 'pendente');
            if ($result) {
                // Atualizar idPlano na tabela do usuário
                $this->perfilRepository->updateUsuarioPlano($idUsuario, $tipoUsuario, $idNovoPlano);
                return ['success' => true, 'message' => 'Solicitação de troca de plano enviada. Aguardando pagamento.'];
            } else {
                return ['success' => false, 'error' => 'Erro ao solicitar troca de plano.'];
            }
        }

        public function cancelarPlano($idUsuario, $tipoUsuario) {
            // Lógica de negócio para cancelar plano
            // Mudar status da assinatura para 'cancelada'
            $result = $this->perfilRepository->cancelarAssinaturaAtual($idUsuario, $tipoUsuario);
            if ($result) {
                // Atribuir plano básico gratuito (se aplicável)
                $idPlanoBasico = $this->perfilRepository->getPlanoBasicoId($tipoUsuario);
                if ($idPlanoBasico) {
                    $this->perfilRepository->updateUsuarioPlano($idUsuario, $tipoUsuario, $idPlanoBasico);
                    $this->perfilRepository->criarNovaAssinatura($idUsuario, $tipoUsuario, $idPlanoBasico, 'ativa');
                }
                return ['success' => true, 'message' => 'Plano cancelado com sucesso.'];
            } else {
                return ['success' => false, 'error' => 'Erro ao cancelar plano ou nenhum plano ativo encontrado.'];
            }
        }

        public function excluirConta($idUsuario, $tipoUsuario) {
            // Lógica de negócio para exclusão de conta (soft delete)
            $result = $this->perfilRepository->softDeleteConta($idUsuario, $tipoUsuario);
            if ($result['success']) {
                // Se for personal, desvincular alunos e treinos
                if ($tipoUsuario === 'personal') {
                    $this->perfilRepository->desvincularAlunosDoPersonal($idUsuario);
                    // Treinos criados por personal não são apagados, apenas ficam sem um personal ativo
                }
                return ['success' => true, 'message' => 'Conta marcada como excluída com sucesso.'];
            } else {
                return ['success' => false, 'error' => 'Erro ao excluir conta: ' . $result['error']];
            }
        }

        // Métodos para listar alunos de um personal
        public function getAlunosDoPersonal($idPersonal) {
            return $this->perfilRepository->getAlunosDoPersonal($idPersonal);
        }

        // Métodos para listar treinos criados por um personal
        public function getTreinosCriadosPorPersonal($idPersonal) {
            return $this->perfilRepository->getTreinosCriadosPorPersonal($idPersonal);
        }
    }

?>