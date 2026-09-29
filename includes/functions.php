<?php
function h(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

function money($precio): string
{
    if ($precio === null || $precio === '') {
        return 'Precio a consultar';
    }
    return 'S/ ' . number_format((float)$precio, 2);
}

function img_producto(?string $ruta): string
{
    if (!empty($ruta)) {
        return $ruta;
    }
    return 'assets/img/no-image.svg';
}