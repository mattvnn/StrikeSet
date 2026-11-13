<?php

$host = getenv('DB_HOST');
$porta = (int) (getenv('DB_PORT') ?: 3306);
$usuario = getenv('DB_USER');
$senha = getenv('DB_PASSWORD');
$banco = getenv('DB_NAME');

/*
| SSL ligado por padrão (banco na nuvem).
| Para um MySQL local sem SSL, coloque DB_SSL=0 no .env.
*/
$usarSsl = getenv('DB_SSL') !== '0';

$conn = mysqli_init();

if ($usarSsl) {

    $ca = __DIR__ . "/certs/ca.pem";

    if (!file_exists($ca)) {
        die("Certificado CA não encontrado.");
    }

    $conn->ssl_set(null, null, $ca, null, null);
}

try {

    $conn->real_connect(
        $host,
        $usuario,
        $senha,
        $banco,
        $porta,
        null,
        $usarSsl ? MYSQLI_CLIENT_SSL : 0
    );

} catch (mysqli_sql_exception $e) {

    die("Erro na conexão: " . $e->getMessage());
}

$conn->set_charset("utf8mb4");

/* Datas e horas do PHP no fuso de Santa Catarina */
date_default_timezone_set('America/Sao_Paulo');
