<?php

    namespace App\Services;

    use App\Repositories\PerfilRepository;

    class PerfilService {
        private $perfilRepository;

        public function __construct() {
            $this->perfilRepository = new PerfilRepository();
        }

        public function getPerfilAluno($idAluno) {
            return $this->perfilRepository->getAlunoById($idAluno);
        }

        public function createPerfilAluno($data) {
            // Validação e lógica de negócio antes de salvar
            return $this->perfilRepository->createAlunoPerfil($data);
        }

        public function updatePerfilAluno($idAluno, $data) {
            // Validação e lógica de negócio antes de atualizar
            return $this->perfilRepository->updateAlunoPerfil($idAluno, $data);
        }

        public function getPerfilPersonal($idPersonal) {
            return $this->perfilRepository->getPersonalById($idPersonal);
        }

        public function createPerfilPersonal($data) {
            // Validação e lógica de negócio antes de salvar
            return $this->perfilRepository->createPersonalPerfil($data);
        }

        public function updatePerfilPersonal($idPersonal, $data) {
            // Validação e lógica de negócio antes de atualizar
            return $this->perfilRepository->updatePersonalPerfil($idPersonal, $data);
        }
    }

?>