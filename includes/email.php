<?php

/*
|--------------------------------------------------------------------------
| Envio de e-mail por SMTP (sem bibliotecas externas)
|--------------------------------------------------------------------------
|
| Configure no .env:
|   SMTP_HOST=smtp.gmail.com
|   SMTP_PORT=465            (465 = SSL direto | 587 = STARTTLS)
|   SMTP_USER=seuemail@gmail.com
|   SMTP_PASS=senha-de-app   (no Gmail use uma "Senha de app", não a senha normal)
|   SMTP_FROM=seuemail@gmail.com
|   SMTP_FROM_NAME=StrikeSet Gaspar
|   SMTP_SECURE=             (opcional: ssl | tls | none — padrão: ssl na 465, tls nas outras.
|                             "none" só para servidor de teste local, ex.: Mailpit)
|
| Se SMTP_HOST estiver vazio, smtp_configurado() devolve false.
*/

function smtp_configurado(): bool
{
    return (string) getenv('SMTP_HOST') !== '' && (string) getenv('SMTP_USER') !== '';
}

/*
| Envia um e-mail em HTML (com versão em texto puro).
| Devolve true se o servidor aceitou a mensagem.
*/
function enviar_email(string $para, string $nomePara, string $assunto, string $html, string $texto): bool
{
    if (!smtp_configurado()) {
        error_log('[email] SMTP não configurado no .env');
        return false;
    }

    $host     = getenv('SMTP_HOST');
    $porta    = (int) (getenv('SMTP_PORT') ?: 465);
    $usuario  = getenv('SMTP_USER');
    $senha    = getenv('SMTP_PASS');
    $de       = getenv('SMTP_FROM') ?: $usuario;
    $nomeDe   = getenv('SMTP_FROM_NAME') ?: 'StrikeSet Gaspar';

    $seguranca = strtolower((string) getenv('SMTP_SECURE')) ?: ($porta === 465 ? 'ssl' : 'tls');

    $endereco = ($seguranca === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $porta;

    $sock = @stream_socket_client($endereco, $errno, $errstr, 15);

    if (!$sock) {
        error_log("[email] Falha ao conectar em $endereco: $errstr");
        return false;
    }

    stream_set_timeout($sock, 15);

    /* Lê a resposta do servidor (pode ter várias linhas) e confere o código */
    $ler = function (array $esperados) use ($sock): bool {
        $resposta = '';

        while (($linha = fgets($sock, 515)) !== false) {
            $resposta .= $linha;

            if (strlen($linha) < 4 || $linha[3] === ' ') {
                break;
            }
        }

        $codigo = (int) substr($resposta, 0, 3);

        if (!in_array($codigo, $esperados, true)) {
            error_log('[email] Resposta inesperada do SMTP: ' . trim($resposta));
            return false;
        }

        return true;
    };

    $enviar = function (string $comando, array $esperados) use ($sock, $ler): bool {
        fwrite($sock, $comando . "\r\n");
        return $ler($esperados);
    };

    $servidorLocal = gethostname() ?: 'localhost';

    $ok = $ler([220]) && $enviar('EHLO ' . $servidorLocal, [250]);

    if ($ok && $seguranca === 'tls') {
        $ok = $enviar('STARTTLS', [220])
            && stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            && $enviar('EHLO ' . $servidorLocal, [250]);
    }

    $ok = $ok
        && $enviar('AUTH LOGIN', [334])
        && $enviar(base64_encode($usuario), [334])
        && $enviar(base64_encode($senha), [235])
        && $enviar('MAIL FROM:<' . $de . '>', [250])
        && $enviar('RCPT TO:<' . $para . '>', [250, 251])
        && $enviar('DATA', [354]);

    if ($ok) {
        $limite = 'b_' . bin2hex(random_bytes(8));
        $codificar = fn($t) => '=?UTF-8?B?' . base64_encode($t) . '?=';

        $cabecalhos = [
            'Date: ' . date('r'),
            'From: ' . $codificar($nomeDe) . ' <' . $de . '>',
            'To: ' . $codificar($nomePara) . ' <' . $para . '>',
            'Subject: ' . $codificar($assunto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $servidorLocal . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $limite . '"',
        ];

        $corpo = implode("\r\n", $cabecalhos) . "\r\n\r\n"
            . '--' . $limite . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texto))
            . '--' . $limite . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . '--' . $limite . "--\r\n";

        $ok = $enviar($corpo . "\r\n.", [250]);
    }

    fwrite($sock, "QUIT\r\n");
    fclose($sock);

    return $ok;
}
