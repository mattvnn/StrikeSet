<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$todos = $conn->query("
    SELECT *
    FROM campeonatos
    ORDER BY
        FIELD(tipo, 'proximo', 'disputado', 'conquista'),
        CASE WHEN tipo = 'proximo' THEN data_inicio END IS NULL,
        CASE WHEN tipo = 'proximo' THEN data_inicio END ASC,
        data_inicio DESC,
        id DESC
")->fetch_all(MYSQLI_ASSOC);

$hoje = date('Y-m-d');

$proximos = array_filter($todos, fn($c) => $c['tipo'] === 'proximo'
    && (!$c['data_inicio'] || ($c['data_fim'] ?: $c['data_inicio']) >= $hoje));
$disputados = array_filter($todos, fn($c) => $c['tipo'] === 'disputado');
$conquistas = array_filter($todos, fn($c) => $c['tipo'] === 'conquista');

$statusLabels = [
    'aberto' => 'Inscrições abertas',
    'confirmado' => 'Confirmado',
    'analise' => 'Em análise',
    'finalizado' => 'Finalizado'
];

$classeColocacao = fn($coloc) => match ($coloc) {
    '1º lugar' => 'coloc-ouro',
    '2º lugar' => 'coloc-prata',
    '3º lugar' => 'coloc-bronze',
    default => 'coloc-neutro',
};

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campeonatos | StrikeSet Gaspar</title>
    <link rel="stylesheet" href="visual/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="visual/css/campeonatos.css?v=<?php echo time(); ?>">
</head>

<body class="pagina-campeonatos">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="camp-hero">
    <div class="camp-hero-overlay"></div>
    <div class="camp-hero-inner">

        <div class="camp-hero-esquerda">
            <span class="camp-tag">StrikeSet Gaspar</span>
            <h1>Campeonatos</h1>
            <p>A StrikeSet compete em torneios universitários e regionais ao longo do ano, representando o IFSC Gaspar dentro e fora de quadra.</p>
        </div>

        <div class="camp-hero-direita">
            <a href="galeria.php" class="hero-galeria-btn">Ver galeria de fotos</a>
        </div>

    </div>
</section>

<section class="proximos">
    <div class="sec-inner">
        <div class="linha"></div>
        <h2>Próximos campeonatos</h2>
        <div class="proximos-grid">

            <?php if (empty($proximos)): ?>
                <p class="camp-vazio">Nenhum campeonato agendado no momento. Fique de olho!</p>
            <?php endif; ?>

            <?php foreach ($proximos as $c): ?>
                <div class="camp-card">
                    <div class="camp-card-topo">
                        <?php if ($c['status']): ?>
                            <span class="camp-status status-<?= e($c['status']) ?>"><?= e($statusLabels[$c['status']] ?? $c['status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h3><?= e($c['nome']) ?></h3>
                    <div class="camp-card-info">
                        <div class="camp-info-item">
                            <span class="info-label">Local</span>
                            <span class="info-val"><?= e($c['local'] ?: 'A definir') ?></span>
                        </div>
                        <div class="camp-info-item">
                            <span class="info-label">Data</span>
                            <span class="info-val"><?= e(periodo_extenso($c['data_inicio'], $c['data_fim']) ?: 'A definir') ?></span>
                        </div>
                    </div>
                    <a href="login.php" class="camp-btn">Ver detalhes</a>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
</section>

<section class="disputados">
    <div class="sec-inner">
        <div class="linha"></div>
        <h2>Campeonatos disputados</h2>
        <div class="disputados-lista">

            <?php if (empty($disputados)): ?>
                <p class="camp-vazio">Nenhum campeonato disputado cadastrado ainda.</p>
            <?php endif; ?>

            <?php foreach ($disputados as $c): ?>
                <div class="disp-card">
                    <div class="disp-ano"><?= e(ano_da_data($c['data_inicio']) ?: '—') ?></div>
                    <div class="disp-corpo">
                        <div class="disp-topo">
                            <h3><?= e($c['nome']) ?></h3>
                            <?php if ($c['colocacao']): ?>
                                <span class="disp-coloc <?= $classeColocacao($c['colocacao']) ?>"><?= e($c['colocacao']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
</section>

<section class="conquistas">
    <div class="sec-inner">
        <div class="linha"></div>
        <h2>Conquistas</h2>
        <div class="conquistas-grid">

            <?php if (empty($conquistas)): ?>
                <p class="camp-vazio">As conquistas do time vão aparecer aqui.</p>
            <?php endif; ?>

            <?php foreach ($conquistas as $c): ?>
                <div class="conq-card">
                    <div class="conq-icone"><?= e($c['icone'] ?: '🏆') ?></div>
                    <h4><?= e($c['nome']) ?></h4>
                    <p><?= e($c['descricao'] ?: ano_da_data($c['data_inicio'])) ?></p>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
</section>

<section class="camp-cta">
    <div class="camp-cta-inner">
        <h2>Quer competir com a gente?</h2>
        <p>A StrikeSet está sempre buscando novos atletas. Se você gosta de vôlei e quer fazer parte dos próximos campeonatos, vem conversar com a gente.</p>
        <a href="login.php" class="cta-btn">QUERO PARTICIPAR</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
