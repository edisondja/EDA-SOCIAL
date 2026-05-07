<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class LoadVideoseggSql extends Command
{
    protected $signature = 'videosegg:load-sql
                            {path? : Ruta al archivo .sql (por defecto: Documents/videosegg/dbvideosegg_2026_04_30.sql o VIDEOSEGG_SQL_PATH)}';

    protected $description = 'Carga el dump MySQL de videosegg (tabla posts, etc.) en la base configurada en la conexión videosegg. Requiere el cliente mysql en PATH.';

    public function handle(): int
    {
        $path = $this->argument('path') ?: config('videosegg.sql_dump_path');
        if (!$path || !is_readable($path)) {
            $this->error('No se encuentra el dump SQL. Indica la ruta:');
            $this->line('  php artisan videosegg:load-sql /Users/tuusuario/Documents/videosegg/dbvideosegg_2026_04_30.sql');
            $this->line('O define VIDEOSEGG_SQL_PATH en .env');
            $this->line('Ruta por defecto en config: ' . (string) config('videosegg.sql_dump_path'));

            return 1;
        }

        $mysql = $this->findMysqlBinary();
        if (!$mysql) {
            $this->error('No se encontró el ejecutable "mysql". Instálalo o añádelo al PATH (p. ej. XAMPP/MAMP/Homebrew).');

            return 1;
        }

        $cfg = config('database.connections.videosegg');
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (string) ($cfg['port'] ?? '3306');
        $user = $cfg['username'] ?? 'root';
        $pass = (string) ($cfg['password'] ?? '');
        $database = $cfg['database'] ?? 'videosegg';

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            $this->error('Nombre de base de datos no válido en la conexión videosegg.');

            return 1;
        }

        try {
            $pdo = new PDO(
                'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4',
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            $this->error('No se pudo crear la base de datos: ' . $e->getMessage());

            return 1;
        }

        $this->info('Importando SQL en la base "' . $database . '" (puede tardar varios minutos)…');
        $this->line('Archivo: ' . $path);

        $input = fopen($path, 'rb');
        if ($input === false) {
            $this->error('No se pudo abrir el archivo SQL.');

            return 1;
        }

        $command = [
            $mysql,
            '--default-character-set=utf8mb4',
            '-h', $host,
            '-P', $port,
            '-u', $user,
            $database,
        ];

        $env = $_ENV;
        if ($pass !== '') {
            $env['MYSQL_PWD'] = $pass;
        }

        $process = new Process($command, null, $env, $input, 3600);
        try {
            $process->run(function ($type, $buffer) {
                if ($type === Process::ERR) {
                    $this->output->write($buffer);
                }
            });
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }

        if (!$process->isSuccessful()) {
            $this->error('mysql terminó con error (código ' . $process->getExitCode() . ').');
            $this->line($process->getErrorOutput());

            return 1;
        }

        $this->info('Listo. Ejecuta ahora: php artisan videosegg:import-posts');

        return 0;
    }

    protected function findMysqlBinary(): ?string
    {
        $finder = new ExecutableFinder();
        $extraDirs = [
            '/opt/homebrew/opt/mysql-client/bin',
            '/opt/homebrew/opt/mysql@8.4/bin',
            '/opt/homebrew/opt/mysql@8.0/bin',
            '/usr/local/mysql/bin',
            '/opt/homebrew/bin',
            '/Applications/MAMP/Library/bin',
        ];

        return $finder->find('mysql', null, $extraDirs);
    }
}
