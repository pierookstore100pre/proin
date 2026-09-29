<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$titulo = 'Inicio';

$q         = trim($_GET['q'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$distrito  = (int)($_GET['distrito'] ?? 0);
$pagina    = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 12;
$offset    = ($pagina - 1) * $porPagina;

$where = [
    "p.estado = 'activo'",
    "p.deleted_at IS NULL",
    "n.activo = 1",
    "n.deleted_at IS NULL",
    "c.activo = 1",
    "c.deleted_at IS NULL"
];
$params = [];

if ($q !== '') {
    $where[] = "(p.titulo LIKE :q OR p.descripcion LIKE :q OR n.nombre_comercial LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

if ($categoria !== '') {
    $where[] = "c.slug = :categoria";
    $params[':categoria'] = $categoria;
}

if ($distrito > 0) {
    $where[] = "n.distrito_id = :distrito";
    $params[':distrito'] = $distrito;
}

$whereSql = implode(' AND ', $where);

$sqlCount = "
    SELECT COUNT(*)
    FROM productos p
    INNER JOIN negocios n ON n.id_negocio = p.negocio_id
    INNER JOIN categorias c ON c.id_categoria = p.categoria_id
    LEFT JOIN distritos d ON d.id_distrito = n.distrito_id
    WHERE $whereSql
";
$stmt = db()->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPaginas = max(1, ceil($total / $porPagina));

$sql = "
    SELECT
        p.id_producto,
        p.titulo,
        p.descripcion,
        p.precio_referencial,
        p.destacado,
        p.created_at,
        n.id_negocio,
        n.nombre_comercial,
        n.telefono_principal,
        n.direccion,
        d.nombre AS distrito,
        c.nombre AS categoria,
        c.slug AS categoria_slug,
        (
            SELECT ip.ruta_imagen
            FROM imagenes_producto ip
            WHERE ip.producto_id = p.id_producto
              AND ip.deleted_at IS NULL
            ORDER BY ip.es_principal DESC, ip.orden ASC, ip.id_imagen ASC
            LIMIT 1
        ) AS imagen_principal
    FROM productos p
    INNER JOIN negocios n ON n.id_negocio = p.negocio_id
    INNER JOIN categorias c ON c.id_categoria = p.categoria_id
    LEFT JOIN distritos d ON d.id_distrito = n.distrito_id
    WHERE $whereSql
    ORDER BY p.destacado DESC, p.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = db()->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll();

$categorias = db()->query("
    SELECT id_categoria, nombre, slug
    FROM categorias
    WHERE activo = 1 AND deleted_at IS NULL AND categoria_padre_id IS NULL
    ORDER BY nombre
")->fetchAll();

$distritos = db()->query("
    SELECT id_distrito, nombre
    FROM distritos
    WHERE activo = 1 AND deleted_at IS NULL
    ORDER BY nombre
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Descubre negocios y productos de Tacna</h1>
        <p>Vitrina digital para comercios locales. Busca, filtra y contacta directo al vendedor.</p>

        <form class="search-form" method="get" action="index.php">
            <input type="search" name="q" placeholder="Buscar productos, negocios..." value="<?= h($q) ?>">
            <button class="btn btn-primary" type="submit">Buscar</button>
        </form>
    </div>
</section>

<section class="container filters">
    <form method="get" action="index.php" class="filter-form">
        <input type="hidden" name="q" value="<?= h($q) ?>">

        <select name="categoria">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= h($cat['slug']) ?>" <?= $categoria === $cat['slug'] ? 'selected' : '' ?>>
                    <?= h($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="distrito">
            <option value="0">Todos los distritos</option>
            <?php foreach ($distritos as $d): ?>
                <option value="<?= (int)$d['id_distrito'] ?>" <?= $distrito === (int)$d['id_distrito'] ? 'selected' : '' ?>>
                    <?= h($d['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-secondary" type="submit">Filtrar</button>
        <a class="btn btn-link" href="index.php">Limpiar</a>
    </form>
</section>

<section class="container">
    <div class="section-head">
        <h2>Productos publicados</h2>
        <span><?= $total ?> resultado(s)</span>
    </div>

    <?php if (empty($productos)): ?>
        <div class="empty">No se encontraron productos.</div>
    <?php else: ?>
        <div class="grid-productos">
            <?php foreach ($productos as $p): ?>
                <article class="card-producto">
                    <a href="producto.php?id=<?= (int)$p['id_producto'] ?>" class="card-img">
                        <img src="<?= h(img_producto($p['imagen_principal'])) ?>" alt="<?= h($p['titulo']) ?>" loading="lazy">
                        <?php if ($p['destacado']): ?>
                            <span class="badge-destacado">Destacado</span>
                        <?php endif; ?>
                    </a>

                    <div class="card-body">
                        <span class="card-categoria"><?= h($p['categoria']) ?></span>
                        <h3>
                            <a href="producto.php?id=<?= (int)$p['id_producto'] ?>">
                                <?= h($p['titulo']) ?>
                            </a>
                        </h3>
                        <p class="card-negocio">
                            <?= h($p['nombre_comercial']) ?> · <?= h($p['distrito'] ?? 'Tacna') ?>
                        </p>
                        <p class="card-precio"><?= money($p['precio_referencial']) ?></p>
                        <a class="btn btn-small" href="producto.php?id=<?= (int)$p['id_producto'] ?>">Ver detalle</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="pagination">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <?php $qs = $_GET; $qs['pagina'] = $i; ?>
                    <a class="<?= $i === $pagina ? 'active' : '' ?>" href="?<?= h(http_build_query($qs)) ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>