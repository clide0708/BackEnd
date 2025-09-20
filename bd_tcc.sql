-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 08-Set-2025 às 02:39
-- Versão do servidor: 8.0.31
-- versão do PHP: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `bd_tcc`
--
CREATE DATABASE IF NOT EXISTS `bd_tcc` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `bd_tcc`;

-- --------------------------------------------------------

--
-- Estrutura da tabela `agendamentos`
--

DROP TABLE IF EXISTS `agendamentos`;
CREATE TABLE IF NOT EXISTS `agendamentos` (
  `idAgendamento` int NOT NULL AUTO_INCREMENT,
  `idPersonal` int NOT NULL,
  `idAluno` int NOT NULL,
  `data_hora` datetime NOT NULL,
  `local` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`idAgendamento`),
  KEY `FK_Agend_Aluno` (`idAluno`),
  KEY `FK_Agend_Personal` (`idPersonal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `agendamentos`
--

TRUNCATE TABLE `agendamentos`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `agua`
--

DROP TABLE IF EXISTS `agua`;
CREATE TABLE IF NOT EXISTS `agua` (
  `idAgua` int NOT NULL AUTO_INCREMENT,
  `quantidade` int DEFAULT NULL,
  `data` datetime DEFAULT NULL,
  `idaluno` int NOT NULL,
  PRIMARY KEY (`idAgua`),
  KEY `FK_Agua_Aluno` (`idaluno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `agua`
--

TRUNCATE TABLE `agua`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `alunos`
--

DROP TABLE IF EXISTS `alunos`;
CREATE TABLE IF NOT EXISTS `alunos` (
  `idAluno` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cpf` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `rg` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `senha` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numTel` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `data_cadastro` datetime NOT NULL,
  `statusPlano` enum('Ativo','Pendente','Desativado','Cancelado','A verificar') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'A verificar',
  `idPersonal` int DEFAULT NULL,
  PRIMARY KEY (`idAluno`),
  UNIQUE KEY `cpf` (`cpf`),
  UNIQUE KEY `uq_numTel` (`numTel`),
  UNIQUE KEY `uq_rg` (`rg`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `FK_Alunos_Personal` (`idPersonal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `alunos`
--

TRUNCATE TABLE `alunos`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `exercadaptados`
--

DROP TABLE IF EXISTS `exercadaptados`;
CREATE TABLE IF NOT EXISTS `exercadaptados` (
  `idExercAdaptado` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grupoMuscular` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cadastradoPor` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`idExercAdaptado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `exercadaptados`
--

TRUNCATE TABLE `exercadaptados`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `exercicios`
--

DROP TABLE IF EXISTS `exercicios`;
CREATE TABLE IF NOT EXISTS `exercicios` (
  `idExercicio` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grupoMuscular` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cadastradoPor` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`idExercicio`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `exercicios`
--

TRUNCATE TABLE `exercicios`;
--
-- Extraindo dados da tabela `exercicios`
--

INSERT INTO `exercicios` (`idExercicio`, `nome`, `grupoMuscular`, `descricao`, `cadastradoPor`) VALUES
(1, 'Chest Press', 'peitoral', 'Sente-se na máquina com as costas apoiadas, segure as alças na altura do peito e empurre controladamente até estender os braços; retorne devagar.', NULL),
(2, 'Máquina Declinada Supino e Crucifixo', 'peitoral', 'Deite-se em banco declinado; no supino empurre a barra/pegada para cima até estender os braços; no crucifixo abra os braços com leve flexão nos cotovelos e retorne controlado.', NULL),
(3, 'Supino com Halteres', 'peitoral', 'Deite-se no banco, segure halteres alinhados ao peito, empurre para cima até braços estendidos e desça controladamente.', NULL),
(4, 'Crucifixo com Halteres', 'peitoral', 'Deite-se, braços semiflexionados; abra lateralmente até sentir alongamento no peitoral e traga os halteres de volta com controle.', NULL),
(5, 'Flexão de Braço', 'peitoral', 'Posição de prancha com mãos alinhadas ao peito; flexione os cotovelos até o peito quase tocar o chão e empurre de volta até estender os braços.', NULL),
(6, 'Fly', 'peitoral', 'Em banco reto ou inclinado, com leve flexão nos cotovelos abra os braços lateralmente e junte-os à frente contraindo o peitoral.', NULL),
(7, 'Paralela', 'peitoral', 'Suspenda-se nas barras paralelas, incline o tronco levemente à frente, desça flexionando os cotovelos e empurre até estender os braços.', NULL),
(8, 'Máquina de Crucifixo Inclinada', 'peitoral', 'Sente-se na máquina inclinada, segure as alças e junte-as à frente do peito com movimento controlado e ênfase na contração.', NULL),
(9, 'Supino com Barra', 'peitoral', 'Deite-se no banco, segure a barra à largura dos ombros, desça ao peito mantendo controle e empurre até estender os braços.', NULL),
(10, 'Cross Over', 'peitoral', 'Em pé entre os cabos, segure as alças e leve-as à frente do corpo com leve arco no tronco, cruzando as mãos se desejar foco na parte inferior do peitoral.', NULL),
(11, 'Voador para Peitoral', 'peitoral', 'Sente-se no peck-deck, apoie os braços/antebraços e junte as alças concentrando a contração no centro do peitoral.', NULL),
(12, 'Tríceps Pulley', 'triceps', 'Em pé, segure a barra no pulley alto e estenda os cotovelos até travá-los; controle na volta para manter tensão no tríceps.', NULL),
(13, 'Tríceps Corda', 'triceps', 'No pulley alto com corda, estenda os antebraços separando as pontas da corda no final da extensão para maior amplitude.', NULL),
(14, 'Tríceps Unilateral', 'triceps', 'Segure o acessório com uma mão no pulley e estenda o braço mantendo o cotovelo imóvel; faça o movimento controlado e simétrico no outro lado.', NULL),
(15, 'Rosca Francesa no Cross', 'triceps', 'De pé no cross, segure a barra/pegada e flexione os cotovelos levando a carga em direção à testa ou têmpora; estenda até travar os braços.', NULL),
(16, 'Tríceps Coice Unilateral no Cross', 'triceps', 'Incline-se com tronco estável, segure o cabo e estenda o antebraço para trás mantendo o cotovelo fixo.', NULL),
(17, 'Rosca Francesa Unilateral com Halter', 'triceps', 'Sentado ou deitado, segure um halter com uma mão e faça a extensão do antebraço até estender totalmente o braço.', NULL),
(18, 'Rosca Francesa Unilateral no Cross', 'triceps', 'No cross, use pegada unilateral para flexionar o cotovelo e estender o antebraço, mantendo o tronco firme.', NULL),
(19, 'Tríceps Coice Unilateral com Halter', 'triceps', 'Apoie o tronco (banco ou inclinação), mantenha o cotovelo fixo e estenda o antebraço para trás até contrair o tríceps.', NULL),
(20, 'Tríceps Banco', 'triceps', 'Mãos apoiadas no banco atrás do quadril, flexione os cotovelos descendo o corpo e empurre até estender os braços.', NULL),
(21, 'Tríceps Garganta', 'triceps', 'Variação de extensão de tríceps com barra/pegada levando a carga em direção à garganta/peito alto e estendendo os cotovelos com controle.', NULL),
(22, 'Tríceps Testa no Cross', 'triceps', 'De pé no cross, segure a barra e flexione os cotovelos levando a barra em direção à testa; estenda controladamente.', NULL),
(23, 'Tríceps Testa com Barra', 'triceps', 'Deitado no banco, segure a barra com pegada pronada, flexione os cotovelos até a barra quase tocar a testa e estenda os braços.', NULL),
(24, 'Tríceps Máquina Articulada', 'triceps', 'Sente-se e ajuste o aparelho; empurre as alças até estender totalmente os braços e retorne com controle.', NULL),
(25, 'Tríceps Coice Bilateral no Cross', 'triceps', 'Incline o tronco, segure as alças e estenda ambos os braços simultaneamente para trás, mantendo os cotovelos quase fixos.', NULL),
(26, 'Desenvolvimento com Halteres e Barra', 'ombros', 'Sentado ou em pé, empurre halteres ou barra acima da cabeça até extensão total dos braços e desça controladamente.', NULL),
(27, 'Desenvolvimento na Máquina Pegada Tradicional e Convergente', 'ombros', 'Sente-se na máquina, alinhe a pegada e empurre as alças acima da cabeça até estender os braços; controle na descida.', NULL),
(28, 'Desenvolvimento Arnold', 'ombros', 'Sente-se com halteres à frente (palmas voltadas para você) e, ao subir, faça a rotação do pulso finalizando com palmas para frente.', NULL),
(29, 'Elevação Lateral com Halteres', 'ombros', 'Em pé, levante os halteres lateralmente até a altura dos ombros com leve flexão no cotovelo; mantenha movimento controlado.', NULL),
(30, 'Elevação Lateral no Cross', 'ombros', 'Segure o cabo em posição baixa e eleve o braço lateralmente até a altura do ombro, controlando a descida.', NULL),
(31, 'Elevação Frontal com Halteres', 'ombros', 'Em pé, levante os halteres à frente do corpo até a altura dos ombros com os braços estendidos e movimento controlado.', NULL),
(32, 'Elevação Frontal no Cross', 'ombros', 'Segure o cabo em posição baixa e eleve o braço à frente até a altura do ombro, mantendo o controle na descida.', NULL),
(33, 'Remada Alta com Barra', 'ombros', 'Segure a barra com pegada pronada, puxe-a até a altura do peito mantendo os cotovelos altos e desça controladamente.', NULL),
(34, 'Remada Alta no Cross', 'ombros', 'Segure as alças no cross, puxe-as para cima até o nível do peito com os cotovelos elevados, controlando o movimento de volta.', NULL),
(35, 'Crucifixo Invertido no Cross', 'ombros', 'Segure os cabos em posição baixa, abra os braços para trás com leve flexão nos cotovelos, contraindo a parte posterior dos ombros.', NULL),
(36, 'Crucifixo Invertido com Halteres', 'ombros', 'Incline o tronco para frente segurando halteres, abra os braços lateralmente com leve flexão no cotovelo e retorne controlado.', NULL),
(37, 'Face Pull', 'ombros', 'Segure a corda no pulley alto, puxe em direção ao rosto com cotovelos abertos para ativar o deltoide posterior e trapézio.', NULL),
(38, 'Rosca Francesa com Halter e Anilha', 'triceps', 'Segure o halter/anilha com as duas mãos acima da cabeça e flexione os cotovelos para trás, estendendo-os novamente com controle.', NULL),
(39, 'Voador Costas ou Crucifixo Invertido na Máquina', 'ombros', 'Sente-se na máquina, abra os braços para trás contraindo a musculatura posterior dos ombros e retorne devagar.', NULL),
(40, 'Rotação Interna e Externa de Ombro (Manguito Rotador)', 'ombros', 'Use o cabo para girar o braço internamente e externamente mantendo o cotovelo fixo, fortalecendo o manguito rotador.', NULL),
(41, 'Rosca Direta com Barra', 'biceps', 'Segure a barra com pegada supinada e flexione os cotovelos levando a barra até a altura dos ombros; desça controladamente.', NULL),
(42, 'Rosca Direta no Cross', 'biceps', 'Segure o cabo com pegada supinada e flexione o cotovelo puxando o cabo até o bíceps contrair; controle a descida.', NULL),
(43, 'Rosca Direta com Halteres', 'biceps', 'Com halteres em mãos, flexione os cotovelos levantando os pesos até a altura dos ombros e desça lentamente.', NULL),
(44, 'Rosca Martelo no Cross', 'biceps', 'Segure o cabo com pegada neutra e flexione o cotovelo puxando o cabo em direção ao ombro; controle o movimento de volta.', NULL),
(45, 'Rosca no Banco Scott', 'biceps', 'Sentado no banco scott, flexione os cotovelos levando a barra/halteres até contrair os bíceps e desça controladamente.', NULL),
(46, 'Rosca Alternada', 'biceps', 'Com halteres em cada mão, flexione um cotovelo de cada vez, girando o pulso ao subir; desça controladamente.', NULL),
(47, 'Rosca Inversa na Barra e no Cross Over', 'antebraco', 'Com pegada pronada, flexione os cotovelos levantando a barra ou puxando o cabo, focando no fortalecimento do antebraço.', NULL),
(48, 'Rosca Punho com Barra e no Cross Over', 'antebraco', 'Segure barra ou cabo e realize flexão e extensão dos punhos para fortalecer os músculos do antebraço.', NULL),
(49, 'Rosca Punho Invertido', 'antebraco', 'Segure a barra com pegada pronada e flexione os punhos para fortalecer os músculos extensores do antebraço.', NULL),
(50, 'Rolo de Pulso Wrist Roller', 'antebraco', 'Segure o rolo com pesos suspensos e enrole a corda movendo os punhos para cima e para baixo, fortalecendo o antebraço.', NULL),
(51, 'Barra Fixa', 'costas', 'Pendure-se na barra com pegada pronada e puxe o corpo até o queixo ultrapassar a barra; desça controladamente.', NULL),
(52, 'Puxador Frente e Costas', 'costas', 'Sentado no puxador, puxe a barra em direção ao peito ou atrás da nuca, mantendo as costas retas; controle a volta.', NULL),
(53, 'Remada Sentado no Puxador', 'costas', 'Sentado, puxe o cabo em direção ao abdômen, mantendo tronco estável e controle na extensão dos braços.', NULL),
(54, 'Remada Sentado no Cross', 'costas', 'Similar à remada no puxador, puxe os cabos em direção ao tronco mantendo os cotovelos próximos ao corpo.', NULL),
(55, 'Puxador no Cross Bilateral e Unilateral', 'costas', 'Segure um ou ambos os cabos e puxe em direção ao peito, controlando a extensão dos braços.', NULL),
(56, 'Remada na Máquina', 'costas', 'Sente-se na máquina e puxe as alças em direção ao corpo, concentrando na contração dos músculos das costas.', NULL),
(57, 'Remada Cavalo Livre', 'costas', 'Com barra, incline o tronco, puxe a barra em direção ao abdômen mantendo os cotovelos próximos ao corpo.', NULL),
(58, 'Remada Cavalo Máquina', 'costas', 'Sente-se e puxe a alavanca da máquina em direção ao corpo focando na contração dos músculos das costas.', NULL),
(59, 'Remada Curvada com Barra Livre', 'costas', 'Incline o tronco com barra e puxe em direção ao abdômen com cotovelos para trás; desça controladamente.', NULL),
(60, 'Remada Curvada no Cross Over', 'costas', 'Segure os cabos baixos e puxe-os em direção ao tronco com cotovelos próximos ao corpo; controle a volta.', NULL),
(61, 'Remada Unilateral com Halter (Serrote)', 'costas', 'Apoie um joelho e mão no banco, puxe o halter com a outra mão em direção ao tronco, mantendo o cotovelo próximo ao corpo.', NULL),
(62, 'Hiperextensão Lombar', 'costas', 'Posicione-se no banco para hiperextensão, flexione o tronco para frente e estenda-o para trabalhar a lombar.', NULL),
(63, 'Pull Over com Halter', 'costas', 'Deitado no banco, segure o halter acima do peito e leve-o para trás da cabeça, alongando o peitoral e costas.', NULL),
(64, 'Pulldown no Cross', 'costas', 'Puxe a barra do pulley alto até o peito, mantendo as costas retas e cotovelos alinhados ao tronco.', NULL),
(65, 'Abdominal Supra', 'abdomen', 'Deitado, eleve o tronco em direção aos joelhos, contraindo o abdômen superior.', NULL),
(66, 'Abdominal Infra', 'abdomen', 'Deitado, eleve as pernas estendidas em direção ao tronco, focando no abdômen inferior.', NULL),
(67, 'Abdominal Infra na Máquina', 'abdomen', 'Sente-se na máquina e eleve os joelhos em direção ao peito, ativando o abdômen inferior.', NULL),
(68, 'Abdominal Oblíquo', 'abdomen', 'Deitado, realize a flexão lateral do tronco para ativar os músculos oblíquos.', NULL),
(69, 'Abdominal Prancha', 'abdomen', 'Mantenha o corpo alinhado apoiado nos antebraços e ponta dos pés, contraindo o core por tempo determinado.', NULL),
(70, 'Abdominal Remador', 'abdomen', 'Deitado, eleve o tronco alternando o toque do cotovelo direito no joelho esquerdo e vice-versa.', NULL),
(71, 'Abdominal Lateral', 'abdomen', 'Deitado, flexione o tronco lateralmente para ativar os músculos do abdômen lateral.', NULL),
(72, 'Concha Abdominal ou Abdominal Canoa', 'abdomen', 'Sentado, incline o tronco para trás em V mantendo o equilíbrio para trabalhar o core.', NULL),
(73, 'Roda Abdominal', 'abdomen', 'Apoie as mãos na roda e estenda o corpo para frente, contraindo o abdômen para retornar.', NULL),
(74, 'Panturrilha Sentado na Máquina', 'panturrilha', 'Sente-se na máquina e eleve os calcanhares para trabalhar a panturrilha, controlando a descida.', NULL),
(75, 'Panturrilha em Pé', 'panturrilha', 'Em pé, eleve os calcanhares mantendo o equilíbrio para fortalecer a panturrilha.', NULL),
(76, 'Panturrilha no Leg Horizontal', 'panturrilha', 'Na máquina leg press horizontal, empurre a plataforma usando somente os dedos dos pés para ativar a panturrilha.', NULL),
(77, 'Panturrilha no Leg 45°', 'panturrilha', 'No leg press 45°, faça a extensão plantar elevando os calcanhares para ativar a panturrilha.', NULL),
(78, 'Panturrilha no Smith', 'panturrilha', 'Com barra no smith, faça a extensão plantar para fortalecer a panturrilha com carga adicional.', NULL),
(79, 'Agachamento Livre', 'quadriceps', 'Com barra apoiada nos ombros, flexione os joelhos e quadris descendo o corpo e volte à posição inicial.', NULL),
(80, 'Agachamento Sumô', 'quadriceps', 'Com pernas afastadas e pés virados para fora, agache mantendo a postura ereta e volte controladamente.', NULL),
(81, 'Agachamento no Hack Machine', 'quadriceps', 'Na máquina hack, execute o agachamento controlando o movimento para fortalecer as pernas.', NULL),
(82, 'Leg Press 45°', 'quadriceps', 'Empurre a plataforma inclinada usando os pés, estendendo os joelhos para ativar o quadríceps.', NULL),
(83, 'Cadeira Extensora', 'quadriceps', 'Sente-se e estenda os joelhos contra a resistência da máquina para trabalhar o quadríceps.', NULL),
(84, 'Afundo', 'quadriceps', 'Dê um passo à frente e flexione ambos os joelhos até o ângulo de 90°, suba controladamente.', NULL),
(85, 'Agachamento Búlgaro', 'quadriceps', 'Com um pé apoiado atrás em banco, agache com o outro mantendo o equilíbrio e volte à posição inicial.', NULL),
(86, 'Flexão de Quadril com Caneleira', 'quadriceps', 'De pé, flexione o quadril levantando a perna com caneleira para frente, trabalhando o quadríceps.', NULL),
(87, 'Passada e Avanço', 'quadriceps', 'Dê um passo largo à frente, flexione o joelho e volte para trás, alternando as pernas.', NULL),
(88, 'Cadeira Adutora', 'adutores', 'Sente-se e pressione as coxas para dentro contra a resistência da máquina para trabalhar os adutores.', NULL),
(89, 'Adução no Cross Over', 'adutores', 'Com cabos baixos, puxe as pernas para dentro cruzando na frente do corpo para ativar os adutores.', NULL),
(90, 'Mesa Flexora', 'posteriordecoxa', 'Deitado na máquina, flexione os joelhos contra a resistência para trabalhar os músculos posteriores da coxa.', NULL),
(91, 'Cadeira Flexora', 'posteriordecoxa', 'Sentado, flexione os joelhos para trás contra a resistência da máquina para fortalecer o posterior da coxa.', NULL),
(92, 'Flexor Apoio', 'posteriordecoxa', 'Apoiado na máquina, flexione os joelhos elevando os calcanhares para trabalhar o posterior da coxa.', NULL),
(93, 'Stiff com Barra', 'posteriordecoxa', 'Com barra, incline o tronco à frente mantendo as pernas quase estendidas para alongar e fortalecer o posterior da coxa.', NULL),
(94, 'Leg Press Horizontal', 'quadriceps', 'Empurre a plataforma horizontalmente com os pés, estendendo os joelhos para ativar o quadríceps.', NULL),
(95, 'Cadeira Extensora Unilateral', 'quadriceps', 'Sente-se e estenda um joelho de cada vez contra a resistência da máquina para trabalhar o quadríceps unilateralmente.', NULL),
(96, 'Rosca Concentrada com Halteres', 'biceps', 'Sentado, apoie o braço e faça a flexão do cotovelo para concentrar o trabalho no bíceps.', NULL),
(97, 'Rosca Martelo com Halteres', 'biceps', 'Com halteres, faça a flexão dos cotovelos mantendo as palmas voltadas para dentro, ativando o braquial.', NULL),
(98, 'Rosca Martelo com Barra', 'biceps', 'Com barra, faça a flexão dos cotovelos com pegada neutra para fortalecer o bíceps e antebraço.', NULL),
(99, 'Rosca 21', 'biceps', 'Execute 7 repetições na metade inferior, 7 na metade superior e 7 completas para estimular o bíceps.', NULL),
(100, 'Encolhimento de Trapézio com Halteres', 'trapezio', 'Com halteres nas mãos, eleve os ombros em direção às orelhas contraindo o trapézio.', NULL),
(101, 'Encolhimento de Trapézio no Cross Over', 'trapezio', 'Segure os cabos e eleve os ombros contraindo o trapézio mantendo o movimento controlado.', NULL),
(102, 'Encolhimento de Trapézio no Smith', 'trapezio', 'Com barra no smith, eleve os ombros para trabalhar o trapézio mantendo postura ereta.', NULL),
(103, 'Leg Press 90°', 'posteriordecoxa', 'Empurre a plataforma inclinada a 90° para ativar os músculos posteriores das pernas.', NULL),
(104, 'Levantamento Terra', 'posteriordecoxa', 'Com barra no chão, levante mantendo a coluna neutra e usando força dos posteriores e glúteos.', NULL),
(105, 'Elevação Pélvica com Barra', 'gluteos', 'Deitado, eleve o quadril com a barra apoiada para ativar os glúteos e posteriores.', NULL),
(106, 'Elevação Pélvica na Máquina', 'gluteos', 'Na máquina, eleve o quadril contra a resistência para fortalecer glúteos e posterior de coxa.', NULL),
(107, 'Elevação Pélvica Apoiado no Solo', 'gluteos', 'Deitado no chão, eleve o quadril contraindo os glúteos mantendo os pés apoiados.', NULL),
(108, 'Coice para Glúteos no Cross Over', 'gluteos', 'Com caneleira, realize o movimento de coice para trás, ativando os glúteos no cross over.', NULL),
(109, 'Extensão de Glúteo com Caneleira', 'gluteos', 'De joelhos, estenda a perna com caneleira para trás contraindo o glúteo máximo.', NULL),
(110, 'Glúteo 4 Apoios ou Coice com Caneleira', 'gluteos', 'Em posição de 4 apoios, eleve a perna para trás com caneleira ativando os glúteos.', NULL),
(111, 'Cadeira Abdutora', 'abdutores', 'Sente-se e abra as pernas contra a resistência da máquina para trabalhar abdutores e glúteos.', NULL),
(112, 'Abdução no Cross Over', 'abdutores', 'Com cabos baixos, afaste as pernas para o lado contraindo abdutores e glúteos.', NULL),
(113, 'Abdução com Caneleira', 'abdutores', 'De pé ou de lado, eleve a perna lateralmente com caneleira para ativar abdutores e glúteos.', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `itens_refeicao`
--

DROP TABLE IF EXISTS `itens_refeicao`;
CREATE TABLE IF NOT EXISTS `itens_refeicao` (
  `idItensRef` int NOT NULL AUTO_INCREMENT,
  `id_tipo_refeicao` int NOT NULL,
  `nome` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantidade` int NOT NULL,
  `medida` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`idItensRef`),
  KEY `id_tipo_refeicao` (`id_tipo_refeicao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `itens_refeicao`
--

TRUNCATE TABLE `itens_refeicao`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `nutrientes`
--

DROP TABLE IF EXISTS `nutrientes`;
CREATE TABLE IF NOT EXISTS `nutrientes` (
  `idNutrientes` int NOT NULL AUTO_INCREMENT,
  `alimento_id` int NOT NULL,
  `calorias` decimal(10,2) DEFAULT '0.00',
  `proteinas` decimal(10,2) DEFAULT '0.00',
  `carboidratos` decimal(10,2) DEFAULT '0.00',
  `gorduras` decimal(10,2) DEFAULT '0.00',
  `medida` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'g',
  PRIMARY KEY (`idNutrientes`),
  UNIQUE KEY `alimento_id` (`alimento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `nutrientes`
--

TRUNCATE TABLE `nutrientes`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `personal`
--

DROP TABLE IF EXISTS `personal`;
CREATE TABLE IF NOT EXISTS `personal` (
  `idPersonal` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cpf` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rg` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cref_numero` varchar(9) COLLATE utf8mb4_general_ci NOT NULL,
  `cref_categoria` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cref_regional` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `senha` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `data_cadastro` datetime NOT NULL,
  `numTel` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statusPlano` enum('Ativo','Pendente','Desativado','Cancelado','A verificar') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'A verificar',
  PRIMARY KEY (`idPersonal`),
  UNIQUE KEY `cpf` (`cpf`) USING BTREE,
  UNIQUE KEY `uq_cref_numero` (`cref_numero`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_numTel` (`numTel`),
  UNIQUE KEY `uq_rg` (`rg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `personal`
--

TRUNCATE TABLE `personal`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `refeicoes_tipos`
--

DROP TABLE IF EXISTS `refeicoes_tipos`;
CREATE TABLE IF NOT EXISTS `refeicoes_tipos` (
  `id` int NOT NULL,
  `nome_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_tipo` (`nome_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `refeicoes_tipos`
--

TRUNCATE TABLE `refeicoes_tipos`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `solicitacoes`
--

DROP TABLE IF EXISTS `solicitacoes`;
CREATE TABLE IF NOT EXISTS `solicitacoes` (
  `idSolicitacao` int NOT NULL AUTO_INCREMENT,
  `idPersonal` int NOT NULL,
  `idAluno` int NOT NULL,
  `status` enum('Pendente','Aceita','Rejeitada','Em análise') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendente',
  `data_solicitacao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSolicitacao`),
  KEY `FK_Personal_Solicit` (`idPersonal`),
  KEY `FK_Aluno_Solicit` (`idAluno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `solicitacoes`
--

TRUNCATE TABLE `solicitacoes`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `traducoes_alimentos`
--

DROP TABLE IF EXISTS `traducoes_alimentos`;
CREATE TABLE IF NOT EXISTS `traducoes_alimentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `termo_ingles` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `termo_portugues` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `termo_ingles` (`termo_ingles`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `traducoes_alimentos`
--

TRUNCATE TABLE `traducoes_alimentos`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `treinos`
--

DROP TABLE IF EXISTS `treinos`;
CREATE TABLE IF NOT EXISTS `treinos` (
  `idTreino` int NOT NULL AUTO_INCREMENT,
  `idAluno` int NOT NULL,
  `idPersonal` int NOT NULL,
  `criadoPor` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nome` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` enum('Musculação','CrossFit','Calistenia','Pilates','Aquecimento','Treino Específico','Outros') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Outros',
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `data_criacao` datetime NOT NULL,
  `data_ultima_modificacao` date NOT NULL,
  PRIMARY KEY (`idTreino`),
  KEY `FK_Personal_treino` (`idPersonal`),
  KEY `FK_Aluno_treino` (`idAluno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `treinos`
--

TRUNCATE TABLE `treinos`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `treinospersonal`
--

DROP TABLE IF EXISTS `treinospersonal`;
CREATE TABLE IF NOT EXISTS `treinospersonal` (
  `idTreinosP` int NOT NULL AUTO_INCREMENT,
  `nomeTreino` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `idPersonal` int NOT NULL,
  `nomePersonal` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`idTreinosP`),
  KEY `FK_Treino_Personal` (`idPersonal`),
  KEY `FK_Treino_nomePersonal` (`nomePersonal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `treinospersonal`
--

TRUNCATE TABLE `treinospersonal`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `treino_exercicio`
--

DROP TABLE IF EXISTS `treino_exercicio`;
CREATE TABLE IF NOT EXISTS `treino_exercicio` (
  `idTreino_Exercicio` int NOT NULL AUTO_INCREMENT,
  `idTreinosP` int NOT NULL,
  `idExercicio` int DEFAULT NULL,
  `idExercAdaptado` int DEFAULT NULL,
  `data_criacao` datetime NOT NULL,
  `data_ultima_modificacao` datetime NOT NULL,
  `series` int DEFAULT NULL,
  `repeticoes` int DEFAULT NULL,
  `carga` decimal(10,2) DEFAULT NULL,
  `ordem` int DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`idTreino_Exercicio`),
  KEY `FK_Treino_TreinosP` (`idTreinosP`),
  KEY `FK_Treino_Exercicio` (`idExercicio`),
  KEY `FK_Treino_ExercAdaptado` (`idExercAdaptado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `treino_exercicio`
--

TRUNCATE TABLE `treino_exercicio`;
-- --------------------------------------------------------

--
-- Estrutura da tabela `videos`
--

DROP TABLE IF EXISTS `videos`;
CREATE TABLE IF NOT EXISTS `videos` (
  `idvideos` int NOT NULL AUTO_INCREMENT,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `idExercicio` int DEFAULT NULL,
  `idExercAdaptado` int DEFAULT NULL,
  `cover` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`idvideos`),
  KEY `FK_Video_Exercicio` (`idExercicio`),
  KEY `FK_Video_ExercAdaptado` (`idExercAdaptado`)
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncar tabela antes do insert `videos`
--

TRUNCATE TABLE `videos`;
--
-- Extraindo dados da tabela `videos`
--

INSERT INTO `videos` (`idvideos`, `url`, `idExercicio`, `idExercAdaptado`, `cover`) VALUES
(1, 'https://www.youtube.com/watch?v=DrIZblSimW8', 1, NULL, ''),
(2, 'https://www.youtube.com/watch?v=kIFCWmiBOpo', 2, NULL, ''),
(3, 'https://www.youtube.com/watch?v=MzKobWFCmtk', 3, NULL, ''),
(4, 'https://www.youtube.com/watch?v=HwpcEMHePJ8', 4, NULL, ''),
(5, 'https://www.youtube.com/watch?v=D2ta8xvURwo', 5, NULL, ''),
(6, 'https://www.youtube.com/watch?v=P36j7gLzEZY', 6, NULL, ''),
(7, 'https://www.youtube.com/watch?v=nQ6DyydhAIo', 7, NULL, ''),
(8, 'https://www.youtube.com/watch?v=F9nNOp8Rfoo', 8, NULL, ''),
(9, 'https://www.youtube.com/watch?v=TVrxH3UJjfs', 9, NULL, ''),
(10, 'https://www.youtube.com/watch?v=bJdvab9uVUc', 10, NULL, ''),
(11, 'https://www.youtube.com/watch?v=bxM8uWGrP0E', 11, NULL, ''),
(12, 'https://www.youtube.com/watch?v=Qo2qqimmdFw', 12, NULL, ''),
(13, 'https://www.youtube.com/watch?v=ahOm8a3oncs', 13, NULL, ''),
(14, 'https://www.youtube.com/watch?v=hFb0YL6pPhY', 14, NULL, ''),
(15, 'https://www.youtube.com/watch?v=TnK4DsvAts4', 15, NULL, ''),
(16, 'https://www.youtube.com/watch?v=4DapiJicioo', 16, NULL, ''),
(17, 'https://www.youtube.com/watch?v=srgDQ6ie7qk', 17, NULL, ''),
(18, 'https://www.youtube.com/watch?v=hZB7xezXbMM', 18, NULL, ''),
(19, 'https://www.youtube.com/watch?v=CCLW6lekNTw', 19, NULL, ''),
(20, 'https://www.youtube.com/watch?v=YgcwPqxibnE', 20, NULL, ''),
(21, 'https://www.youtube.com/watch?v=DMPDjQdwhkA', 21, NULL, ''),
(22, 'https://www.youtube.com/watch?v=BTEkru3wrfg', 22, NULL, ''),
(23, 'https://www.youtube.com/watch?v=uFOs6zI883o', 23, NULL, ''),
(24, 'https://www.youtube.com/watch?v=z3fMvMyql8A', 24, NULL, ''),
(25, 'https://www.youtube.com/watch?v=mnOcntB2QE0', 25, NULL, ''),
(26, 'https://www.youtube.com/watch?v=t3Uu11HfaNQ', 26, NULL, ''),
(27, 'https://www.youtube.com/watch?v=Lsn4LVvtm44', 27, NULL, ''),
(28, 'https://www.youtube.com/watch?v=9w4pFCQvKg4', 28, NULL, ''),
(29, 'https://www.youtube.com/watch?v=m8J4xGmVXRA', 29, NULL, ''),
(30, 'https://www.youtube.com/watch?v=637o04QCWs0', 30, NULL, ''),
(31, 'https://www.youtube.com/watch?v=fNYeFdgR6Gs', 31, NULL, ''),
(32, 'https://www.youtube.com/watch?v=5gmwbzepi7E', 32, NULL, ''),
(33, 'https://www.youtube.com/watch?v=xkmxQaz6M3c', 33, NULL, ''),
(34, 'https://www.youtube.com/watch?v=V4m_uE1M0TM', 34, NULL, ''),
(35, 'https://www.youtube.com/watch?v=LHyYmMSTbj8', 35, NULL, ''),
(36, 'https://www.youtube.com/watch?v=QL1uD2E_xpg', 36, NULL, ''),
(37, 'https://www.youtube.com/watch?v=zpHgB1srbJA', 37, NULL, ''),
(38, 'https://www.youtube.com/watch?v=2Sb2_atoD2c', 38, NULL, ''),
(39, 'https://www.youtube.com/watch?v=rhlwE-J37x8', 39, NULL, ''),
(40, 'https://www.youtube.com/watch?v=EjiH-RcOR2U', 40, NULL, ''),
(41, 'https://www.youtube.com/watch?v=Q3motWl8P4w', 41, NULL, ''),
(42, 'https://www.youtube.com/watch?v=LoWCU2Yb4AY', 42, NULL, ''),
(43, 'https://www.youtube.com/watch?v=_3Mihov5a24', 43, NULL, ''),
(44, 'https://www.youtube.com/watch?v=OuEJB34uSKI', 44, NULL, ''),
(45, 'https://www.youtube.com/watch?v=jl2m6ch53NU', 45, NULL, ''),
(46, 'https://www.youtube.com/watch?v=gVC2jMVZVfE', 46, NULL, ''),
(47, 'https://www.youtube.com/watch?v=bCUMKcOefUU', 47, NULL, ''),
(48, 'https://www.youtube.com/watch?v=bKPmruztS8g', 48, NULL, ''),
(49, 'https://www.youtube.com/watch?v=1l7icabk0mo', 49, NULL, ''),
(50, 'https://www.youtube.com/watch?v=j7a4_SjM8ZM', 50, NULL, ''),
(51, 'https://www.youtube.com/watch?v=PXiJKmAyLR8', 51, NULL, ''),
(52, 'https://www.youtube.com/watch?v=OXH8gKrYzig', 52, NULL, ''),
(53, 'https://www.youtube.com/watch?v=toQuzSgZm8E', 53, NULL, ''),
(54, 'https://www.youtube.com/watch?v=P2HPQuUHMsQ', 54, NULL, ''),
(55, 'https://www.youtube.com/watch?v=-7OxAps9mvk', 55, NULL, ''),
(56, 'https://www.youtube.com/watch?v=3H5Kw5-_Lw8', 56, NULL, ''),
(57, 'https://www.youtube.com/watch?v=vMXNqjcc21c', 57, NULL, ''),
(58, 'https://www.youtube.com/watch?v=4O_9NktinIw', 58, NULL, ''),
(59, 'https://www.youtube.com/watch?v=wtcZa02D5-c', 59, NULL, ''),
(60, 'https://www.youtube.com/watch?v=zqvKtQippnA', 60, NULL, ''),
(61, 'https://www.youtube.com/watch?v=69JIUT2q6lU', 61, NULL, ''),
(62, 'https://www.youtube.com/watch?v=mahiVOFUv14', 62, NULL, ''),
(63, 'https://www.youtube.com/watch?v=gvbWkKYP0VY', 63, NULL, ''),
(64, 'https://www.youtube.com/watch?v=1EQU0jBpcds', 64, NULL, ''),
(65, 'https://www.youtube.com/watch?v=zEgL8MSaSs8', 65, NULL, ''),
(66, 'https://www.youtube.com/watch?v=gh7fXWsPKaM', 66, NULL, ''),
(67, 'https://www.youtube.com/watch?v=fCqyKmIVH00', 67, NULL, ''),
(68, 'https://www.youtube.com/watch?v=QYF8YgU8few', 68, NULL, ''),
(69, 'https://www.youtube.com/watch?v=PJMPwGvDxaQ', 69, NULL, ''),
(70, 'https://www.youtube.com/watch?v=NjE50Vz5Lkc', 70, NULL, ''),
(71, 'https://www.youtube.com/watch?v=I30xMS5gyPw', 71, NULL, ''),
(72, 'https://www.youtube.com/watch?v=QmIpJ498RNA', 72, NULL, ''),
(73, 'https://www.youtube.com/watch?v=u1DPdaxLMcw', 73, NULL, ''),
(74, 'https://www.youtube.com/watch?v=kWGRm-IDMQ4', 74, NULL, ''),
(75, 'https://www.youtube.com/watch?v=GKbh4fNX2gM', 75, NULL, ''),
(76, 'https://www.youtube.com/watch?v=uCgSv-4-S0A', 76, NULL, ''),
(77, 'https://www.youtube.com/watch?v=-OmMnWC4iT4', 77, NULL, ''),
(78, 'https://www.youtube.com/watch?v=w5xVs3hCvfg', 78, NULL, ''),
(79, 'https://www.youtube.com/watch?v=PxsKGxKsDL0', 79, NULL, ''),
(80, 'https://www.youtube.com/watch?v=_UfEXzV5Yf4', 80, NULL, ''),
(81, 'https://www.youtube.com/watch?v=u41-6ZMC528', 81, NULL, ''),
(82, 'https://www.youtube.com/watch?v=HY8i_j-wvys', 82, NULL, ''),
(83, 'https://www.youtube.com/watch?v=idOLAV3JGDc', 83, NULL, ''),
(84, 'https://www.youtube.com/watch?v=YILZe3FfIw8', 84, NULL, ''),
(85, 'https://www.youtube.com/watch?v=aKRVoiOQK3Y', 85, NULL, ''),
(86, 'https://www.youtube.com/watch?v=0M0i4jD5CGw', 86, NULL, ''),
(87, 'https://www.youtube.com/watch?v=FaGtwve4s-E', 87, NULL, ''),
(88, 'https://www.youtube.com/watch?v=OpkKv88HzR8', 88, NULL, ''),
(89, 'https://www.youtube.com/watch?v=HbLx5f5vnhY', 89, NULL, ''),
(90, 'https://www.youtube.com/watch?v=jMnDwCn72ws', 90, NULL, ''),
(91, 'https://www.youtube.com/watch?v=DKD0id35rgU', 91, NULL, ''),
(92, 'https://www.youtube.com/watch?v=Tux6Q2AW0eA', 92, NULL, ''),
(93, 'https://www.youtube.com/watch?v=ZEdksboBtg4', 93, NULL, ''),
(94, 'https://www.youtube.com/watch?v=5MOa-_qdw9s', 94, NULL, ''),
(95, 'https://www.youtube.com/watch?v=RqOJkeiET5Q', 95, NULL, ''),
(96, 'https://www.youtube.com/watch?v=iAKisU13ypU', 96, NULL, ''),
(97, 'https://www.youtube.com/watch?v=WFAvShrNgiw', 97, NULL, ''),
(98, 'https://www.youtube.com/watch?v=6E5soH4kpL8', 98, NULL, ''),
(99, 'https://www.youtube.com/watch?v=DmqaUQaskwo', 99, NULL, ''),
(100, 'https://www.youtube.com/watch?v=0NES5nQ7tnw', 100, NULL, ''),
(101, 'https://www.youtube.com/watch?v=bc6X06OWyPk', 101, NULL, ''),
(102, 'https://www.youtube.com/watch?v=rreRO8-pPYg', 102, NULL, ''),
(103, 'https://www.youtube.com/watch?v=cJ4J7XoPo6E', 103, NULL, ''),
(104, 'https://www.youtube.com/watch?v=CC7fg3SEof8', 104, NULL, ''),
(105, 'https://www.youtube.com/watch?v=7CaF6Rsw_SA', 105, NULL, ''),
(106, 'https://www.youtube.com/watch?v=tATrzTP56_M', 106, NULL, ''),
(107, 'https://www.youtube.com/watch?v=HqWvQkwr_Zk', 107, NULL, ''),
(108, 'https://www.youtube.com/watch?v=lBRDG-psDdE', 108, NULL, ''),
(109, 'https://www.youtube.com/watch?v=7hebjw6AGGM', 109, NULL, ''),
(110, 'https://www.youtube.com/watch?v=cRWpZBMJZrw', 110, NULL, ''),
(111, 'https://www.youtube.com/watch?v=EgbM4kXE-2s', 111, NULL, ''),
(112, 'https://www.youtube.com/watch?v=A7BzMkvbGSY', 112, NULL, ''),
(113, 'https://www.youtube.com/watch?v=KSjVpHvJhLo', 113, NULL, '');

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `agua`
--
ALTER TABLE `agua`
  ADD CONSTRAINT `FK_Agua_Aluno` FOREIGN KEY (`idaluno`) REFERENCES `alunos` (`idAluno`);

--
-- Limitadores para a tabela `alunos`
--
ALTER TABLE `alunos`
  ADD CONSTRAINT `FK_Alunos_Personal` FOREIGN KEY (`idPersonal`) REFERENCES `personal` (`idPersonal`);

--
-- Limitadores para a tabela `itens_refeicao`
--
ALTER TABLE `itens_refeicao`
  ADD CONSTRAINT `itens_refeicao_ibfk_1` FOREIGN KEY (`id_tipo_refeicao`) REFERENCES `refeicoes_tipos` (`id`);

--
-- Limitadores para a tabela `nutrientes`
--
ALTER TABLE `nutrientes`
  ADD CONSTRAINT `nutrientes_ibfk_1` FOREIGN KEY (`alimento_id`) REFERENCES `itens_refeicao` (`idItensRef`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `solicitacoes`
--
ALTER TABLE `solicitacoes`
  ADD CONSTRAINT `FK_Aluno_Solicit` FOREIGN KEY (`idAluno`) REFERENCES `alunos` (`idAluno`),
  ADD CONSTRAINT `FK_Personal_Solicit` FOREIGN KEY (`idPersonal`) REFERENCES `personal` (`idPersonal`);

--
-- Limitadores para a tabela `treinos`
--
ALTER TABLE `treinos`
  ADD CONSTRAINT `FK_Aluno_treino` FOREIGN KEY (`idAluno`) REFERENCES `alunos` (`idAluno`),
  ADD CONSTRAINT `FK_Personal_treino` FOREIGN KEY (`idPersonal`) REFERENCES `personal` (`idPersonal`);

--
-- Limitadores para a tabela `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `FK_Video_ExercAdaptado` FOREIGN KEY (`idExercAdaptado`) REFERENCES `exercadaptados` (`idExercAdaptado`),
  ADD CONSTRAINT `FK_Video_Exercicio` FOREIGN KEY (`idExercicio`) REFERENCES `exercicios` (`idExercicio`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
