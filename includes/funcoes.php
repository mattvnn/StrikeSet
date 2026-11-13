<?php

function e($texto): string
{
    return htmlspecialchars((string) ($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

const MESES_PT = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
    5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
    9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
];

const MESES_PT_CURTO = [
    1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr', 5 => 'mai', 6 => 'jun',
    7 => 'jul', 8 => 'ago', 9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez'
];

function partes_data(?string $data): ?array
{
    if (!$data || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m)) {
        return null;
    }

    return [(int) $m[1], (int) $m[2], (int) $m[3]];
}

function data_extenso(?string $data): string
{
    $p = partes_data($data);

    if (!$p) {
        return '';
    }

    [$ano, $mes, $dia] = $p;

    return $dia . ' de ' . MESES_PT[$mes] . ' de ' . $ano;
}

function data_curta(?string $data): string
{
    $p = partes_data($data);

    if (!$p) {
        return '';
    }

    [$ano, $mes, $dia] = $p;

    return $dia . ' ' . MESES_PT_CURTO[$mes] . ' ' . $ano;
}

function periodo_extenso(?string $inicio, ?string $fim = null): string
{
    $i = partes_data($inicio);
    $f = partes_data($fim);

    if (!$i) {
        return '';
    }

    if (!$f || $f === $i) {
        return data_extenso($inicio);
    }

    [$ai, $mi, $di] = $i;
    [$af, $mf, $df] = $f;

    if ($ai !== $af) {
        return data_extenso($inicio) . ' a ' . data_extenso($fim);
    }

    if ($mi !== $mf) {
        return $di . ' de ' . MESES_PT[$mi] . ' a ' . $df . ' de ' . MESES_PT[$mf] . ' de ' . $af;
    }

    $ligacao = ($df - $di === 1) ? ' e ' : ' a ';

    return $di . $ligacao . $df . ' de ' . MESES_PT[$mf] . ' de ' . $af;
}

function ano_da_data(?string $data): string
{
    $p = partes_data($data);

    return $p ? (string) $p[0] : '';
}

function hora_formatada(?string $hora): string
{
    if (!$hora || !preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
        return '';
    }

    return sprintf('%02d:%s', (int) $m[1], $m[2]);
}

function horario_treino(?string $inicio, ?string $fim = null): string
{
    $texto = hora_formatada($inicio);

    if ($fim) {
        $texto .= ' - ' . hora_formatada($fim);
    }

    return $texto;
}

function hora_input(?string $hora): string
{
    return $hora ? substr($hora, 0, 5) : '';
}

function validar_data(?string $data): ?string
{
    $data = trim((string) $data);

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m)) {
        return null;
    }

    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $data : null;
}

function validar_hora(?string $hora): ?string
{
    $hora = trim((string) $hora);

    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $hora, $m)) {
        return null;
    }

    return $m[1] . ':' . $m[2] . ':00';
}

function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function pegar_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $f;
}
