<?php

/*
|--------------------------------------------------------------------------
| Atualização do banco (versão manual)
|--------------------------------------------------------------------------
|
| NÃO é preciso rodar isto: o site já faz a atualização sozinho no primeiro
| acesso (veja includes/migracao.php). Este arquivo só serve para ver o
| relatório ou forçar a verificação de novo:
|
|   Terminal:  docker compose exec web php SQL/migrar_datas.php
|   Navegador: logado como admin, abra /SQL/migrar_datas.php
*/

$cli = PHP_SAPI === 'cli';

if (!$cli) {
    session_start();
    if (($_SESSION['tipo'] ?? '') !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

/* Força uma nova verificação */
@unlink(__DIR__ . '/.banco_atualizado');

define('SEM_MIGRACAO_AUTOMATICA', true);
require_once __DIR__ . '/../conexao.php';

$pendencias = migrar_banco($conn, function ($t) { echo $t . PHP_EOL; });

@file_put_contents(__DIR__ . '/.banco_atualizado', $pendencias === 0 ? 'ok' : 'pendente:' . $pendencias);
