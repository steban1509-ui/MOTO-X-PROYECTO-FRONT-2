<?php
/**
 * <head> común.
 * Variables opcionales antes de incluirlo:
 *   $titulo        string  Título de la pestaña
 *   $estilos       array   Hojas CSS propias: ['css/catalogo.css']
 *   $usarBootstrap bool    (true por defecto)
 *   $claseBody     string
 */
$usarBootstrap = $usarBootstrap ?? true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? APP_NAME) ?></title>
    <?php if ($usarBootstrap): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/comun.css')) ?>">
    <?php foreach (($estilos ?? []) as $hojaCss): ?>
    <link rel="stylesheet" href="<?= e(asset($hojaCss)) ?>">
    <?php endforeach; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body<?= !empty($claseBody) ? ' class="' . e($claseBody) . '"' : '' ?>>
