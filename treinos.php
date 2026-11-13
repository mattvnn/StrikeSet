<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$logado = isset($_SESSION['usuario_id']);
$usuarioId = $logado ? (int) $_SESSION['usuario_id'] : 0;


/*
|--------------------------------------------------------------------------
| AÇÕES (inscrever / cancelar) → sempre redireciona no final (POST → GET)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$logado) {
        header('Location: login.php?voltar=treinos.php#login');
        exit;
    }

    $acao = $_POST['acao'] ?? '';
    $treinoId = (int) ($_POST['treino_id'] ?? 0);

    /* ---------------- INSCREVER ---------------- */

    if ($acao === 'inscrever') {

        $telefone = trim($_POST['telefone'] ?? '');
        $digitos = preg_replace('/\D/', '', $telefone);

        $stmt = $conn->prepare("SELECT id, titulo FROM treinos WHERE id = ?");
        $stmt->bind_param('i', $treinoId);
        $stmt->execute();
        $treino = $stmt->get_result()->fetch_assoc();

        if (!$treino) {

            flash('erro', 'Selecione um treino válido.');

        } elseif (strlen($digitos) < 10 || strlen($digitos) > 13) {

            flash('erro', 'Informe um telefone válido com DDD.');

        } else {

            $stmt = $conn->prepare("
                SELECT id, status
                FROM inscricoes_treino
                WHERE usuario_id = ? AND treino_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ii', $usuarioId, $treinoId);
            $stmt->execute();
            $existente = $stmt->get_result()->fetch_assoc();

            if ($existente && $existente['status'] !== 'cancelada') {

                flash('erro', 'Você já está inscrito no treino "' . $treino['titulo'] . '".');

            } elseif ($existente) {

                /* Tinha cancelado antes: reativa a mesma inscrição */
                $stmt = $conn->prepare("
                    UPDATE inscricoes_treino
                    SET status = 'pendente', telefone = ?, criado_em = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->bind_param('si', $telefone, $existente['id']);
                $stmt->execute();

                flash('sucesso', 'Inscrição no treino "' . $treino['titulo'] . '" feita de novo! Aguarde a confirmação.');

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO inscricoes_treino (usuario_id, treino_id, telefone)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param('iis', $usuarioId, $treinoId, $telefone);
                $stmt->execute();

                flash('sucesso', 'Inscrição no treino "' . $treino['titulo'] . '" realizada! Aguarde a confirmação.');
            }
        }

        header('Location: treinos.php#inscricao');
        exit;
    }

    /* ---------------- CANCELAR ---------------- */

    if ($acao === 'cancelar') {

        $stmt = $conn->prepare("
            UPDATE inscricoes_treino
            SET status = 'cancelada'
            WHERE usuario_id = ? AND treino_id = ? AND status <> 'cancelada'
        ");
        $stmt->bind_param('ii', $usuarioId, $treinoId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            flash('sucesso', 'Inscrição cancelada.');
        }

        header('Location: treinos.php#minhas-inscricoes');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| LEITURA
|--------------------------------------------------------------------------
*/

$treinos = $conn->query("
    SELECT
        t.id, t.dia, t.titulo, t.hora_inicio, t.hora_fim, t.descricao, t.nivel,
        (SELECT COUNT(*) FROM inscricoes_treino i
          WHERE i.treino_id = t.id AND i.status <> 'cancelada') AS inscritos
    FROM treinos t
    ORDER BY FIELD(t.dia, 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'), t.hora_inicio
")->fetch_all(MYSQLI_ASSOC);

/* Inscrições do usuário logado, indexadas pelo id do treino */
$minhas = [];
$ultimoTelefone = '';

if ($logado) {

    $stmt = $conn->prepare("
        SELECT i.treino_id, i.status, i.telefone, i.criado_em,
               t.dia, t.titulo, t.hora_inicio, t.hora_fim
        FROM inscricoes_treino i
        JOIN treinos t ON t.id = i.treino_id
        WHERE i.usuario_id = ?
        ORDER BY FIELD(t.dia, 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'), t.hora_inicio
    ");
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();

    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $i) {
        $minhas[(int) $i['treino_id']] = $i;
    }

    /* Sugere o telefone usado na última inscrição */
    $stmt = $conn->prepare("
        SELECT telefone FROM inscricoes_treino
        WHERE usuario_id = ? AND telefone IS NOT NULL AND telefone <> ''
        ORDER BY criado_em DESC LIMIT 1
    ");
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $ultimoTelefone = $stmt->get_result()->fetch_row()[0] ?? '';
}

/* Inscrição "ativa" = pendente ou confirmada */
$ativa = fn($treinoId) => isset($minhas[$treinoId]) && $minhas[$treinoId]['status'] !== 'cancelada';

$disponiveis = array_values(array_filter($treinos, fn($t) => !$ativa((int) $t['id'])));
$inscricoesAtivas = array_filter($minhas, fn($i) => $i['status'] !== 'cancelada');

$treinoEscolhido = (int) ($_GET['treino'] ?? 0);

$niveis = [
    'iniciante' => 'Iniciante',
    'intermediario' => 'Intermediário',
    'avancado' => 'Avançado',
    'todos' => 'Todos os níveis'
];

$statusInscricao = [
    'pendente' => 'Aguardando confirmação',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada'
];

$mensagem = pegar_flash();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Treinos | StrikeSet Gaspar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="visual/css/global.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="visual/css/treinos.css?v=<?php echo time(); ?>">
</head>
<body>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="treinos-section">

    <div class="treinos-header">
        <h1>Nossos Treinos</h1>
        <p>Confira o cronograma semanal do StrikeSet Gaspar e garanta sua vaga no próximo treino!</p>
        <div class="local-info">Ginásio do IFSC Gaspar</div>
    </div>

    <h2 class="section-title">Cronograma Semanal</h2>

    <div class="cronograma-grid">

        <?php if (empty($treinos)): ?>

            <p class="sem-registros">Nenhum treino cadastrado no momento.</p>

        <?php else: ?>

            <?php foreach ($treinos as $treino): $id = (int) $treino['id']; ?>

                <div class="treino-card<?= $ativa($id) ? ' treino-card--inscrito' : '' ?>">

                    <div class="dia-tag"><?= e($treino['dia']) ?></div>

                    <div class="treino-card-body">
                        <h5><?= e($treino['titulo']) ?></h5>

                        <p class="horario"><?= e(horario_treino($treino['hora_inicio'], $treino['hora_fim'])) ?></p>

                        <p class="descricao"><?= e($treino['descricao']) ?></p>

                        <div class="treino-card-rodape">
                            <span class="nivel <?= e($treino['nivel']) ?>">
                                <?= e($niveis[$treino['nivel']] ?? $treino['nivel']) ?>
                            </span>

                            <span class="treino-inscritos">
                                <?= (int) $treino['inscritos'] ?> inscrito<?= (int) $treino['inscritos'] === 1 ? '' : 's' ?>
                            </span>
                        </div>

                        <?php if ($ativa($id)): ?>
                            <span class="treino-selo treino-selo--<?= e($minhas[$id]['status']) ?>">
                                ✓ <?= $minhas[$id]['status'] === 'confirmada' ? 'Inscrição confirmada' : 'Você está inscrito' ?>
                            </span>
                        <?php else: ?>
                            <a class="treino-btn" href="treinos.php?treino=<?= $id ?>#inscricao">Quero participar</a>
                        <?php endif; ?>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <div class="inscricao-wrapper" id="inscricao">

        <div class="inscricao-card">

            <h2>Inscreva-se em um Treino</h2>

            <?php if ($mensagem): ?>
                <p class="form-feedback form-feedback--caixa <?= e($mensagem['tipo']) ?>" role="alert">
                    <?= e($mensagem['texto']) ?>
                </p>
            <?php endif; ?>

            <?php if (!$logado): ?>

                <p class="inscricao-sub">Para se inscrever você precisa estar logado. É rapidinho!</p>

                <div class="inscricao-login">
                    <a class="btn-inscrever" href="login.php?voltar=treinos.php#login">ENTRAR</a>
                    <a class="btn-inscrever btn-inscrever--contorno" href="login.php?voltar=treinos.php#cadastro">CRIAR CONTA</a>
                </div>

            <?php elseif (empty($treinos)): ?>

                <p class="inscricao-sub">Ainda não há treinos cadastrados.</p>

            <?php elseif (empty($disponiveis)): ?>

                <p class="inscricao-sub">Você já está inscrito em todos os treinos da semana. 🏐</p>

            <?php else: ?>

                <p class="inscricao-sub">
                    Olá, <?= e($_SESSION['usuario_nome'] ?? '') ?>! Escolha o treino e deixe seu WhatsApp para contato.
                </p>

                <form class="inscricao-form" method="POST" action="treinos.php">
                    <input type="hidden" name="acao" value="inscrever">

                    <div class="form-group">
                        <label for="treino">Qual treino você quer fazer?</label>
                        <select id="treino" name="treino_id" required>
                            <option value="">Selecione um treino...</option>
                            <?php foreach ($disponiveis as $treino): ?>
                                <option value="<?= (int) $treino['id'] ?>"
                                    <?= (int) $treino['id'] === $treinoEscolhido ? 'selected' : '' ?>>
                                    <?= e($treino['dia']) ?> – <?= e($treino['titulo']) ?>
                                    (<?= e(horario_treino($treino['hora_inicio'], $treino['hora_fim'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="telefone">Telefone (WhatsApp)</label>
                        <input type="tel" id="telefone" name="telefone" maxlength="20"
                               placeholder="(47) 99999-9999" value="<?= e($ultimoTelefone) ?>"
                               autocomplete="tel" required>
                    </div>

                    <button type="submit" class="btn-inscrever">QUERO ME INSCREVER</button>
                </form>

            <?php endif; ?>

        </div>

        <?php if ($logado && !empty($minhas)): ?>

            <div class="inscricao-card minhas-inscricoes" id="minhas-inscricoes">

                <h2>Minhas inscrições</h2>

                <ul class="minhas-lista">
                    <?php foreach ($minhas as $treinoId => $i): ?>
                        <li class="minha-inscricao minha-inscricao--<?= e($i['status']) ?>">
                            <span class="dia-tag"><?= e($i['dia']) ?></span>

                            <div class="minha-info">
                                <strong><?= e($i['titulo']) ?></strong>
                                <span><?= e(horario_treino($i['hora_inicio'], $i['hora_fim'])) ?></span>
                            </div>

                            <span class="status-inscricao status-inscricao--<?= e($i['status']) ?>">
                                <?= e($statusInscricao[$i['status']] ?? $i['status']) ?>
                            </span>

                            <?php if ($i['status'] !== 'cancelada'): ?>
                                <form method="POST" action="treinos.php"
                                      onsubmit="return confirm('Cancelar sua inscrição neste treino?');">
                                    <input type="hidden" name="acao" value="cancelar">
                                    <input type="hidden" name="treino_id" value="<?= (int) $treinoId ?>">
                                    <button type="submit" class="btn-cancelar-inscricao">Cancelar</button>
                                </form>
                            <?php else: ?>
                                <a class="btn-reinscrever" href="treinos.php?treino=<?= (int) $treinoId ?>#inscricao">Inscrever de novo</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>

    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
