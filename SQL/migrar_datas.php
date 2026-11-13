<?php

/*
|--------------------------------------------------------------------------
| MIGRAÇÃO: troca as colunas de data em texto (VARCHAR) por DATE / TIME
|--------------------------------------------------------------------------
|
| Use quando o banco JÁ EXISTE e tem dados. Banco novo → criacao_tabelas.sql.
|
| Como rodar (escolha um):
|   1) Terminal:  docker compose exec web php SQL/migrar_datas.php
|   2) Navegador: logado como admin, abra  http://localhost:8080/SQL/migrar_datas.php
|
| O que faz:
|   - treinos:     horario (VARCHAR)  →  hora_inicio TIME + hora_fim TIME
|   - campeonatos: data_exibicao (VARCHAR) + ano  →  data_inicio DATE + data_fim DATE
|   - cria a tabela recuperacao_senha (Esqueci minha senha)
|
| É seguro rodar mais de uma vez. Se algum texto não puder ser convertido,
| a coluna antiga NÃO é apagada: o script lista o que falta e você corrige
| pelo painel admin (ou direto no banco) e roda de novo.
*/

$cli = PHP_SAPI === 'cli';

if (!$cli) {
    session_start();
    if (($_SESSION['tipo'] ?? '') !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../conexao.php';

function linha(string $t): void { echo $t . PHP_EOL; }

function colunas(mysqli $conn, string $tabela): array
{
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME, DATA_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->bind_param('s', $tabela);
    $stmt->execute();

    $cols = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $c) {
        $cols[strtolower($c['COLUMN_NAME'])] = strtolower($c['DATA_TYPE']);
    }

    return $cols;
}

function existe_tabela(mysqli $conn, string $tabela): bool
{
    $stmt = $conn->prepare("
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->bind_param('s', $tabela);
    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_row();
}

/* "19h00 - 21h00", "19:30 às 21:30", "18h-20h", "9h" → ['19:00:00', '21:00:00'] */
function ler_horario(?string $texto): array
{
    preg_match_all('/(\d{1,2})\s*(?:[:hH]\s*(\d{2})?)/u', (string) $texto, $m, PREG_SET_ORDER);

    $horas = [];
    foreach ($m as $h) {
        $hh = (int) $h[1];
        $mm = isset($h[2]) && $h[2] !== '' ? (int) $h[2] : 0;
        if ($hh <= 23 && $mm <= 59) {
            $horas[] = sprintf('%02d:%02d:00', $hh, $mm);
        }
    }

    return [$horas[0] ?? null, $horas[1] ?? null];
}

/*
| Converte textos de data em [inicio, fim, aproximada]:
|   "2026-06-14" | "14/06/2026" | "14 e 15 de junho de 2026" | "28 de junho a 2 de julho de 2026"
|   "5 de julho de 2026" | "Setembro de 2026" (vira dia 1, marcada como aproximada)
*/
function ler_data(?string $texto, ?int $anoReserva): array
{
    $t = trim((string) $texto);
    $t = function_exists('mb_strtolower')
        ? mb_strtolower($t, 'UTF-8')
        : strtr(strtolower($t), ['Ç' => 'ç', 'Ã' => 'ã', 'É' => 'é', 'Ê' => 'ê']);

    $meses = ['janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'marco' => 3, 'abril' => 4,
        'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8, 'setembro' => 9,
        'outubro' => 10, 'novembro' => 11, 'dezembro' => 12];
    $nomes = implode('|', array_keys($meses));

    $d = fn($a, $m, $dia) => checkdate($m, $dia, $a) ? sprintf('%04d-%02d-%02d', $a, $m, $dia) : null;

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $t, $x)) {
        return [$d((int) $x[1], (int) $x[2], (int) $x[3]), null, false];
    }

    if (preg_match_all('#(\d{1,2})/(\d{1,2})/(\d{4})#', $t, $x, PREG_SET_ORDER)) {
        $ini = $d((int) $x[0][3], (int) $x[0][2], (int) $x[0][1]);
        $fim = isset($x[1]) ? $d((int) $x[1][3], (int) $x[1][2], (int) $x[1][1]) : null;
        return [$ini, $fim, false];
    }

    /* 28 de junho a 2 de julho de 2026 */
    if (preg_match("/(\d{1,2})\s+de\s+($nomes)\s+(?:a|até)\s+(\d{1,2})\s+de\s+($nomes)\s+de\s+(\d{4})/u", $t, $x)) {
        return [$d((int) $x[5], $meses[$x[2]], (int) $x[1]), $d((int) $x[5], $meses[$x[4]], (int) $x[3]), false];
    }

    /* 14 e 15 de junho de 2026 | 14 a 18 de junho de 2026 */
    if (preg_match("/(\d{1,2})\s*(?:e|a|até|-)\s*(\d{1,2})\s+de\s+($nomes)\s+(?:de\s+)?(\d{4})/u", $t, $x)) {
        return [$d((int) $x[4], $meses[$x[3]], (int) $x[1]), $d((int) $x[4], $meses[$x[3]], (int) $x[2]), false];
    }

    /* 5 de julho de 2026 */
    if (preg_match("/(\d{1,2})\s+de\s+($nomes)\s+(?:de\s+)?(\d{4})/u", $t, $x)) {
        return [$d((int) $x[3], $meses[$x[2]], (int) $x[1]), null, false];
    }

    /* Setembro de 2026 */
    if (preg_match("/($nomes)\s+(?:de\s+)?(\d{4})/u", $t, $x)) {
        return [$d((int) $x[2], $meses[$x[1]], 1), null, true];
    }

    /* Só o ano (coluna "ano" antiga) */
    if ($t === '' && $anoReserva) {
        return [sprintf('%04d-01-01', $anoReserva), null, true];
    }

    return [null, null, false];
}

$pendencias = 0;

linha('=== Migração de datas - StrikeSet ===');
linha('');

/* ---------------------------------------------------------
 | 1) Tabela de recuperação de senha
 --------------------------------------------------------- */

if (!existe_tabela($conn, 'recuperacao_senha')) {
    $conn->query("
        CREATE TABLE recuperacao_senha (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expira_em DATETIME NOT NULL,
            usado_em DATETIME DEFAULT NULL,
            criado_em DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_recuperacao_token (token_hash),
            KEY idx_recuperacao_usuario (usuario_id),
            CONSTRAINT fk_recuperacao_usuario FOREIGN KEY (usuario_id)
                REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    linha('[ok] Tabela recuperacao_senha criada.');
} else {
    linha('[--] Tabela recuperacao_senha já existe.');
}

/* ---------------------------------------------------------
 | 2) Treinos: horario → hora_inicio / hora_fim
 --------------------------------------------------------- */

linha('');
linha('--- Treinos ---');

$cols = colunas($conn, 'treinos');

if (!isset($cols['hora_inicio'])) {
    $conn->query("ALTER TABLE treinos ADD hora_inicio TIME NULL AFTER titulo");
    linha('[ok] Coluna hora_inicio criada.');
}
if (!isset($cols['hora_fim'])) {
    $conn->query("ALTER TABLE treinos ADD hora_fim TIME NULL AFTER hora_inicio");
    linha('[ok] Coluna hora_fim criada.');
}

if (isset($cols['horario'])) {

    $res = $conn->query("SELECT id, titulo, horario FROM treinos WHERE hora_inicio IS NULL");
    $upd = $conn->prepare("UPDATE treinos SET hora_inicio = ?, hora_fim = ? WHERE id = ?");

    foreach ($res->fetch_all(MYSQLI_ASSOC) as $t) {
        [$ini, $fim] = ler_horario($t['horario']);

        if ($ini) {
            $upd->bind_param('ssi', $ini, $fim, $t['id']);
            $upd->execute();
            linha("[ok] #{$t['id']} {$t['titulo']}: \"{$t['horario']}\" → $ini" . ($fim ? " até $fim" : ''));
        } else {
            $pendencias++;
            linha("[!!] #{$t['id']} {$t['titulo']}: não entendi o horário \"{$t['horario']}\". Preencha hora_inicio manualmente.");
        }
    }
}

$semHora = (int) $conn->query("SELECT COUNT(*) FROM treinos WHERE hora_inicio IS NULL")->fetch_row()[0];

if ($semHora === 0) {
    $conn->query("ALTER TABLE treinos MODIFY hora_inicio TIME NOT NULL");

    if (isset(colunas($conn, 'treinos')['horario'])) {
        $conn->query("ALTER TABLE treinos DROP COLUMN horario");
        linha('[ok] Coluna antiga "horario" (VARCHAR) removida.');
    }
} else {
    linha("[!!] $semHora treino(s) sem hora_inicio. A coluna antiga \"horario\" foi mantida.");
}

/* ---------------------------------------------------------
 | 3) Campeonatos: data_exibicao + ano → data_inicio / data_fim
 --------------------------------------------------------- */

linha('');
linha('--- Campeonatos ---');

$cols = colunas($conn, 'campeonatos');

/* Se data_inicio/data_fim existirem como texto, guarda o texto e recria como DATE */
foreach (['data_inicio', 'data_fim'] as $c) {
    if (isset($cols[$c]) && $cols[$c] !== 'date') {
        $conn->query("ALTER TABLE campeonatos CHANGE $c {$c}_texto VARCHAR(100) NULL");
        linha("[ok] $c era texto ({$cols[$c]}); renomeada para {$c}_texto para converter.");
        unset($cols[$c]);
    }
}

if (!isset($cols['data_inicio'])) {
    $conn->query("ALTER TABLE campeonatos ADD data_inicio DATE NULL AFTER local");
    linha('[ok] Coluna data_inicio (DATE) criada.');
}
if (!isset($cols['data_fim'])) {
    $conn->query("ALTER TABLE campeonatos ADD data_fim DATE NULL AFTER data_inicio");
    linha('[ok] Coluna data_fim (DATE) criada.');
}

$cols = colunas($conn, 'campeonatos');
$fontes = array_values(array_filter(
    ['data_inicio_texto', 'data_exibicao', 'data_fim_texto', 'ano'],
    fn($c) => isset($cols[$c])
));

if ($fontes) {

    $res = $conn->query("SELECT id, nome, " . implode(', ', $fontes) . " FROM campeonatos WHERE data_inicio IS NULL");
    $upd = $conn->prepare("UPDATE campeonatos SET data_inicio = ?, data_fim = COALESCE(data_fim, ?) WHERE id = ?");

    foreach ($res->fetch_all(MYSQLI_ASSOC) as $c) {
        $texto = trim(($c['data_inicio_texto'] ?? '') ?: ($c['data_exibicao'] ?? ''));
        $ano = isset($c['ano']) && $c['ano'] ? (int) $c['ano'] : null;

        [$ini, $fim, $aprox] = ler_data($texto, $ano);

        if (!$fim && !empty($c['data_fim_texto'])) {
            [$fim] = ler_data($c['data_fim_texto'], null);
        }

        if ($ini) {
            $upd->bind_param('ssi', $ini, $fim, $c['id']);
            $upd->execute();
            $origem = $texto !== '' ? "\"$texto\"" : "ano $ano";
            linha("[ok] #{$c['id']} {$c['nome']}: $origem → $ini" . ($fim ? " até $fim" : '')
                . ($aprox ? '  (data aproximada, confira no painel)' : ''));
        } elseif ($texto !== '') {
            $pendencias++;
            linha("[!!] #{$c['id']} {$c['nome']}: não entendi a data \"$texto\". Ajuste no painel admin.");
        } else {
            linha("[--] #{$c['id']} {$c['nome']}: sem data (pode ficar assim).");
        }
    }
}

if ($pendencias === 0) {
    foreach (['data_exibicao', 'ano', 'data_inicio_texto', 'data_fim_texto'] as $c) {
        if (isset(colunas($conn, 'campeonatos')[$c])) {
            if ($c === 'ano') {
                try {
                    $conn->query("ALTER TABLE campeonatos DROP INDEX idx_campeonatos_ano");
                } catch (mysqli_sql_exception $e) {
                    /* índice já não existia */
                }
            }
            $conn->query("ALTER TABLE campeonatos DROP COLUMN $c");
            linha("[ok] Coluna antiga \"$c\" removida.");
        }
    }

    $idx = $conn->query("SHOW INDEX FROM campeonatos WHERE Key_name = 'idx_campeonatos_data'")->num_rows;
    if (!$idx) {
        $conn->query("ALTER TABLE campeonatos ADD KEY idx_campeonatos_data (data_inicio)");
    }
}

linha('');

if ($pendencias === 0) {
    linha('Migração concluída! Nenhuma coluna de data em texto sobrou.');
} else {
    linha("Migração parcial: $pendencias item(ns) precisam de ajuste manual.");
    linha('As colunas antigas foram mantidas. Corrija e rode este script de novo.');
}
