<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = db()->prepare("
    SELECT
        p.*,
        n.nombre_comercial,
        n.razon_social,
        n.descripcion AS negocio_descripcion,
        n.direccion AS negocio_direccion,
        n.telefono_principal,
        n.telefono_secundario,
        n.horario_texto,
        n.latitud,
        n.longitud,
        n.id_negocio,
        c.nombre AS categoria,
        c.slug AS categoria_slug,
        d.nombre AS distrito,
        u.email AS vendedor_email
    FROM productos p
    INNER JOIN negocios n ON n.id_negocio = p.negocio_id
    INNER JOIN categorias c ON c.id_categoria = p.categoria_id
    INNER JOIN usuarios u ON u.id_usuario = n.usuario_id
    LEFT JOIN distritos d ON d.id_distrito = n.distrito_id
    WHERE p.id_producto = :id
      AND p.estado = 'activo'
      AND p.deleted_at IS NULL
      AND n.activo = 1
      AND n.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$producto = $stmt->fetch();

if (!$producto) {
    http_response_code(404);
    $titulo = 'Producto no encontrado';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty">Producto no encontrado.</div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$titulo = $producto['titulo'];

$stmt = db()->prepare("
    SELECT *
    FROM imagenes_producto
    WHERE producto_id = :id AND deleted_at IS NULL
    ORDER BY es_principal DESC, orden ASC, id_imagen ASC
");
$stmt->execute([':id' => $id]);
$imagenes = $stmt->fetchAll();

$stmt = db()->prepare("
    SELECT *
    FROM horarios_atencion
    WHERE negocio_id = :id AND activo = 1 AND deleted_at IS NULL
    ORDER BY dia_semana
");
$stmt->execute([':id' => $producto['id_negocio']]);
$horarios = $stmt->fetchAll();

$dias = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo'
];

$stmt = db()->prepare("
    SELECT
        p.id_producto,
        p.titulo,
        p.precio_referencial,
        (
            SELECT ip.ruta_imagen
            FROM imagenes_producto ip
            WHERE ip.producto_id = p.id_producto AND ip.deleted_at IS NULL
            ORDER BY ip.es_principal DESC, ip.orden ASC
            LIMIT 1
        ) AS imagen_principal
    FROM productos p
    WHERE p.negocio_id = :negocio_id
      AND p.id_producto != :id
      AND p.estado = 'activo'
      AND p.deleted_at IS NULL
    ORDER BY p.created_at DESC
    LIMIT 4
");
$stmt->execute([
    ':negocio_id' => $producto['id_negocio'],
    ':id'         => $id
]);
$relacionados = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="container producto-detalle">
    <div class="detalle-grid">
        <div class="galeria">
            <?php $principal = $imagenes[0]['ruta_imagen'] ?? null; ?>
            <img id="imagenPrincipal" src="<?= h(img_producto($principal)) ?>" alt="<?= h($producto['titulo']) ?>">

            <?php if (count($imagenes) > 1): ?>
                <div class="thumbs">
                    <?php foreach ($imagenes as $img): ?>
                        <img
                            src="<?= h(img_producto($img['ruta_imagen'])) ?>"
                            alt="Miniatura"
                            data-src="<?= h($img['ruta_imagen']) ?>"
                            class="thumb <?= $img['es_principal'] ? 'active' : '' ?>"
                        >
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="info">
            <span class="card-categoria"><?= h($producto['categoria']) ?></span>
            <h1><?= h($producto['titulo']) ?></h1>
            <p class="precio-grande"><?= money($producto['precio_referencial']) ?></p>
            <p><?= nl2br(h($producto['descripcion'])) ?></p>

            <div class="negocio-box">
                <h3><?= h($producto['nombre_comercial']) ?></h3>
                <p><?= h($producto['negocio_descripcion'] ?? '') ?></p>
                <p>
                    <strong>Dirección:</strong>
                    <?= h($producto['negocio_direccion']) ?>
                    <?= $producto['distrito'] ? ' - ' . h($producto['distrito']) : '' ?>
                </p>
                <p>
                    <strong>Teléfono:</strong>
                    <a href="tel:<?= h($producto['telefono_principal']) ?>">
                        <?= h($producto['telefono_principal']) ?>
                    </a>
                </p>

                <?php if ($producto['telefono_secundario']): ?>
                    <p><strong>Teléfono 2:</strong> <?= h($producto['telefono_secundario']) ?></p>
                <?php endif; ?>

                <?php if ($producto['horario_texto']): ?>
                    <p><strong>Horario:</strong> <?= h($producto['horario_texto']) ?></p>
                <?php endif; ?>

                <a class="btn btn-primary" href="tel:<?= h($producto['telefono_principal']) ?>">
                    Llamar al vendedor
                </a>
            </div>

            <?php if ($horarios): ?>
                <div class="horarios">
                    <h4>Horarios de atención</h4>
                    <ul>
                        <?php foreach ($horarios as $h): ?>
                            <li>
                                <?= h($dias[(int)$h['dia_semana']] ?? '') ?>:
                                <?= h(substr($h['hora_apertura'], 0, 5)) ?> -
                                <?= h(substr($h['hora_cierre'], 0, 5)) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($relacionados): ?>
        <section class="relacionados">
            <h2>Más productos de este negocio</h2>
            <div class="grid-productos">
                <?php foreach ($relacionados as $r): ?>
                    <article class="card-producto">
                        <a href="producto.php?id=<?= (int)$r['id_producto'] ?>" class="card-img">
                            <img src="<?= h(img_producto($r['imagen_principal'])) ?>" alt="<?= h($r['titulo']) ?>" loading="lazy">
                        </a>
                        <div class="card-body">
                            <h3>
                                <a href="producto.php?id=<?= (int)$r['id_producto'] ?>">
                                    <?= h($r['titulo']) ?>
                                </a>
                            </h3>
                            <p class="card-precio"><?= money($r['precio_referencial']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>