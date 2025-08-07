<?php
// verificar_inicio_evento.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Verificar parámetro
    if (!isset($_GET['id_evento'])) {
        throw new Exception("ID de evento no proporcionado.");
    }

    $id_evento = (int)$_GET['id_evento'];

    // Verificación más estricta: 
    // ¿Existe AL MENOS UN turno para este evento QUE NO esté finalizado?
    // Esta es la condición para considerar el evento "iniciado" según la lógica de admin_panel.php
    $sql = "SELECT COUNT(*) as turnos_activos 
            FROM turnos 
            WHERE id_evento = ? AND finalizado = 0";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $row = $resultado->fetch_assoc();
    $turnos_activos = (int)$row['turnos_activos'];
    $stmt->close();

    // El evento se considera INICIADO si hay al menos un turno creado y no finalizado
    // Esto sucede cuando el admin pulsa "Iniciar" en admin_panel.php y se crean los turnos
    $evento_iniciado = ($turnos_activos > 0);

    // Para depuración, puedes descomentar la siguiente línea temporalmente
    // error_log("Verificando inicio evento $id_evento: Turnos activos: $turnos_activos, Iniciado: " . ($evento_iniciado ? 'SI' : 'NO'));

    echo json_encode([
        'iniciado' => $evento_iniciado,
        'debug_turnos_activos' => $turnos_activos // Opcional, para depuración
    ]);

} catch (Exception $e) {
    error_log("Error en verificar_inicio_evento.php: " . $e->getMessage());
    echo json_encode([
        'iniciado' => false,
        'error' => 'Error al verificar el estado del evento.'
    ]);
}
?>