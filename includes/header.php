<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

?>

<header>
    <h2>StrikeSet Gaspar</h2>

    <nav>
        <a href="home.php">Inicio</a>
        <a href="galeria.php">Galeria</a>
        <a href="sobre.php">Sobre o Time</a>
        <a href="treinos.php">Treinos</a>
        <a href="campeonatos.php">Campeonatos</a>

        <?php if (isset($_SESSION['usuario_id'])): ?>

            <?php if (($_SESSION['tipo'] ?? '') === 'admin'): ?>

                <a class="btn-login" href="admin_treinos.php">PAINEL ADMIN</a>

            <?php else: ?>

                <a class="btn-login" href="logout.php">SAIR</a>

            <?php endif; ?>

        <?php else: ?>

            <a class="btn-login" href="login.php">LOGIN/REGISTRO</a>

        <?php endif; ?>

    </nav>
</header>
