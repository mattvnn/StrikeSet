<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';

$erro = '';
$sucesso = '';

if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso') {
    $sucesso = 'Cadastro realizado com sucesso! Faça seu login.';
}

if (($_GET['senha'] ?? '') === 'redefinida') {
    $sucesso = 'Senha alterada com sucesso! Entre com a nova senha.';
}

$paginasRetorno = ['treinos.php', 'campeonatos.php', 'galeria.php', 'sobre.php', 'home.php'];
$voltar = $_GET['voltar'] ?? $_POST['voltar'] ?? '';
$voltar = in_array($voltar, $paginasRetorno, true) ? $voltar : '';

if ($voltar !== '' && $sucesso === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sucesso = 'Entre na sua conta para continuar.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastro') {

        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $repetirSenha = $_POST['repetir_senha'] ?? '';

        if (
    $nome === '' ||
    $email === '' ||
    $senha === '' ||
    $repetirSenha === ''
) {

    $erro = 'Preencha todos os campos.';

} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $erro = 'E-mail inválido.';

} elseif (strlen($senha) < 6) {

    $erro = 'A senha deve possuir pelo menos 6 caracteres.';

} elseif ($senha !== $repetirSenha) {

    $erro = 'As senhas não coincidem.';

} else {

            $stmt = $conn->prepare("
                SELECT id
                FROM usuarios
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {

                $erro = 'Este e-mail já está cadastrado.';

            } else {

                $senhaHash = password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare("
                    INSERT INTO usuarios
                    (
                        nome,
                        email,
                        senha,
                        tipo,
                        ativo
                    )
                    VALUES (?, ?, ?, 'usuario', 1)
                ");

                $stmt->bind_param(
                    "sss",
                    $nome,
                    $email,
                    $senhaHash
                );

                if ($stmt->execute()) {

                    header(
                        'Location: login.php?cadastro=sucesso#login'
                    );

                    exit;

                } else {

                    $erro = 'Erro ao cadastrar usuário.';
                }
            }
        }
    }

    if ($acao === 'login') {

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        $stmt = $conn->prepare("
            SELECT
                id,
                nome,
                email,
                senha,
                tipo,
                ativo
            FROM usuarios
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $usuario = $resultado->fetch_assoc();

        if (!$usuario) {

            $erro = 'E-mail ou senha inválidos.';

        } elseif (!password_verify($senha, $usuario['senha'])) {

            $erro = 'E-mail ou senha inválidos.';

        } elseif (!$usuario['ativo']) {

            $erro = 'Usuário desativado.';

        } else {

            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['tipo'] = $usuario['tipo'];

            if ($usuario['tipo'] === 'admin') {

                header('Location: admin_treinos.php');

            } else {

                header('Location: ' . ($voltar === 'treinos.php' ? 'treinos.php#inscricao' : ($voltar !== '' ? $voltar : 'home.php')));
            }

            exit;
        }
    }
}

$acao_enviada = $_POST['acao'] ?? '';
$erro_login    = ($acao_enviada === 'login') ? $erro : '';
$erro_cadastro = ($acao_enviada === 'cadastro') ? $erro : '';

$valor_nome  = ($acao_enviada === 'cadastro') ? ($_POST['nome'] ?? '') : '';
$valor_email = ($acao_enviada === 'cadastro') ? ($_POST['email'] ?? '') : '';
$valor_login = ($acao_enviada === 'login') ? ($_POST['email'] ?? '') : '';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StrikeSet Gaspar - Login/Cadastro</title>
    <link rel="stylesheet" href="visual/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="visual/css/login.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="auth-container">

        <div class="hero-img">
            <img src="visual/images/jogador.png" alt="Jogadora de vôlei">
        </div>

        <div class="auth-card" id="login">
            <h2>Login</h2>
            <p class="auth-sub">Entre para acompanhar treinos e campeonatos.</p>

            <?php if ($sucesso !== ''): ?>
                <div class="auth-alert auth-alert--sucesso" role="alert">
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <?php if ($erro_login !== ''): ?>
                <div class="auth-alert auth-alert--erro" role="alert">
                    <?php echo htmlspecialchars($erro_login); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="login.php#login">
                <input type="hidden" name="acao" value="login">
                <input type="hidden" name="voltar" value="<?php echo htmlspecialchars($voltar); ?>">

                <div class="form-group">
                    <label for="usuario">E-mail</label>
                    <input type="email" id="usuario" name="email" placeholder="Digite seu e-mail" autocomplete="email" value="<?php echo htmlspecialchars($valor_login); ?>" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-auth">Entrar</button>
            </form>
            <div class="text-small">
                <a href="esqueci_senha.php">Esqueceu a senha?</a>
            </div>
        </div>

        <div class="auth-divider">
            <span>ou</span>
        </div>

        <div class="auth-card" id="cadastro">
            <h2>Cadastro</h2>
            <p class="auth-sub">Crie sua conta e faça parte do time.</p>

            <?php if ($erro_cadastro !== ''): ?>
                <div class="auth-alert auth-alert--erro" role="alert">
                    <?php echo htmlspecialchars($erro_cadastro); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="form-cadastro" method="POST" action="login.php#cadastro" novalidate>
                <input type="hidden" name="acao" value="cadastro">

                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Digite seu nome"
                        autocomplete="name"
                        value="<?php echo htmlspecialchars($valor_nome); ?>"
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
                        value="<?php echo htmlspecialchars($valor_email); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="senha-cadastro">Senha</label>
                    <input
                        type="password"
                        id="senha-cadastro"
                        name="senha"
                        placeholder="Crie uma senha (mínimo 6 caracteres)"
                        autocomplete="new-password"
                        minlength="6"
                        required>
                </div>

                <div class="form-group">
                    <label for="senha-repetir">Repetir senha</label>
                    <input
                        type="password"
                        id="senha-repetir"
                        name="repetir_senha"
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

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="visual/js/confere_senha.js"></script>
    <script>conferirSenhas('form-cadastro');</script>
</body>
</html>
