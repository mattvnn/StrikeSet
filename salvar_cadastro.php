<?php
include "conexao.php";

/* Só aceita envio pelo formulário */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$nome      = trim($_POST['nome'] ?? '');
$email     = trim($_POST['email'] ?? '');
$senha     = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmar_senha'] ?? '';

/* Campos obrigatórios */
if ($nome === '' || $email === '' || $senha === '' || $confirmar === '') {
    header("Location: login.php?cadastro=vazio#cadastro");
    exit;
}

/* E-mail válido */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: login.php?cadastro=email#cadastro");
    exit;
}

/* Repetir senha: precisa ser igual à senha */
if ($senha !== $confirmar) {
    header("Location: login.php?cadastro=senhas#cadastro");
    exit;
}

/* E-mail já cadastrado? */
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
if (!$stmt) {
    header("Location: login.php?cadastro=erro#cadastro");
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
$ja_existe = $stmt->num_rows > 0;
$stmt->close();

if ($ja_existe) {
    header("Location: login.php?cadastro=existe#cadastro");
    exit;
}

/* Gera hash seguro da senha */
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

/* Insere o usuário (prepared statement evita SQL injection) */
$stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
if (!$stmt) {
    header("Location: login.php?cadastro=erro#cadastro");
    exit;
}
$stmt->bind_param("sss", $nome, $email, $senha_hash);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: login.php?cadastro=sucesso#login");
    exit;
}

$stmt->close();
header("Location: login.php?cadastro=erro#cadastro");
exit;
?>
