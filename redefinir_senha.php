<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$erro = '';
$pedido = null;

/* Procura um pedido válido: token existe, não foi usado e não expirou */
if (preg_match('/^[a-f0-9]{64}$/', $token)) {

    $tokenHash = hash('sha256', $token);

    $stmt = $conn->prepare("
        SELECT r.id, r.usuario_id, r.expira_em, r.usado_em, u.nome
        FROM recuperacao_senha r
        JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.token_hash = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();

    if ($pedido && ($pedido['usado_em'] !== null || $pedido['expira_em'] < date('Y-m-d H:i:s'))) {
        $pedido = null;
    }
}

if ($pedido && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $senha = $_POST['senha'] ?? '';
    $repetir = $_POST['repetir_senha'] ?? '';

    if (strlen($senha) < 6) {

        $erro = 'A senha deve possuir pelo menos 6 caracteres.';

    } elseif ($senha !== $repetir) {

        $erro = 'As senhas não coincidem.';

    } else {

        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $agora = date('Y-m-d H:i:s');

        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $pedido['usuario_id']);
        $stmt->execute();

        /* Marca este e qualquer outro pedido pendente do usuário como usado */
        $stmt = $conn->prepare("
            UPDATE recuperacao_senha
            SET usado_em = ?
            WHERE usuario_id = ? AND usado_em IS NULL
        ");
        $stmt->bind_param('si', $agora, $pedido['usuario_id']);
        $stmt->execute();

        $conn->commit();

        header('Location: login.php?senha=redefinida#login');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova senha | StrikeSet Gaspar</title>
    <link rel="stylesheet" href="visual/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="visual/css/login.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="auth-container auth-container--unico">

        <div class="auth-card">
            <h2>Nova senha</h2>

            <?php if (!$pedido): ?>

                <p class="auth-sub">Não foi possível usar este link.</p>

                <div class="auth-alert auth-alert--erro" role="alert">
                    O link é inválido, já foi usado ou expirou. Peça um novo link para redefinir sua senha.
                </div>

                <a class="btn-auth btn-auth--link" href="esqueci_senha.php">Pedir novo link</a>

            <?php else: ?>

                <p class="auth-sub">Olá, <?= e($pedido['nome']) ?>! Escolha sua nova senha.</p>

                <?php if ($erro): ?>
                    <div class="auth-alert auth-alert--erro" role="alert"><?= e($erro) ?></div>
                <?php endif; ?>

                <form class="auth-form" id="form-nova-senha" method="POST" action="redefinir_senha.php" novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="form-group">
                        <label for="senha-cadastro">Nova senha</label>
                        <input type="password" id="senha-cadastro" name="senha"
                               placeholder="Mínimo 6 caracteres" autocomplete="new-password"
                               minlength="6" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="senha-repetir">Repetir nova senha</label>
                        <input type="password" id="senha-repetir" name="repetir_senha"
                               placeholder="Repita a senha" autocomplete="new-password"
                               aria-describedby="senha-hint" required>
                        <small class="field-hint" id="senha-hint" aria-live="polite"></small>
                    </div>

                    <button type="submit" class="btn-auth">Salvar nova senha</button>
                </form>

            <?php endif; ?>
        </div>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="visual/js/confere_senha.js"></script>
    <script>conferirSenhas('form-nova-senha');</script>
</body>
</html>
