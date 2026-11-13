<?php

require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$dias = [
    'SEG' => 'Segunda-feira',
    'TER' => 'Terça-feira',
    'QUA' => 'Quarta-feira',
    'QUI' => 'Quinta-feira',
    'SEX' => 'Sexta-feira',
    'SAB' => 'Sábado'
];

$niveis = [
    'iniciante' => 'Iniciante',
    'intermediario' => 'Intermediário',
    'avancado' => 'Avançado',
    'todos' => 'Todos'
];

$statusInscricao = [
    'pendente' => 'Pendente',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada'
];


/*
|--------------------------------------------------------------------------
| AÇÕES (POST → redirect → GET)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';
    $voltarPara = 'admin_treinos.php';

    /* ---------- Criar / editar treino ---------- */

    if ($acao === 'salvar' || $acao === 'editar') {

        $id = (int) ($_POST['id'] ?? 0);
        $dia = $_POST['dia'] ?? '';
        $titulo = trim($_POST['titulo'] ?? '');
        $horaInicio = validar_hora($_POST['hora_inicio'] ?? '');
        $horaFim = validar_hora($_POST['hora_fim'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $nivel = $_POST['nivel'] ?? '';

        if (!isset($dias[$dia]) || !isset($niveis[$nivel]) || $titulo === '') {

            flash('erro', 'Preencha dia, título e nível.');

        } elseif (!$horaInicio) {

            flash('erro', 'Informe o horário de início.');

        } elseif ($horaFim && $horaFim <= $horaInicio) {

            flash('erro', 'O horário de término precisa ser depois do início.');

        } elseif ($acao === 'salvar') {

            $stmt = $conn->prepare("
                INSERT INTO treinos (dia, titulo, hora_inicio, hora_fim, descricao, nivel)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssss', $dia, $titulo, $horaInicio, $horaFim, $descricao, $nivel);
            $stmt->execute();

            flash('sucesso', 'Treino criado com sucesso!');

        } else {

            $stmt = $conn->prepare("
                UPDATE treinos
                SET dia = ?, titulo = ?, hora_inicio = ?, hora_fim = ?, descricao = ?, nivel = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ssssssi', $dia, $titulo, $horaInicio, $horaFim, $descricao, $nivel, $id);
            $stmt->execute();

            flash('sucesso', 'Treino atualizado com sucesso!');
        }
    }

    /* ---------- Excluir treino ---------- */

    if ($acao === 'excluir') {

        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM treinos WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        flash('sucesso', 'Treino excluído (as inscrições dele também foram removidas).');
    }

    /* ---------- Inscrições: confirmar / cancelar / excluir ---------- */

    if (in_array($acao, ['confirmar_inscricao', 'cancelar_inscricao', 'excluir_inscricao'], true)) {

        $id = (int) ($_POST['id'] ?? 0);

        if ($acao === 'excluir_inscricao') {
            $stmt = $conn->prepare("DELETE FROM inscricoes_treino WHERE id = ?");
            $stmt->bind_param('i', $id);
            flash('sucesso', 'Inscrição removida.');
        } else {
            $novo = $acao === 'confirmar_inscricao' ? 'confirmada' : 'cancelada';
            $stmt = $conn->prepare("UPDATE inscricoes_treino SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $novo, $id);
            flash('sucesso', $novo === 'confirmada' ? 'Inscrição confirmada!' : 'Inscrição cancelada.');
        }

        $stmt->execute();

        /* Mantém os filtros que estavam aplicados */
        $voltarPara .= '?' . http_build_query([
            'f_treino' => $_POST['f_treino'] ?? '',
            'f_status' => $_POST['f_status'] ?? '',
        ]) . '#inscricoes';
    }

    header('Location: ' . $voltarPara);
    exit;
}


/*
|--------------------------------------------------------------------------
| LEITURA
|--------------------------------------------------------------------------
*/

$treinos = $conn->query("
    SELECT t.*,
        SUM(i.status = 'pendente')   AS pendentes,
        SUM(i.status = 'confirmada') AS confirmadas
    FROM treinos t
    LEFT JOIN inscricoes_treino i ON i.treino_id = t.id
    GROUP BY t.id
    ORDER BY FIELD(t.dia, 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'), t.hora_inicio
")->fetch_all(MYSQLI_ASSOC);

/* Filtros das inscrições */
$fTreino = (int) ($_GET['f_treino'] ?? 0);
$fStatus = $_GET['f_status'] ?? '';
$fStatus = isset($statusInscricao[$fStatus]) ? $fStatus : '';

$sql = "
    SELECT i.id, i.status, i.telefone, i.criado_em,
           u.nome, u.email,
           t.dia, t.titulo, t.hora_inicio, t.hora_fim
    FROM inscricoes_treino i
    JOIN usuarios u ON u.id = i.usuario_id
    JOIN treinos t ON t.id = i.treino_id
    WHERE (? = 0 OR i.treino_id = ?)
      AND (? = '' OR i.status = ?)
    ORDER BY FIELD(i.status, 'pendente', 'confirmada', 'cancelada'), i.criado_em DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iiss', $fTreino, $fTreino, $fStatus, $fStatus);
$stmt->execute();
$inscricoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totais = $conn->query("
    SELECT
        SUM(status = 'pendente')   AS pendentes,
        SUM(status = 'confirmada') AS confirmadas
    FROM inscricoes_treino
")->fetch_assoc();

$mensagem = pegar_flash();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Treinos | StrikeSet Gaspar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="visual/css/global.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="visual/css/admin.css?v=<?php echo time(); ?>">
</head>

<body class="pagina-admin">

<?php require_once __DIR__ . '/includes/header_admin.php'; ?>

<section class="admin-hero">
    <div class="admin-hero-inner">

        <div class="admin-hero-texto">
            <span class="admin-hero-tag">Administração</span>
            <h1>Gerenciar Treinos</h1>
            <p>Monte o cronograma semanal e acompanhe as inscrições dos jogadores.</p>
        </div>

        <button type="button" class="btn-novo" data-bs-toggle="modal" data-bs-target="#modalTreino" data-novo>
            + Novo Treino
        </button>

    </div>
</section>

<section class="admin-stats">
    <div class="admin-inner">
        <div class="stats-grid">
            <div class="stat-card">
                <strong><?= count($treinos) ?></strong>
                <span>Treinos na semana</span>
            </div>
            <div class="stat-card stat-card-destaque">
                <strong><?= (int) $totais['pendentes'] ?></strong>
                <span>Inscrições pendentes</span>
            </div>
            <div class="stat-card">
                <strong><?= (int) $totais['confirmadas'] ?></strong>
                <span>Inscrições confirmadas</span>
            </div>
            <div class="stat-card">
                <strong><?= (int) $totais['pendentes'] + (int) $totais['confirmadas'] ?></strong>
                <span>Inscrições ativas</span>
            </div>
        </div>
    </div>
</section>

<section class="admin-conteudo">
    <div class="admin-inner">

        <?php if ($mensagem): ?>
            <div class="admin-alerta admin-alerta--<?= e($mensagem['tipo']) ?>" role="alert">
                <?= e($mensagem['texto']) ?>
            </div>
        <?php endif; ?>

        <!-- ================= TREINOS ================= -->

        <div class="admin-secao">

            <div class="admin-secao-topo">
                <div class="admin-secao-titulo">
                    <span class="barra"></span>
                    <h2>Cronograma</h2>
                </div>
                <span class="admin-secao-contagem"><?= count($treinos) ?> cadastrados</span>
            </div>

            <?php if (empty($treinos)): ?>

                <p class="admin-vazio">Nenhum treino cadastrado ainda. Clique em “+ Novo Treino”.</p>

            <?php else: ?>

                <div class="tabela-wrapper">
                    <table class="tabela-admin">
                        <thead>
                            <tr>
                                <th>Dia</th>
                                <th>Treino</th>
                                <th>Horário</th>
                                <th>Nível</th>
                                <th>Inscritos</th>
                                <th>Descrição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treinos as $t): ?>
                                <tr>
                                    <td><span class="tag tag-dia"><?= e($t['dia']) ?></span></td>
                                    <td class="td-forte td-nowrap"><?= e($t['titulo']) ?></td>
                                    <td class="td-nowrap"><?= e(horario_treino($t['hora_inicio'], $t['hora_fim'])) ?></td>
                                    <td>
                                        <span class="tag tag-<?= e($t['nivel']) ?>">
                                            <?= e($niveis[$t['nivel']] ?? $t['nivel']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="contador-inscritos" href="admin_treinos.php?f_treino=<?= (int) $t['id'] ?>#inscricoes"
                                           title="Ver inscrições deste treino">
                                            <?= (int) $t['confirmadas'] ?>
                                            <small>conf. · <?= (int) $t['pendentes'] ?> pend.</small>
                                        </a>
                                    </td>
                                    <td class="td-desc" title="<?= e($t['descricao']) ?>"><?= e($t['descricao']) ?></td>
                                    <td>
                                        <div class="tabela-acoes">
                                            <button type="button" class="acao-btn acao-editar"
                                                data-bs-toggle="modal" data-bs-target="#modalTreino"
                                                data-treino='<?= e(json_encode([
                                                    'id' => (int) $t['id'],
                                                    'dia' => $t['dia'],
                                                    'titulo' => $t['titulo'],
                                                    'hora_inicio' => hora_input($t['hora_inicio']),
                                                    'hora_fim' => hora_input($t['hora_fim']),
                                                    'nivel' => $t['nivel'],
                                                    'descricao' => $t['descricao'] ?? '',
                                                ])) ?>'>
                                                Editar
                                            </button>

                                            <form method="POST" onsubmit="return confirm('Excluir o treino &quot;<?= e($t['titulo']) ?>&quot;? As inscrições dele também serão apagadas.');">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                                <button type="submit" class="acao-btn acao-excluir">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>

        <!-- ================= INSCRIÇÕES ================= -->

        <div class="admin-secao" id="inscricoes">

            <div class="admin-secao-topo">
                <div class="admin-secao-titulo">
                    <span class="barra"></span>
                    <h2>Inscrições</h2>
                </div>
                <span class="admin-secao-contagem"><?= count($inscricoes) ?> encontradas</span>
            </div>

            <form class="admin-filtros" method="GET" action="admin_treinos.php#inscricoes">
                <div class="filtro-selects">
                    <select name="f_treino" class="filtro-select" onchange="this.form.submit()">
                        <option value="0">Todos os treinos</option>
                        <?php foreach ($treinos as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= $fTreino === (int) $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['dia'] . ' – ' . $t['titulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="f_status" class="filtro-select" onchange="this.form.submit()">
                        <option value="">Todos os status</option>
                        <?php foreach ($statusInscricao as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $fStatus === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="acao-btn acao-editar">Filtrar</button></noscript>
            </form>

            <?php if (empty($inscricoes)): ?>

                <p class="admin-vazio">Nenhuma inscrição encontrada.</p>

            <?php else: ?>

                <div class="tabela-wrapper">
                    <table class="tabela-admin">
                        <thead>
                            <tr>
                                <th>Jogador</th>
                                <th>Contato</th>
                                <th>Treino</th>
                                <th>Inscrito em</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscricoes as $i): ?>
                                <tr>
                                    <td class="td-forte"><?= e($i['nome']) ?></td>
                                    <td>
                                        <?= e($i['email']) ?><br>
                                        <span class="td-fraco"><?= e($i['telefone'] ?: 'sem telefone') ?></span>
                                    </td>
                                    <td>
                                        <span class="tag tag-dia"><?= e($i['dia']) ?></span>
                                        <?= e($i['titulo']) ?><br>
                                        <span class="td-fraco"><?= e(horario_treino($i['hora_inicio'], $i['hora_fim'])) ?></span>
                                    </td>
                                    <td class="td-fraco"><?= e(data_curta($i['criado_em'])) ?></td>
                                    <td>
                                        <span class="tag tag-<?= e($i['status']) ?>"><?= e($statusInscricao[$i['status']]) ?></span>
                                    </td>
                                    <td>
                                        <div class="tabela-acoes">
                                            <?php
                                            $botoes = [];
                                            if ($i['status'] !== 'confirmada') $botoes[] = ['confirmar_inscricao', 'Confirmar', 'acao-confirmar', ''];
                                            if ($i['status'] !== 'cancelada')  $botoes[] = ['cancelar_inscricao', 'Cancelar', 'acao-editar', ''];
                                            $botoes[] = ['excluir_inscricao', 'Excluir', 'acao-excluir', "return confirm('Apagar esta inscrição de vez?');"];
                                            ?>
                                            <?php foreach ($botoes as [$acao, $rotulo, $classe, $confirmar]): ?>
                                                <form method="POST" <?= $confirmar ? 'onsubmit="' . $confirmar . '"' : '' ?>>
                                                    <input type="hidden" name="acao" value="<?= $acao ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                                                    <input type="hidden" name="f_treino" value="<?= $fTreino ?: '' ?>">
                                                    <input type="hidden" name="f_status" value="<?= e($fStatus) ?>">
                                                    <button type="submit" class="acao-btn <?= $classe ?>"><?= $rotulo ?></button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>

    </div>
</section>

<!-- ================= MODAL CRIAR / EDITAR ================= -->

<div class="modal fade" id="modalTreino" tabindex="-1" aria-labelledby="modalTreinoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="admin_treinos.php" class="modal-content admin-modal-content" id="formTreino">

            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" value="">

            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="modalTreinoTitulo">Novo Treino</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body admin-modal-body">
                <div class="admin-form">

                    <div class="form-grupo form-grupo-full">
                        <label for="titulo">Título</label>
                        <input type="text" id="titulo" name="titulo" maxlength="100" placeholder="Ex: Treino de fundamentos" required>
                    </div>

                    <div class="form-grupo">
                        <label for="dia">Dia da semana</label>
                        <select id="dia" name="dia" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($dias as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grupo">
                        <label for="nivel">Nível</label>
                        <select id="nivel" name="nivel" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($niveis as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grupo">
                        <label for="hora_inicio">Começa às</label>
                        <input type="time" id="hora_inicio" name="hora_inicio" required>
                    </div>

                    <div class="form-grupo">
                        <label for="hora_fim">Termina às <span class="opcional">(opcional)</span></label>
                        <input type="time" id="hora_fim" name="hora_fim">
                    </div>

                    <div class="form-grupo form-grupo-full">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="3" maxlength="500" placeholder="Descreva o conteúdo do treino..."></textarea>
                    </div>

                </div>
            </div>

            <div class="modal-footer admin-modal-footer">
                <button type="button" class="btn-modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-modal-salvar">Salvar treino</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Preenche o mesmo modal para "Novo" ou "Editar" */
document.getElementById('modalTreino').addEventListener('show.bs.modal', function (evento) {
    const form = document.getElementById('formTreino');
    const botao = evento.relatedTarget;
    const dados = botao && botao.dataset.treino ? JSON.parse(botao.dataset.treino) : null;

    form.reset();
    form.elements['acao'].value = dados ? 'editar' : 'salvar';
    form.elements['id'].value = dados ? dados.id : '';
    document.getElementById('modalTreinoTitulo').textContent = dados ? 'Editar Treino' : 'Novo Treino';
    form.querySelector('.btn-modal-salvar').textContent = dados ? 'Salvar alterações' : 'Salvar treino';

    if (dados) {
        ['titulo', 'dia', 'nivel', 'hora_inicio', 'hora_fim', 'descricao'].forEach(function (campo) {
            form.elements[campo].value = dados[campo] || '';
        });
    }
});

/* Término não pode ser antes do início (confere a cada alteração) */
(function () {
    const form = document.getElementById('formTreino');
    const conferir = function () {
        const ini = form.elements['hora_inicio'].value, fim = form.elements['hora_fim'].value;
        form.elements['hora_fim'].setCustomValidity(fim && ini && fim <= ini ? 'O término precisa ser depois do início.' : '');
    };
    form.elements['hora_inicio'].addEventListener('input', conferir);
    form.elements['hora_fim'].addEventListener('input', conferir);
    form.querySelector('.btn-modal-salvar').addEventListener('click', conferir);
})();
</script>
</body>
</html>
