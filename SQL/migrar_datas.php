<?php

$cli = PHP_SAPI === 'cli';

if (!$cli) {
    session_start();
    if (($_SESSION['tipo'] ?? '') !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

@unlink(__DIR__ . '/.banco_atualizado_v2');

define('SEM_MIGRACAO_AUTOMATICA', true);
require_once __DIR__ . '/../conexao.php';

$pendencias = migrar_banco($conn, function ($t) { echo $t . PHP_EOL; });

@file_put_contents(MIGRACAO_MARCA, $pendencias === 0 ? 'ok' : 'pendente:' . $pendencias);
