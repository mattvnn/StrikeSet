<?php
include "conexao.php";

$nome  = $_POST['nome'];
$email = $_POST['email'];
$senha = $_POST['senha'];

/* Gera hash seguro da senha */
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

$sql = "INSERT INTO usuarios (nome, email, senha)
        VALUES ('$nome', '$email', '$senha_hash')";

if ($conn->query($sql) === TRUE) {
    header("Location: login.php?cadastro=sucesso");
    exit;
} else {
    header("Location: login.php?cadastro=erro");
    exit;
}
?>
