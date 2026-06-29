<?php
session_start();
include "conexao.php";

$email = $_POST['email'];
$senha = $_POST['senha'];

$sql = "SELECT * FROM usuarios WHERE email='$email'";
$result = $conn->query($sql);

if ($result->num_rows == 1) {

    $usuario = $result->fetch_assoc();

    // Verifica senha
    if (password_verify($senha, $usuario['senha'])) {

        // Sessão
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['tipo']         = $usuario['tipo'];

        // Redirecionamento
        if ($usuario['tipo'] === 'admin') {

            header("Location: admin_treinos.php");

        } else {

            header("Location: home.php");
        }

        exit();

    } else {
	    header("Location: erro_login.php?msg=senha");
            exit();
    }

} else {
	header("Location: erro_login.php?msg=usuario");
        exit();
}
?>
