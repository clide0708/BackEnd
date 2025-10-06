<?php 

    class DB {
        private static function tentarConexao($envFile) {
            if (!file_exists($envFile)) return null;

            // recarrega o .env do arquivo correto
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, basename($envFile));
            $dotenv->load();

            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $db   = $_ENV['DB_NAME'] ?? 'bd_tcc'; 
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? ''; 
            $charset = 'utf8mb4';

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                return new PDO("mysql:host={$host};dbname={$db};charset={$charset}", $user, $pass, $options);
            } catch (\PDOException $e) {
                error_log("Falha ao conectar com $envFile: " . $e->getMessage());
                return null;
            }
        }

        public static function connectDB() { 
            // tenta production primeiro
            $con = self::tentarConexao(__DIR__ . '/.env.production');
            if ($con) return $con;

            // limpa variáveis antigas do production antes de tentar development
            foreach ($_ENV as $key => $value) {
                if (str_starts_with($key, 'DB_')) unset($_ENV[$key]);
            }

            // fallback para development
            $con = self::tentarConexao(__DIR__ . '/.env.development');
            if ($con) return $con;

            // nenhum funcionou
            throw new \PDOException("❌ Não foi possível conectar ao banco nem no production nem no development");
        }
    }
    function connectDB() {
        return DB::connectDB();
    }
    
?>