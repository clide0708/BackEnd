<?php

require_once __DIR__ . '/../Repositories/PerfilRepository.php';

class PerfilService
{
    private $perfilRepository;

    public function __construct()
    {
        $this->perfilRepository = new PerfilRepository();
    }

    // Métodos GET para perfis
    public function getPerfilAluno($idAluno, $idUsuarioLogado, $tipoUsuarioLogado)
    {
        if ($tipoUsuarioLogado === 'aluno' && $idUsuarioLogado != $idAluno) {
            return null;
        }
        if ($tipoUsuarioLogado === 'personal') {
            if (!$this->perfilRepository->isAlunoVinculadoAoPersonal($idAluno, $idUsuarioLogado)) {
                return null;
            }
        }
        return $this->perfilRepository->getAlunoById($idAluno);
    }

    public function getPerfilPersonal($idPersonal)
    {
        return $this->perfilRepository->getPersonalById($idPersonal);
    }

    public function getPerfilAcademia($idAcademia)
    {
        return $this->perfilRepository->getAcademiaById($idAcademia);
    }

    public function getPerfilDev($idDev)
    {
        return $this->perfilRepository->getDevById($idDev);
    }

    // Métodos POST/PUT
    public function createOrUpdatePerfilAluno($idAluno, $data)
    {
        if (isset($data['altura']) && !is_numeric($data['altura'])) {
            return ['success' => false, 'error' => 'Altura deve ser um número.'];
        }
        if (isset($data['genero']) && !in_array($data['genero'], ['Masculino', 'Feminino', 'Outro'])) {
            return ['success' => false, 'error' => 'Gênero inválido.'];
        }
        return $this->perfilRepository->updateAlunoPerfil($idAluno, $data);
    }

    public function createOrUpdatePerfilPersonal($idPersonal, $data)
    {
        if (isset($data['idade']) && !is_numeric($data['idade'])) {
            return ['success' => false, 'error' => 'Idade deve ser um número.'];
        }
        if (isset($data['genero']) && !in_array($data['genero'], ['Masculino', 'Feminino', 'Outro'])) {
            return ['success' => false, 'error' => 'Gênero inválido.'];
        }
        return $this->perfilRepository->updatePersonalPerfil($idPersonal, $data);
    }

    public function createOrUpdatePerfilAcademia($idAcademia, $data)
    {
        return $this->perfilRepository->updateAcademiaPerfil($idAcademia, $data);
    }

    public function updatePerfilDev($idDev, $data)
    {
        return $this->perfilRepository->updateDevPerfil($idDev, $data);
    }

    // Métodos de planos
    public function getPlanoUsuario($idUsuario, $tipoUsuario)
    {
        return $this->perfilRepository->getPlanoUsuario($idUsuario, $tipoUsuario);
    }

    public function trocarPlano($idUsuario, $tipoUsuario, $idNovoPlano)
    {
        $novoPlano = $this->perfilRepository->getPlanoById($idNovoPlano);
        if (!$novoPlano || $novoPlano['tipo_usuario'] !== $tipoUsuario) {
            return ['success' => false, 'error' => 'Plano inválido ou não disponível para seu tipo de usuário.'];
        }
        $this->perfilRepository->cancelarAssinaturaAtual($idUsuario, $tipoUsuario);
        $result = $this->perfilRepository->criarNovaAssinatura($idUsuario, $tipoUsuario, $idNovoPlano, 'pendente');
        if ($result) {
            $this->perfilRepository->updateUsuarioPlano($idUsuario, $tipoUsuario, $idNovoPlano);
            return ['success' => true, 'message' => 'Solicitação de troca de plano enviada. Aguardando pagamento.'];
        }
        return ['success' => false, 'error' => 'Erro ao solicitar troca de plano.'];
    }

    public function cancelarPlano($idUsuario, $tipoUsuario)
    {
        $result = $this->perfilRepository->cancelarAssinaturaAtual($idUsuario, $tipoUsuario);
        if ($result) {
            $idPlanoBasico = $this->perfilRepository->getPlanoBasicoId($tipoUsuario);
            if ($idPlanoBasico) {
                $this->perfilRepository->updateUsuarioPlano($idUsuario, $tipoUsuario, $idPlanoBasico);
                $this->perfilRepository->criarNovaAssinatura($idUsuario, $tipoUsuario, $idPlanoBasico, 'ativa');
            }
            return ['success' => true, 'message' => 'Plano cancelado com sucesso.'];
        }
        return ['success' => false, 'error' => 'Erro ao cancelar plano ou nenhum plano ativo encontrado.'];
    }

    public function excluirConta($idUsuario, $tipoUsuario)
    {
        $result = $this->perfilRepository->softDeleteConta($idUsuario, $tipoUsuario);
        if ($result['success']) {
            if ($tipoUsuario === 'personal') {
                $this->perfilRepository->desvincularAlunosDoPersonal($idUsuario);
            }
            return ['success' => true, 'message' => 'Conta marcada como excluída com sucesso.'];
        }
        return ['success' => false, 'error' => 'Erro ao excluir conta: ' . $result['error']];
    }

    public function getAlunosDoPersonal($idPersonal)
    {
        return $this->perfilRepository->getAlunosDoPersonal($idPersonal);
    }

    public function getTreinosCriadosPorPersonal($idPersonal)
    {
        return $this->perfilRepository->getTreinosCriadosPorPersonal($idPersonal);
    }

    public function getUsuarioPorEmail($email, $usuarioLogado = null)
    {
        if (!$usuarioLogado) {
            return ['success' => false, 'error' => 'Usuário não autenticado.'];
        }

        $usuario = $this->perfilRepository->findByEmail($email);

        if (!$usuario) {
            return ['success' => false, 'error' => 'Usuário não encontrado.'];
        }

        // // regra de acesso: aluno só vê o próprio perfil
        // if ($usuarioLogado['tipo'] === 'aluno' && $usuarioLogado['sub'] != $usuario['id']) {
        //     return ['success' => false, 'error' => 'Acesso negado.'];
        // }

        return ['success' => true, 'data' => $usuario];
    }
}
