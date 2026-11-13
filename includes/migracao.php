<?php

const MIGRACAO_MARCA = __DIR__ . '/../SQL/.banco_atualizado_v2';

const COLUNAS_RECUPERACAO = ['id', 'usuario_id', 'token_hash', 'expira_em', 'usado_em', 'criado_em'];

function banco_precisa_migrar(mysqli $conn): bool
{
    $r = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND ((TABLE_NAME = 'treinos' AND COLUMN_NAME = 'horario')
                  OR (TABLE_NAME = 'campeonatos' AND COLUMN_NAME IN ('data_exibicao', 'ano', 'data_inicio_texto', 'data_fim_texto'))
                  OR (TABLE_NAME = 'campeonatos' AND COLUMN_NAME IN ('data_inicio', 'data_fim') AND DATA_TYPE <> 'date')))
          +
            (SELECT COUNT(*) < 6 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacao_senha'
                AND COLUMN_NAME IN ('id', 'usuario_id', 'token_hash', 'expira_em', 'usado_em', 'criado_em'))
          +
            (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'treinos' AND COLUMN_NAME = 'hora_inicio')
    ")->fetch_row();

    return (int) $r[0] > 0;
}

function migracao_automatica(mysqli $conn): void
{
    $marca = @file_get_contents(MIGRACAO_MARCA);

    if ($marca === 'ok' || (is_string($marca) && str_starts_with($marca, 'pendente'))) {
        return;
    }

    try {
        if (!banco_precisa_migrar($conn)) {
            @file_put_contents(MIGRACAO_MARCA, 'ok');
            return;
        }

        $trava = $conn->query("SELECT GET_LOCK('strikeset_migracao', 20)")->fetch_row()[0];

        if ((int) $trava !== 1) {
            return;
        }

        $relatorio = [];
        $pendencias = banco_precisa_migrar($conn)
            ? migrar_banco($conn, function ($t) use (&$relatorio) { $relatorio[] = $t; })
            : 0;

        $conn->query("SELECT RELEASE_LOCK('strikeset_migracao')");

        @file_put_contents(__DIR__ . '/../SQL/migracao.log',
            date('Y-m-d H:i:s') . PHP_EOL . implode(PHP_EOL, $relatorio) . PHP_EOL . PHP_EOL, FILE_APPEND);

        @file_put_contents(MIGRACAO_MARCA, $pendencias === 0 ? 'ok' : 'pendente:' . $pendencias);

    } catch (Throwable $e) {

        error_log('[migracao] ' . $e->getMessage());
        try { $conn->query("SELECT RELEASE_LOCK('strikeset_migracao')"); } catch (Throwable $e2) {}
    }
}

function migracao_reverificar(): void
{
    $marca = @file_get_contents(MIGRACAO_MARCA);

    if (is_string($marca) && str_starts_with($marca, 'pendente')) {
        @unlink(MIGRACAO_MARCA);
    }
}

function migracao_pendencias(mysqli $conn): array
{
    $marca = @file_get_contents(MIGRACAO_MARCA);

    if (!is_string($marca) || !str_starts_with($marca, 'pendente')) {
        return [];
    }

    $itens = [];

    try {
        $cols = mig_colunas($conn, 'treinos');
        if (isset($cols['horario'])) {
            foreach ($conn->query("SELECT titulo, horario FROM treinos WHERE hora_inicio IS NULL") as $t) {
                $itens[] = 'Treino “' . $t['titulo'] . '”: horário “' . $t['horario'] . '”';
            }
        }

        $cols = mig_colunas($conn, 'campeonatos');
        if (isset($cols['data_exibicao'])) {
            foreach ($conn->query("SELECT nome, data_exibicao FROM campeonatos
                                   WHERE data_inicio IS NULL AND data_exibicao IS NOT NULL AND data_exibicao <> ''") as $c) {
                $itens[] = 'Campeonato “' . $c['nome'] . '”: data “' . $c['data_exibicao'] . '”';
            }
        }
    } catch (Throwable $e) {
        error_log('[migracao] ' . $e->getMessage());
    }

    if (!$itens) {

        migracao_reverificar();
    }

    return $itens;
}

function mig_backup(mysqli $conn, string $tabela, string $coluna): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS backup_datas_antigas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tabela VARCHAR(50) NOT NULL,
            registro_id INT UNSIGNED NOT NULL,
            coluna VARCHAR(50) NOT NULL,
            valor VARCHAR(255),
            copiado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        INSERT INTO backup_datas_antigas (tabela, registro_id, coluna, valor)
        SELECT '$tabela', id, '$coluna', CAST(`$coluna` AS CHAR)
        FROM `$tabela`
        WHERE `$coluna` IS NOT NULL
    ");
}

function mig_colunas(mysqli $conn, string $tabela): array
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

function mig_existe_tabela(mysqli $conn, string $tabela): bool
{
    $stmt = $conn->prepare("
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->bind_param('s', $tabela);
    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_row();
}

function mig_ler_horario(?string $texto): array
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

function mig_ler_data(?string $texto, ?int $anoReserva): array
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

    if (preg_match("/(\d{1,2})\s+de\s+($nomes)\s+(?:a|até)\s+(\d{1,2})\s+de\s+($nomes)\s+de\s+(\d{4})/u", $t, $x)) {
        return [$d((int) $x[5], $meses[$x[2]], (int) $x[1]), $d((int) $x[5], $meses[$x[4]], (int) $x[3]), false];
    }

    if (preg_match("/(\d{1,2})\s*(?:e|a|até|-)\s*(\d{1,2})\s+de\s+($nomes)\s+(?:de\s+)?(\d{4})/u", $t, $x)) {
        return [$d((int) $x[4], $meses[$x[3]], (int) $x[1]), $d((int) $x[4], $meses[$x[3]], (int) $x[2]), false];
    }

    if (preg_match("/(\d{1,2})\s+de\s+($nomes)\s+(?:de\s+)?(\d{4})/u", $t, $x)) {
        return [$d((int) $x[3], $meses[$x[2]], (int) $x[1]), null, false];
    }

    if (preg_match("/($nomes)\s+(?:de\s+)?(\d{4})/u", $t, $x)) {
        return [$d((int) $x[2], $meses[$x[1]], 1), null, true];
    }

    if ($t === '' && $anoReserva) {
        return [sprintf('%04d-01-01', $anoReserva), null, true];
    }

    return [null, null, false];
}

function migrar_banco(mysqli $conn, callable $log): int
{
    $pendencias = 0;

    $log('=== Migração de datas - StrikeSet ===');
    $log('');

    if (mig_existe_tabela($conn, 'recuperacao_senha')) {
        $faltando = array_diff(COLUNAS_RECUPERACAO, array_keys(mig_colunas($conn, 'recuperacao_senha')));

        if ($faltando) {
            $nomeAntigo = 'recuperacao_senha_antiga_' . date('YmdHis');
            $conn->query("RENAME TABLE recuperacao_senha TO `$nomeAntigo`");
            $log("[ok] Tabela recuperacao_senha tinha outro formato (faltava: " . implode(', ', $faltando) . "). Guardada como $nomeAntigo.");
        }
    }

    if (!mig_existe_tabela($conn, 'recuperacao_senha')) {
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
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $log('[ok] Tabela recuperacao_senha criada.');
    } else {
        $log('[--] Tabela recuperacao_senha já existe.');
    }

    $log('');
    $log('--- Treinos ---');

    $cols = mig_colunas($conn, 'treinos');

    if (!isset($cols['hora_inicio'])) {
        $conn->query("ALTER TABLE treinos ADD hora_inicio TIME NULL AFTER titulo");
        $log('[ok] Coluna hora_inicio criada.');
    }
    if (!isset($cols['hora_fim'])) {
        $conn->query("ALTER TABLE treinos ADD hora_fim TIME NULL AFTER hora_inicio");
        $log('[ok] Coluna hora_fim criada.');
    }

    if (isset($cols['horario'])) {

        $res = $conn->query("SELECT id, titulo, horario FROM treinos WHERE hora_inicio IS NULL");
        $upd = $conn->prepare("UPDATE treinos SET hora_inicio = ?, hora_fim = ? WHERE id = ?");

        foreach ($res->fetch_all(MYSQLI_ASSOC) as $t) {
            [$ini, $fim] = mig_ler_horario($t['horario']);

            if ($ini) {
                $upd->bind_param('ssi', $ini, $fim, $t['id']);
                $upd->execute();
                $log("[ok] #{$t['id']} {$t['titulo']}: \"{$t['horario']}\" → $ini" . ($fim ? " até $fim" : ''));
            } else {
                $pendencias++;
                $log("[!!] #{$t['id']} {$t['titulo']}: não entendi o horário \"{$t['horario']}\". Preencha hora_inicio manualmente.");
            }
        }
    }

    $semHora = (int) $conn->query("SELECT COUNT(*) FROM treinos WHERE hora_inicio IS NULL")->fetch_row()[0];

    if ($semHora === 0) {
        $conn->query("ALTER TABLE treinos MODIFY hora_inicio TIME NOT NULL");

        if (isset(mig_colunas($conn, 'treinos')['horario'])) {
            mig_backup($conn, 'treinos', 'horario');
            $conn->query("ALTER TABLE treinos DROP COLUMN horario");
            $log('[ok] Coluna antiga "horario" (VARCHAR) removida.');
        }
    } else {
        $log("[!!] $semHora treino(s) sem hora_inicio. A coluna antiga \"horario\" foi mantida.");
    }

    $log('');
    $log('--- Campeonatos ---');

    $cols = mig_colunas($conn, 'campeonatos');

    foreach (['data_inicio', 'data_fim'] as $c) {
        if (isset($cols[$c]) && $cols[$c] !== 'date') {
            $conn->query("ALTER TABLE campeonatos CHANGE $c {$c}_texto VARCHAR(100) NULL");
            $log("[ok] $c era texto ({$cols[$c]}); renomeada para {$c}_texto para converter.");
            unset($cols[$c]);
        }
    }

    if (!isset($cols['data_inicio'])) {
        $conn->query("ALTER TABLE campeonatos ADD data_inicio DATE NULL AFTER local");
        $log('[ok] Coluna data_inicio (DATE) criada.');
    }
    if (!isset($cols['data_fim'])) {
        $conn->query("ALTER TABLE campeonatos ADD data_fim DATE NULL AFTER data_inicio");
        $log('[ok] Coluna data_fim (DATE) criada.');
    }

    $cols = mig_colunas($conn, 'campeonatos');
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

            [$ini, $fim, $aprox] = mig_ler_data($texto, $ano);

            if (!$fim && !empty($c['data_fim_texto'])) {
                [$fim] = mig_ler_data($c['data_fim_texto'], null);
            }

            if ($ini) {
                $upd->bind_param('ssi', $ini, $fim, $c['id']);
                $upd->execute();
                $origem = $texto !== '' ? "\"$texto\"" : "ano $ano";
                $log("[ok] #{$c['id']} {$c['nome']}: $origem → $ini" . ($fim ? " até $fim" : '')
                    . ($aprox ? '  (data aproximada, confira no painel)' : ''));
            } elseif ($texto !== '') {
                $pendencias++;
                $log("[!!] #{$c['id']} {$c['nome']}: não entendi a data \"$texto\". Ajuste no painel admin.");
            } else {
                $log("[--] #{$c['id']} {$c['nome']}: sem data (pode ficar assim).");
            }
        }
    }

    if ($pendencias === 0) {
        foreach (['data_exibicao', 'ano', 'data_inicio_texto', 'data_fim_texto'] as $c) {
            if (isset(mig_colunas($conn, 'campeonatos')[$c])) {
                if ($c === 'ano') {
                    try {
                        $conn->query("ALTER TABLE campeonatos DROP INDEX idx_campeonatos_ano");
                    } catch (mysqli_sql_exception $e) {

                    }
                }
                mig_backup($conn, 'campeonatos', $c);
                $conn->query("ALTER TABLE campeonatos DROP COLUMN $c");
                $log("[ok] Coluna antiga \"$c\" removida.");
            }
        }

        $idx = $conn->query("SHOW INDEX FROM campeonatos WHERE Key_name = 'idx_campeonatos_data'")->num_rows;
        if (!$idx) {
            $conn->query("ALTER TABLE campeonatos ADD KEY idx_campeonatos_data (data_inicio)");
        }
    }

    $log('');

    if ($pendencias === 0) {
        $log('Migração concluída! Nenhuma coluna de data em texto sobrou.');
    } else {
        $log("Migração parcial: $pendencias item(ns) precisam de ajuste manual.");
        $log('As colunas antigas foram mantidas. Corrija e rode este script de novo.');
    }

    return $pendencias;
}
