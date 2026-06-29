<?php
$host = "192.168.88.177";
$usuario = "hugoapp";
$senha = "@appHugo2";
$banco = "hugodb";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro: " . $conn->connect_error);
}
?>
