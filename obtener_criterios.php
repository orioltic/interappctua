<?php
// obtener_criterios.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Verificar parámetro
    if (!isset($_GET['id_evento'])) {
        throw new Exception("ID de evento no proporcionado.");
    }

    $id_evento = (int)$_GET['id_evento'];

    // Obtener información del evento
    $sql = "SELECT criterio1, criterio2, criterio3, criterio4, criterio5 FROM eventos WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conexion->error);
    }
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 0) {
        throw new Exception("Evento no encontrado.");
    }

    $evento = $resultado->fetch_assoc();
    $stmt->close();

    // Crear array con los criterios no vacíos
    $criterios = [];
    for ($i = 1; $i <= 5; $i++) {
        $campo = "criterio$i";
        if (!empty($evento[$campo])) {
            $criterios[] = [
                'nombre' => $evento[$campo],
                'indice' => $i
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'criterios' => $criterios
    ]);

} catch (Exception $e) {
    error_log("Error en obtener_criterios.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener los criterios de evaluación.'
    ]);
}
?>