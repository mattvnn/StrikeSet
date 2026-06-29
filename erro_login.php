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

<title>Erro no Login</title>

<link rel="stylesheet" href="global.css?v=<?php echo time();?>">

<style>

body{
    margin:0;
    background:#f5f6fa;
    font-family:Arial, Helvetica, sans-serif;
}

.container{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.card{
    width:420px;
    background:#fff;
    border-radius:15px;
    padding:40px;
    box-shadow:0 10px 30px rgba(0,0,0,.15);
    text-align:center;
}

.icon{
    font-size:70px;
    color:#e53935;
}

h1{
    color:#e53935;
    margin:20px 0 10px;
}

p{
    color:#555;
    line-height:1.6;
    margin-bottom:30px;
}

a{
    display:inline-block;
    text-decoration:none;
    background:#1976d2;
    color:white;
    padding:12px 28px;
    border-radius:8px;
    transition:.3s;
    font-weight:bold;
}

a:hover{
    background:#1258a8;
}

</style>

</head>
<body>

<div class="container">

    <div class="card">

        <div class="icon">⚠️</div>

        <h1><?php echo $titulo; ?></h1>

        <p><?php echo $texto; ?></p>

        <a href="login.php">Voltar ao Login</a>

    </div>

</div>

</body>
</html>
