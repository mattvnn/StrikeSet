<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* Marca o link da página atual como ativo */
$pagina_atual = basename($_SERVER['PHP_SELF']);

function ativo_admin($arquivo, $pagina_atual) {
    return $arquivo === $pagina_atual ? ' class="active"' : '';
}

?>

<header>
    <h2>StrikeSet Gaspar</h2>

    <nav>
        <a href="home.php">Inicio</a>
        <a href="admin_galeria.php"<?php echo ativo_admin('admin_galeria.php', $pagina_atual); ?>>Galeria</a>
        <a href="sobre.php">Sobre o Time</a>
        <a href="admin_treinos.php"<?php echo ativo_admin('admin_treinos.php', $pagina_atual); ?>>Treinos</a>
        <a href="admin_campeonatos.php"<?php echo ativo_admin('admin_campeonatos.php', $pagina_atual); ?>>Campeonatos</a>

        <span class="admin-badge">PAINEL ADMIN</span>

        <a class="btn-login" href="logout.php">Sair</a>
    </nav>
</header>
