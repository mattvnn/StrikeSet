<?php
session_start();

require 'conexao.php'; // sua conexão com o banco

echo '<pre>';
var_dump($_SESSION);
echo '</pre>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    // Busca usuário no banco
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica usuário
    if ($usuario && password_verify($senha, $usuario['senha'])) {

        // Cria sessão
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['tipo']         = $usuario['tipo'];

        // Redireciona conforme tipo
        if ($usuario['tipo'] === 'admin') {

            header("Location: admin_treinos.php");
            exit();

        } else {

            header("Location: home.php");
            exit();
        }

    } else {

        $erro = "Email ou senha inválidos.";
    }
}
