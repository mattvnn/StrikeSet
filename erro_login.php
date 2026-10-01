<?php
session_start();

if (!isset($_GET['msg'])) {
    header("Location: login.php");
    exit();
}

$msg = $_GET['msg'];

switch ($msg) {
    case "senha":
        $titulo = "Senha incorreta";
        $texto = "A senha informada está incorreta. Verifique os dados e tente novamente.";
        break;

    case "usuario":
        $titulo = "Usuário não encontrado";
        $texto = "Não existe nenhuma conta cadastrada com este e-mail.";
        break;

    default:
        $titulo = "Erro";
        $texto = "Ocorreu um erro ao realizar o login.";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Erro no Login | StrikeSet Gaspar</title>

<link rel="stylesheet" href="global.css?v=<?php echo time();?>">

<style>
.erro-wrapper{
    flex:1;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:60px 20px;
}

.erro-card{
    width:100%;
    max-width:440px;
    background:var(--cinza-escuro);
    border:1px solid var(--cinza-medio);
    border-top:4px solid var(--vermelho);
    border-radius:var(--raio);
    padding:44px 36px;
    box-shadow:var(--sombra-card);
    text-align:center;
    animation:fadeIn .5s ease-out both;
}

.erro-icone{
    font-size:3.5rem;
    line-height:1;
    margin-bottom:16px;
}

.erro-card h1{
    font-family:var(--fonte-titulo);
    font-style:italic;
    font-weight:800;
    font-size:2.2rem;
    color:#f87171;
    margin-bottom:12px;
}

.erro-card p{
    color:var(--cinza-texto);
    line-height:1.6;
    margin-bottom:28px;
}

.erro-btn{
    display:inline-block;
    background:var(--laranja);
    color:white;
    text-decoration:none;
    padding:13px 30px;
    border-radius:8px;
    font-weight:700;
    letter-spacing:.4px;
    transition:.3s;
}

.erro-btn:hover{
    background:var(--laranja-escuro);
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(249,115,22,.3);
}
</style>

</head>
<body>

<header>
    <h2>StrikeSet Gaspar</h2>
    <nav>
        <a href="home.php">Inicio</a>
        <a href="galeria.php">Galeria</a>
        <a href="sobre.php">Sobre o Time</a>
        <a href="treinos.php">Treinos</a>
        <a href="campeonatos.php">Campeonatos</a>
        <a class="btn-login" href="login.php">LOGIN/REGISTRO</a>
    </nav>
</header>

<main class="erro-wrapper">
    <div class="erro-card">
        <div class="erro-icone">⚠️</div>
        <h1><?php echo htmlspecialchars($titulo); ?></h1>
        <p><?php echo htmlspecialchars($texto); ?></p>
        <a class="erro-btn" href="login.php">Voltar ao Login</a>
    </div>
</main>

<footer>
    <p>StrikeSet Gaspar</p>
</footer>

</body>
</html>
