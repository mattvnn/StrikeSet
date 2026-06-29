<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StrikeSet Gaspar - Login/Cadastro</title>
    <link rel="stylesheet" href="global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="login.css?v=<?php echo time(); ?>">
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

    <main class="auth-container">

        <div class="hero-img">
            <img src="jogador.png" alt="Jogadora de vôlei">
        </div>

        <div class="auth-card">
            <h2>Login</h2>
             <form class="auth-form" method="POST" action="autenticar.php">
                <div class="form-group">
                    <label for="usuario">Usuário</label>
                    <input type="email" id="usuario" name="email" placeholder="Digite seu e-mail">
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha">
                </div>
                <button type="submit" class="btn-auth">Entrar</button>
            </form>
            <div class="text-small">
                <a href="#">Esqueceu a senha?</a>
            </div>
        </div>

        <div class="auth-divider">
            <span>ou</span>
        </div>

        <div class="auth-card">
            <h2>Cadastro</h2>
	   <form class="auth-form" method="POST" action="salvar_cadastro.php">
    <div class="form-group">
        <label for="nome">Nome</label>
        <input
            type="text"
            id="nome"
            name="nome"
            placeholder="Digite seu nome"
            required>
    </div>

    <div class="form-group">
        <label for="email">E-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Digite seu e-mail"
            required>
    </div>

    <div class="form-group">
        <label for="senha-cadastro">Senha</label>
        <input
            type="password"
            id="senha-cadastro"
            name="senha"
            placeholder="Crie uma senha"
            required>
    </div>

    <button type="submit" class="btn-auth">Cadastrar-se</button>
</form>
             <div class="text-small">
                Ao se cadastrar, você concorda com nossos <a href="#">termos</a>.
            </div>
        </div>
    </main>

    <footer>
        <p>StrikeSet Gaspar</p>
        <div>
            <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/instagram.svg" alt="Instagram">
            <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/x.svg" alt="Twitter">
            <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/whatsapp.svg" alt="WhatsApp">
        </div>
    </footer>
</body>
</html>
