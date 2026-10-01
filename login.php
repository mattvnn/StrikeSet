<?php
/* Mensagens de retorno do cadastro (vindas de salvar_cadastro.php) */
$aviso_tipo  = '';
$aviso_texto = '';

if (isset($_GET['cadastro'])) {
    switch ($_GET['cadastro']) {
        case 'sucesso':
            $aviso_tipo  = 'sucesso';
            $aviso_texto = 'Cadastro realizado com sucesso! Agora é só fazer login.';
            break;
        case 'senhas':
            $aviso_tipo  = 'erro';
            $aviso_texto = 'As senhas não coincidem. Digite a mesma senha nos dois campos.';
            break;
        case 'existe':
            $aviso_tipo  = 'erro';
            $aviso_texto = 'Já existe uma conta cadastrada com este e-mail.';
            break;
        case 'email':
            $aviso_tipo  = 'erro';
            $aviso_texto = 'Informe um e-mail válido.';
            break;
        case 'vazio':
            $aviso_tipo  = 'erro';
            $aviso_texto = 'Preencha todos os campos do cadastro.';
            break;
        default:
            $aviso_tipo  = 'erro';
            $aviso_texto = 'Não foi possível concluir o cadastro. Tente novamente.';
    }
}
?>
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

        <div class="auth-card" id="login">
            <h2>Login</h2>
            <p class="auth-sub">Entre para acompanhar treinos e campeonatos.</p>

            <form class="auth-form" method="POST" action="autenticar.php">
                <div class="form-group">
                    <label for="usuario">E-mail</label>
                    <input type="email" id="usuario" name="email" placeholder="Digite seu e-mail" autocomplete="email" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required>
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

        <div class="auth-card" id="cadastro">
            <h2>Cadastro</h2>
            <p class="auth-sub">Crie sua conta e faça parte do time.</p>

            <?php if ($aviso_texto !== ''): ?>
                <div class="auth-alert auth-alert--<?php echo $aviso_tipo; ?>" role="alert">
                    <?php echo htmlspecialchars($aviso_texto); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="form-cadastro" method="POST" action="salvar_cadastro.php" novalidate>
                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Digite seu nome"
                        autocomplete="name"
                        required>
                </div>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Digite seu e-mail"
                        autocomplete="email"
                        required>
                </div>

                <div class="form-group">
                    <label for="senha-cadastro">Senha</label>
                    <input
                        type="password"
                        id="senha-cadastro"
                        name="senha"
                        placeholder="Crie uma senha"
                        autocomplete="new-password"
                        required>
                </div>

                <div class="form-group">
                    <label for="senha-repetir">Repetir senha</label>
                    <input
                        type="password"
                        id="senha-repetir"
                        name="confirmar_senha"
                        placeholder="Repita a senha"
                        autocomplete="new-password"
                        aria-describedby="senha-hint"
                        required>
                    <small class="field-hint" id="senha-hint" aria-live="polite"></small>
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

    <script>
        (function () {
            var form    = document.getElementById('form-cadastro');
            var senha   = document.getElementById('senha-cadastro');
            var repetir = document.getElementById('senha-repetir');
            var dica    = document.getElementById('senha-hint');

            /* Compara os dois campos de senha e avisa o usuário na hora */
            function conferir() {
                if (repetir.value === '') {
                    repetir.setCustomValidity('');
                    repetir.classList.remove('campo-erro', 'campo-ok');
                    dica.textContent = '';
                    dica.className = 'field-hint';
                    return;
                }

                if (senha.value !== repetir.value) {
                    repetir.setCustomValidity('As senhas não coincidem.');
                    repetir.classList.add('campo-erro');
                    repetir.classList.remove('campo-ok');
                    dica.textContent = 'As senhas não coincidem.';
                    dica.className = 'field-hint field-hint--erro';
                } else {
                    repetir.setCustomValidity('');
                    repetir.classList.add('campo-ok');
                    repetir.classList.remove('campo-erro');
                    dica.textContent = 'As senhas coincidem.';
                    dica.className = 'field-hint field-hint--ok';
                }
            }

            senha.addEventListener('input', conferir);
            repetir.addEventListener('input', conferir);

            form.addEventListener('submit', function (e) {
                conferir();
                if (!form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                }
            });
        })();
    </script>
</body>
</html>
