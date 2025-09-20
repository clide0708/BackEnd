<?php

    require_once __DIR__ . '/db.connect.php';
    try {

        
        $pdo = connectDB();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $exerciciosJson = file_get_contents('../Data/exercicios.json');
        $exercicios = json_decode($exerciciosJson, true);
        if ($exercicios === null) {
            throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
        }

        $stmtExercicios = $pdo->prepare("INSERT INTO exercicios (nome, grupoMuscular, descricao, cadastradoPor) VALUES (?, ?, ?, ?)");
        $stmtVideos = $pdo->prepare("INSERT INTO videos (url, cover) VALUES (?, ?)");

        foreach ($exercicios as $ex) {
            
            $nome = $ex['nome'] ?? '';
            $grupoMuscular = $ex['grupo'] ?? '';
            $descricao = $ex['informacoes'] ?? '';

            $url = $ex['url'] ?? '';
            $cover = $ex['cover'] ?? '';

            // Executa inserção na tabela exercicios
            $stmtExercicios->execute([$nome, $grupoMuscular, $descricao, $cadastradoPor]);

            // Executa inserção na tabela videos
            $stmtVideos->execute([$url, $cover]);
        }

        echo "Exercícios e vídeos importados com sucesso.";

    } catch (PDOException $e) {
        echo "Erro ao importar dados: " . $e->getMessage();
    }
?>