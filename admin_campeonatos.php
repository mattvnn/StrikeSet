<?php

require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$tipos = [
    'proximo' => 'Próximo campeonato',
    'disputado' => 'Campeonato disputado',
    'conquista' => 'Conquista'
];

$statusLabels = [
    'aberto' => 'Inscrições abertas',
    'confirmado' => 'Confirmado',
    'analise' => 'Em análise',
    'finalizado' => 'Finalizado'
];

$colocacoes = ['1º lugar', '2º lugar', '3º lugar', '4º lugar', 'Participação'];


/*
|--------------------------------------------------------------------------
| AÇÕES (POST → redirect → GET)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($acao === 'excluir' && $id > 0) {

        $stmt = $conn->prepare("DELETE FROM campeonatos WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        flash('sucesso', 'Campeonato excluído.');
    }

    if ($acao === 'salvar' || $acao === 'editar') {

        $vazioParaNull = fn($v) => ($v = trim((string) $v)) === '' ? null : $v;

        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? '';
        $status = $vazioParaNull($_POST['status'] ?? '');
        $local = $vazioParaNull($_POST['local'] ?? '');
        $colocacao = $vazioParaNull($_POST['colocacao'] ?? '');
        $icone = $vazioParaNull($_POST['icone'] ?? '');
        $descricao = $vazioParaNull($_POST['descricao'] ?? '');

        $textoInicio = trim($_POST['data_inicio'] ?? '');
        $textoFim = trim($_POST['data_fim'] ?? '');
        $dataInicio = validar_data($textoInicio);
        $dataFim = validar_data($textoFim);

        if ($status !== null && !isset($statusLabels[$status])) {
            $status = null;
        }

        if ($colocacao !== null && !in_array($colocacao, $colocacoes, true)) {
            $colocacao = null;
        }

        if ($nome === '' || !isset($tipos[$tipo])) {

            flash('erro', 'Preencha o nome e selecione um tipo válido.');

        } elseif (($textoInicio !== '' && !$dataInicio) || ($textoFim !== '' && !$dataFim)) {

            flash('erro', 'Data inválida.');

        } elseif ($tipo === 'proximo' && !$dataInicio) {

            flash('erro', 'Informe a data de início do próximo campeonato.');

        } elseif ($dataFim && !$dataInicio) {

            flash('erro', 'Informe a data de início antes da data final.');

        } elseif ($dataFim && $dataFim < $dataInicio) {

            flash('erro', 'A data final não pode ser antes da data de início.');

        } elseif ($acao === 'salvar') {

            $stmt = $conn->prepare("
                INSERT INTO campeonatos
                    (nome, tipo, status, local, data_inicio, data_fim, colocacao, icone, descricao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssssss', $nome, $tipo, $status, $local, $dataInicio, $dataFim, $colocacao, $icone, $descricao);
            $stmt->execute();

            flash('sucesso', 'Campeonato criado com sucesso!');

        } elseif ($id > 0) {

            $stmt = $conn->prepare("
                UPDATE campeonatos
                SET nome = ?, tipo = ?, status = ?, local = ?, data_inicio = ?,
                    data_fim = ?, colocacao = ?, icone = ?, descricao = ?
                WHERE id = ?
            ");
            $stmt->bind_param('sssssssssi', $nome, $tipo, $status, $local, $dataInicio, $dataFim, $colocacao, $icone, $descricao, $id);
            $stmt->execute();

            flash('sucesso', 'Campeonato atualizado com sucesso!');
        }
    }

    header('Location: admin_campeonatos.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| LEITURA
|--------------------------------------------------------------------------
*/

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

$proximos = array_values(array_filter($todos, fn($c) => $c['tipo'] === 'proximo'));
$disputados = array_values(array_filter($todos, fn($c) => $c['tipo'] === 'disputado'));
$conquistas = array_values(array_filter($todos, fn($c) => $c['tipo'] === 'conquista'));

$hoje = date('Y-m-d');
$atrasados = count(array_filter($proximos, fn($c) => ($c['data_fim'] ?: $c['data_inicio']) < $hoje));

/* Classe de cor da colocação */
function classe_colocacao(?string $coloc): string
{
    return match ($coloc) {
        '1º lugar' => 'coloc-ouro',
        '2º lugar' => 'coloc-prata',
        '3º lugar' => 'coloc-bronze',
        default => 'coloc-neutro',
    };
}

/* Dados de um campeonato para o modal de edição (vai no data-camp do botão) */
function dados_modal(array $c): string
{
    return e(json_encode([
        'id' => (int) $c['id'],
        'nome' => $c['nome'],
        'tipo' => $c['tipo'],
        'status' => $c['status'] ?? '',
        'local' => $c['local'] ?? '',
        'data_inicio' => $c['data_inicio'] ?? '',
        'data_fim' => $c['data_fim'] ?? '',
        'colocacao' => $c['colocacao'] ?? '',
        'icone' => $c['icone'] ?? '',
        'descricao' => $c['descricao'] ?? '',
    ]));
}

/* Atributos usados pelos filtros (busca / tipo / status) */
function atributos_filtro(array $c): string
{
    $busca = mb_strtolower_seguro($c['nome'] . ' ' . ($c['local'] ?? '') . ' ' . ($c['descricao'] ?? ''));

    return 'data-filtro-item data-busca="' . e($busca) . '" data-status="' . e($c['status'] ?? '') . '"';
}

function mb_strtolower_seguro(string $t): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
}

/* Botões Editar / Excluir */
function botoes_acao(array $c): string
{
    $nome = e($c['nome']);
    $id = (int) $c['id'];
    $dados = dados_modal($c);

    return <<<HTML
        <button type="button" class="acao-btn acao-editar"
            data-bs-toggle="modal" data-bs-target="#modalCampeonato" data-camp='{$dados}'>
            Editar
        </button>
        <form method="POST" onsubmit="return confirm('Excluir &quot;{$nome}&quot;?');">
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="id" value="{$id}">
            <button type="submit" class="acao-btn acao-excluir">Excluir</button>
        </form>
    HTML;
}

$mensagem = pegar_flash();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Campeonatos | StrikeSet Gaspar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="visual/css/global.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="visual/css/admin.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="visual/css/admin_campeonatos.css?v=<?php echo time(); ?>">
</head>

<body class="pagina-admin">

<?php require_once __DIR__ . '/includes/header_admin.php'; ?>

<section class="admin-hero">
    <div class="admin-hero-inner">

        <div class="admin-hero-texto">
            <span class="admin-hero-tag">Administração</span>
            <h1>Gerenciar Campeonatos</h1>
            <p>Adicione, edite e organize os campeonatos e resultados da equipe.</p>
        </div>

        <button type="button" class="btn-novo" data-bs-toggle="modal" data-bs-target="#modalCampeonato">
            + Novo Campeonato
        </button>

    </div>
</section>

<section class="admin-stats">
    <div class="admin-inner">
        <div class="stats-grid">
            <div class="stat-card">
                <strong><?= count($todos) ?></strong>
                <span>Cadastrados no total</span>
            </div>
            <div class="stat-card stat-card-destaque">
                <strong><?= count($proximos) ?></strong>
                <span>Próximos campeonatos</span>
            </div>
            <div class="stat-card">
                <strong><?= count($disputados) ?></strong>
                <span>Campeonatos disputados</span>
            </div>
            <div class="stat-card">
                <strong><?= count($conquistas) ?></strong>
                <span>Conquistas</span>
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

        <?php if ($atrasados > 0): ?>
            <div class="admin-alerta admin-alerta--aviso" role="alert">
                <?= $atrasados ?> “próximo campeonato” já passou da data.
                Edite e mude o tipo para “Campeonato disputado” para ele aparecer no histórico.
            </div>
        <?php endif; ?>

        <div class="admin-filtros">
            <input id="buscarCampeonato" class="filtro-busca" type="search" placeholder="Buscar campeonato...">

            <div class="filtro-selects">
                <select id="filtroTipo" class="filtro-select">
                    <option value="">Todos os tipos</option>
                    <option value="proximo">Próximos</option>
                    <option value="disputado">Disputados</option>
                    <option value="conquista">Conquistas</option>
                </select>

                <select id="filtroStatus" class="filtro-select">
                    <option value="">Todos os status</option>
                    <?php foreach ($statusLabels as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ================= PRÓXIMOS ================= -->

        <div class="admin-secao" data-secao="proximo">
            <div class="admin-secao-topo">
                <div class="admin-secao-titulo">
                    <span class="barra"></span>
                    <h2>Próximos campeonatos</h2>
                </div>
                <span class="admin-secao-contagem"><?= count($proximos) ?> cadastrados</span>
            </div>

            <div class="admin-grid">
                <?php if (empty($proximos)): ?>
                    <p class="admin-vazio">Nenhum campeonato futuro cadastrado.</p>
                <?php endif; ?>

                <?php foreach ($proximos as $c): ?>
                    <?php $passou = ($c['data_fim'] ?: $c['data_inicio']) < $hoje; ?>
                    <div class="camp-admin-card<?= $passou ? ' camp-admin-card--passou' : '' ?>" <?= atributos_filtro($c) ?>>

                        <div class="camp-card-topo">
                            <?php if ($c['status']): ?>
                                <span class="camp-status status-<?= e($c['status']) ?>">
                                    <?= e($statusLabels[$c['status']] ?? $c['status']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($passou): ?>
                                <span class="camp-status status-passou">Data já passou</span>
                            <?php endif; ?>
                        </div>

                        <h3><?= e($c['nome']) ?></h3>

                        <div class="camp-card-info">
                            <div class="camp-info-item">
                                <span class="info-label">Local</span>
                                <span class="info-val"><?= e($c['local'] ?: '—') ?></span>
                            </div>
                            <div class="camp-info-item">
                                <span class="info-label">Data</span>
                                <span class="info-val"><?= e(periodo_extenso($c['data_inicio'], $c['data_fim']) ?: '—') ?></span>
                            </div>
                        </div>

                        <div class="admin-card-acoes"><?= botoes_acao($c) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ================= DISPUTADOS ================= -->

        <div class="admin-secao" data-secao="disputado">
            <div class="admin-secao-topo">
                <div class="admin-secao-titulo">
                    <span class="barra"></span>
                    <h2>Campeonatos disputados</h2>
                </div>
                <span class="admin-secao-contagem"><?= count($disputados) ?> cadastrados</span>
            </div>

            <div class="disputados-lista">
                <?php if (empty($disputados)): ?>
                    <p class="admin-vazio">Nenhum campeonato disputado cadastrado.</p>
                <?php endif; ?>

                <?php foreach ($disputados as $c): ?>
                    <div class="disp-admin-card" <?= atributos_filtro($c) ?>>
                        <div class="disp-ano"><?= e(ano_da_data($c['data_inicio']) ?: '—') ?></div>

                        <div class="disp-corpo">
                            <div class="disp-info">
                                <h3><?= e($c['nome']) ?></h3>

                                <?php if ($c['colocacao']): ?>
                                    <span class="disp-coloc <?= classe_colocacao($c['colocacao']) ?>"><?= e($c['colocacao']) ?></span>
                                <?php endif; ?>

                                <?php if ($c['data_inicio']): ?>
                                    <span class="disp-data"><?= e(periodo_extenso($c['data_inicio'], $c['data_fim'])) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="disp-acoes"><?= botoes_acao($c) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ================= CONQUISTAS ================= -->

        <div class="admin-secao" data-secao="conquista">
            <div class="admin-secao-topo">
                <div class="admin-secao-titulo">
                    <span class="barra"></span>
                    <h2>Conquistas</h2>
                </div>
                <span class="admin-secao-contagem"><?= count($conquistas) ?> cadastradas</span>
            </div>

            <div class="conquistas-grid">
                <?php if (empty($conquistas)): ?>
                    <p class="admin-vazio">Nenhuma conquista cadastrada.</p>
                <?php endif; ?>

                <?php foreach ($conquistas as $c): ?>
                    <div class="conq-admin-card" <?= atributos_filtro($c) ?>>
                        <div class="conq-icone"><?= e($c['icone'] ?: '🏆') ?></div>
                        <h4><?= e($c['nome']) ?></h4>
                        <p><?= e($c['descricao'] ?: ano_da_data($c['data_inicio'])) ?></p>

                        <div class="admin-card-acoes"><?= botoes_acao($c) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="admin-vazio filtro-sem-resultado" hidden>Nenhum campeonato encontrado com esses filtros.</p>

    </div>
</section>

<!-- ================= MODAL CRIAR / EDITAR ================= -->

<div class="modal fade" id="modalCampeonato" tabindex="-1" aria-labelledby="modalCampTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="admin_campeonatos.php" class="modal-content admin-modal-content" id="formCampeonato">

            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" value="">

            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="modalCampTitulo">Novo Campeonato</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body admin-modal-body">
                <div class="admin-form">

                    <div class="form-grupo form-grupo-full">
                        <label for="c_nome">Nome</label>
                        <input type="text" id="c_nome" name="nome" maxlength="200" placeholder="Ex: Copa Regional de Vôlei" required>
                    </div>

                    <div class="form-grupo">
                        <label for="c_tipo">Tipo</label>
                        <select id="c_tipo" name="tipo" required>
                            <option value="">Selecione o tipo</option>
                            <?php foreach ($tipos as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grupo" data-para="proximo disputado">
                        <label for="c_status">Status</label>
                        <select id="c_status" name="status">
                            <option value="">Sem status</option>
                            <?php foreach ($statusLabels as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grupo">
                        <label for="c_data_inicio">Data de início <span class="opcional" data-obrigatorio-texto>(opcional)</span></label>
                        <input type="date" id="c_data_inicio" name="data_inicio">
                    </div>

                    <div class="form-grupo">
                        <label for="c_data_fim">Data final <span class="opcional">(se durar mais de um dia)</span></label>
                        <input type="date" id="c_data_fim" name="data_fim">
                    </div>

                    <div class="form-grupo" data-para="proximo disputado">
                        <label for="c_local">Local</label>
                        <input type="text" id="c_local" name="local" maxlength="200" placeholder="Ex: Ginásio IFSC Gaspar">
                    </div>

                    <div class="form-grupo" data-para="disputado">
                        <label for="c_colocacao">Colocação</label>
                        <select id="c_colocacao" name="colocacao">
                            <option value="">Não informado</option>
                            <?php foreach ($colocacoes as $col): ?>
                                <option value="<?= e($col) ?>"><?= e($col) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grupo" data-para="conquista">
                        <label for="c_icone">Ícone <span class="opcional">(um emoji)</span></label>
                        <input type="text" id="c_icone" name="icone" maxlength="20" placeholder="Ex: 🥇">
                    </div>

                    <div class="form-grupo form-grupo-full">
                        <label for="c_descricao">Descrição</label>
                        <textarea id="c_descricao" name="descricao" rows="3" placeholder="Ex: Copa IFSC 2026"></textarea>
                    </div>

                </div>
            </div>

            <div class="modal-footer admin-modal-footer">
                <button type="button" class="btn-modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-modal-salvar">Salvar campeonato</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const form = document.getElementById('formCampeonato');
    const campo = (nome) => form.elements[nome];

    /* Mostra só os campos que fazem sentido para o tipo escolhido */
    function ajustarCampos() {
        const tipo = campo('tipo').value;

        form.querySelectorAll('[data-para]').forEach(function (grupo) {
            const visivel = !tipo || grupo.dataset.para.split(' ').includes(tipo);
            grupo.hidden = !visivel;
        });

        const obrigatoria = tipo === 'proximo';
        campo('data_inicio').required = obrigatoria;
        form.querySelector('[data-obrigatorio-texto]').textContent = obrigatoria ? '' : '(opcional)';
    }

    campo('tipo').addEventListener('change', ajustarCampos);

    /* Mesmo modal para "Novo" e "Editar" */
    document.getElementById('modalCampeonato').addEventListener('show.bs.modal', function (evento) {
        const botao = evento.relatedTarget;
        const dados = botao && botao.dataset.camp ? JSON.parse(botao.dataset.camp) : null;

        form.reset();
        campo('acao').value = dados ? 'editar' : 'salvar';
        campo('id').value = dados ? dados.id : '';
        document.getElementById('modalCampTitulo').textContent = dados ? 'Editar Campeonato' : 'Novo Campeonato';
        form.querySelector('.btn-modal-salvar').textContent = dados ? 'Salvar alterações' : 'Salvar campeonato';

        if (dados) {
            ['nome', 'tipo', 'status', 'local', 'data_inicio', 'data_fim', 'colocacao', 'icone', 'descricao']
                .forEach((n) => campo(n).value = dados[n] || '');
        }

        ajustarCampos();
    });

    /* Data final não pode ser antes do início (confere a cada alteração) */
    function conferirDatas() {
        const ini = campo('data_inicio').value, fim = campo('data_fim').value;
        campo('data_fim').setCustomValidity(fim && ini && fim < ini ? 'A data final não pode ser antes do início.' : '');
    }

    campo('data_inicio').addEventListener('input', conferirDatas);
    campo('data_fim').addEventListener('input', conferirDatas);
    form.querySelector('.btn-modal-salvar').addEventListener('click', conferirDatas);

    /* Campos escondidos não devem ser enviados com valores de outro tipo */
    form.addEventListener('submit', function () {
        form.querySelectorAll('[data-para][hidden] input, [data-para][hidden] select').forEach((c) => c.value = '');
    });

    /* Filtros: busca, tipo e status */
    const busca = document.getElementById('buscarCampeonato');
    const filtroTipo = document.getElementById('filtroTipo');
    const filtroStatus = document.getElementById('filtroStatus');

    function filtrar() {
        const termo = busca.value.trim().toLowerCase();
        const tipo = filtroTipo.value;
        const status = filtroStatus.value;
        let algum = false;

        document.querySelectorAll('[data-secao]').forEach(function (secao) {
            const secaoVisivel = !tipo || secao.dataset.secao === tipo;
            let visiveisNaSecao = 0;

            secao.querySelectorAll('[data-filtro-item]').forEach(function (item) {
                const ok = secaoVisivel
                    && (!termo || item.dataset.busca.includes(termo))
                    && (!status || item.dataset.status === status);
                item.hidden = !ok;
                if (ok) visiveisNaSecao++;
            });

            const filtrando = termo || status;
            secao.hidden = !secaoVisivel || (filtrando && visiveisNaSecao === 0);
            if (!secao.hidden) algum = true;
        });

        document.querySelector('.filtro-sem-resultado').hidden = algum;
    }

    [busca, filtroTipo, filtroStatus].forEach((el) => el.addEventListener('input', filtrar));
})();
</script>
</body>
</html>
