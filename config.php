<?php
// config.php

$host = "wokstglorioltic.mysql.db"; // Cambia esto si tu servidor no es localhost
$usuario = "wokstglorioltic"; // Cambia esto por tu usuario de MySQL
$contrasena = "Pep0riol"; // Cambia esto por tu contraseña de MySQL
$base_datos = "wokstglorioltic";

// Manejo de errores
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Conexión a la base de datos
    $conexion = new mysqli($host, $usuario, $contrasena, $base_datos);
    $conexion->set_charset("utf8mb4"); // Establecer el charset
} catch (mysqli_sql_exception $e) {
    // Mostrar un mensaje de error más amigable al usuario
    die("Error de conexión a la base de datos. Por favor, contacta con el administrador.");
    // El error detallado se registrará en los logs del servidor
    error_log("Error de conexión en config.php: " . $e->getMessage());
}

// Función para generar un código único para el evento
function generarCodigoEvento($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $codigo = '';
    try {
        for ($i = 0; $i < $longitud; $i++) {
            $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }
    } catch (Exception $e) {
        // Fallback si random_int falla
        for ($i = 0; $i < $longitud; $i++) {
            $codigo .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
    }
    return $codigo;
}
?>