<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/email.php';

$erro = '';
$enviado = false;
$linkDev = '';
$email = '';

$emailDisponivel = smtp_configurado() || getenv('APP_DEBUG') === '1';

if ($emailDisponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = 'Informe um e-mail válido.';

    } else {

        $stmt = $conn->prepare("
            SELECT id, nome, email
            FROM usuarios
            WHERE email = ? AND ativo = 1
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if ($usuario) {

            $stmt = $conn->prepare("
                SELECT criado_em
                FROM recuperacao_senha
                WHERE usuario_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->bind_param('i', $usuario['id']);
            $stmt->execute();
            $ultimo = $stmt->get_result()->fetch_assoc();

            $podeEnviar = !$ultimo
                || (time() - strtotime($ultimo['criado_em'])) > 120;

            if ($podeEnviar) {

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiraEm = date('Y-m-d H:i:s', time() + 3600);
                $agora = date('Y-m-d H:i:s');

                $stmt = $conn->prepare("
                    UPDATE recuperacao_senha
                    SET usado_em = ?
                    WHERE usuario_id = ? AND usado_em IS NULL
                ");
                $stmt->bind_param('si', $agora, $usuario['id']);
                $stmt->execute();

                $stmt = $conn->prepare("
                    INSERT INTO recuperacao_senha (usuario_id, token_hash, expira_em, criado_em)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param('isss', $usuario['id'], $tokenHash, $expiraEm, $agora);
                $stmt->execute();

                $base = rtrim((string) getenv('APP_URL'), '/');

                if ($base === '') {
                    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                    $pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
                    $base = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $pasta;
                }

                $link = $base . '/redefinir_senha.php?token=' . $token;

                $nome = e($usuario['nome']);
                $linkHtml = e($link);

                $html = "
                    <div style=\"font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#222\">
                        <h2 style=\"color:#f97316\">StrikeSet Gaspar</h2>
                        <p>Olá, {$nome}!</p>
                        <p>Recebemos um pedido para redefinir a senha da sua conta.
                           Clique no botão abaixo para criar uma nova senha:</p>
                        <p style=\"text-align:center;margin:28px 0\">
                            <a href=\"{$linkHtml}\" style=\"background:#f97316;color:#fff;padding:12px 26px;border-radius:8px;text-decoration:none;font-weight:bold\">Redefinir senha</a>
                        </p>
                        <p style=\"font-size:13px;color:#666\">O link vale por 1 hora e só pode ser usado uma vez.
                           Se você não pediu a troca de senha, ignore este e-mail.</p>
                    </div>";

                $texto = "Olá, {$usuario['nome']}!\n\n"
                    . "Para redefinir sua senha do StrikeSet Gaspar, acesse:\n{$link}\n\n"
                    . "O link vale por 1 hora. Se você não pediu a troca, ignore este e-mail.";

                $foiEnviado = enviar_email(
                    $usuario['email'],
                    $usuario['nome'],
                    'Redefinição de senha - StrikeSet Gaspar',
                    $html,
                    $texto
                );

                if (!$foiEnviado && getenv('APP_DEBUG') === '1') {
                    $linkDev = $link;
                }
            }
        }

        $enviado = true;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci minha senha | StrikeSet Gaspar</title>
    <link rel="stylesheet" href="visual/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="visual/css/login.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="auth-container auth-container--unico">

        <div class="auth-card">
            <h2>Esqueci a senha</h2>

            <?php if (!$emailDisponivel): ?>

                <p class="auth-sub">Recuperação por e-mail indisponível.</p>

                <div class="auth-alert auth-alert--aviso" role="alert">
                    A recuperação de senha por e-mail ainda não foi ativada neste site.
                    Fale com um administrador do StrikeSet.
                </div>

                <a class="btn-auth btn-auth--link" href="login.php#login">Voltar ao login</a>

            <?php elseif ($enviado): ?>

                <p class="auth-sub">Verifique sua caixa de entrada.</p>

                <div class="auth-alert auth-alert--sucesso" role="alert">
                    Se existir uma conta com esse e-mail, enviamos um link para criar uma nova senha.
                    O link vale por 1 hora. Confira também a pasta de spam.
                </div>

                <?php if ($linkDev): ?>
                    <div class="auth-alert auth-alert--aviso" role="alert">
                        <strong>Modo de desenvolvimento:</strong> o SMTP não está configurado,
                        então o e-mail não foi enviado. Use o link abaixo para testar:<br>
                        <a href="<?= e($linkDev) ?>">Redefinir senha</a>
                    </div>
                <?php endif; ?>

                <a class="btn-auth btn-auth--link" href="login.php#login">Voltar ao login</a>

            <?php else: ?>

                <p class="auth-sub">Digite o e-mail da sua conta e enviaremos um link para você criar uma nova senha.</p>

                <?php if ($erro): ?>
                    <div class="auth-alert auth-alert--erro" role="alert"><?= e($erro) ?></div>
                <?php endif; ?>

                <form class="auth-form" method="POST" action="esqueci_senha.php">
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="Digite seu e-mail"
                               autocomplete="email" value="<?= e($email) ?>" required autofocus>
                    </div>

                    <button type="submit" class="btn-auth">Enviar link</button>
                </form>

                <div class="text-small">
                    Lembrou a senha? <a href="login.php#login">Voltar ao login</a>
                </div>

            <?php endif; ?>
        </div>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
