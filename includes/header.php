<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$categorias_menu = db()->query("
    SELECT id_categoria, nombre, slug
    FROM categorias
    WHERE activo = 1 AND deleted_at IS NULL AND categoria_padre_id IS NULL
    ORDER BY nombre
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? h($titulo) . ' | ' : '' ?>Vitrina Digital Tacna</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.php">Vitrina<span>Tacna</span></a>

        <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">☰</button>

        <nav class="main-nav" id="mainNav">
            <a href="index.php">Inicio</a>
            <?php foreach ($categorias_menu as $cat): ?>
                <a href="index.php?categoria=<?= urlencode($cat['slug']) ?>">
                    <?= h($cat['nombre']) ?>
                </a>
            <?php endforeach; ?>
            <a class="btn btn-outline" href="#">Ingresar</a>
        </nav>
    </div>
</header>

<main>