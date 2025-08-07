<?php
// sesion_participante.php
require_once 'config.php';

// Verificar si se proporcionó un código de evento
if (!isset($_GET['codigo'])) {
    die("Código de evento no proporcionado.");
}

$codigo_evento = $_GET['codigo'];

// Verificar si el evento existe
$sql_evento = "SELECT id, nombre, tiempo_maximo FROM eventos WHERE codigo_evento = ?";
$stmt_evento = $conexion->prepare($sql_evento);
$stmt_evento->bind_param("s", $codigo_evento);
$stmt_evento->execute();
$resultado_evento = $stmt_evento->get_result();

if ($resultado_evento->num_rows == 0) {
    die("Evento no encontrado.");
}

$evento = $resultado_evento->fetch_assoc();
$id_evento = $evento['id'];
$stmt_evento->close();

// Verificar si el usuario está registrado (usando la cookie)
if (!isset($_COOKIE['usuario_id'])) {
    // Si no hay cookie de usuario, redirigir al registro
    header("Location: participante.php?codigo=" . urlencode($codigo_evento));
    exit();
}

$id_usuario = (int)$_COOKIE['usuario_id'];

// Obtener información del usuario
$sql_usuario = "SELECT * FROM usuarios WHERE id = ?";
$stmt_usuario = $conexion->prepare($sql_usuario);
$stmt_usuario->bind_param("i", $id_usuario);
$stmt_usuario->execute();
$resultado_usuario = $stmt_usuario->get_result();

if ($resultado_usuario->num_rows == 0) {
    // Si el usuario no existe, redirigir al registro
    header("Location: participante.php?codigo=" . urlencode($codigo_evento));
    exit();
}

$usuario = $resultado_usuario->fetch_assoc();
$stmt_usuario->close();

// Obtener el turno actual (el primero no finalizado)
$sql_turno_actual = "SELECT t.*, u.nombre, u.apellidos FROM turnos t JOIN usuarios u ON t.id_usuario = u.id WHERE t.id_evento = ? AND t.finalizado = 0 ORDER BY t.orden LIMIT 1";
$stmt_turno_actual = $conexion->prepare($sql_turno_actual);
$stmt_turno_actual->bind_param("i", $id_evento);
$stmt_turno_actual->execute();
$resultado_turno_actual = $stmt_turno_actual->get_result();
$turno_actual = $resultado_turno_actual->fetch_assoc();
$stmt_turno_actual->close();

// Determinar si el usuario actual es el ponente
$es_ponente_actual = ($turno_actual && $turno_actual['id_usuario'] == $id_usuario);

// Verificar si el turno actual ya ha finalizado (para mostrar el botón de votación)
$turno_finalizado = false;
if ($turno_actual) {
    $sql_turno_finalizado = "SELECT finalizado FROM turnos WHERE id = ?";
    $stmt_turno_finalizado = $conexion->prepare($sql_turno_finalizado);
    $stmt_turno_finalizado->bind_param("i", $turno_actual['id']);
    $stmt_turno_finalizado->execute();
    $resultado_turno_finalizado = $stmt_turno_finalizado->get_result();
    if ($row = $resultado_turno_finalizado->fetch_assoc()) {
        $turno_finalizado = (bool)$row['finalizado'];
    }
    $stmt_turno_finalizado->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InterAPPctúa - Sesión</title>
    <link rel="stylesheet" href="./css/style.css">
    <style>
        /* Estilos adicionales para esta página */
        .waiting-screen {
            text-align: center;
            padding: 2rem;
        }
        
        .expositor-actual {
            background-color: var(--urjc-azul-claro);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .expositor-actual-ponente {
            background-color: var(--danger);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .pregunta-form {
            margin-bottom: 2rem;
        }
        
        .pregunta-item {
            border-left: 4px solid var(--urjc-azul-claro);
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: var(--urjc-gris-claro);
        }
        
        .pregunta-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--urjc-gris-medio);
        }
        
        .pregunta-texto {
            margin-bottom: 1rem;
        }
        
        .pregunta-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .like-btn {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: var(--urjc-gris-medio);
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: var(--border-radius);
        }
        
        .like-btn:hover {
            color: var(--urjc-azul-oscuro);
            background-color: rgba(0, 149, 218, 0.1);
        }
        
        .like-btn.liked {
            color: var(--urjc-azul-claro);
        }
        
        .like-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .like-count {
            margin-left: 0.5rem;
            font-weight: 600;
        }
        
        .edit-btn {
            background: none;
            border: none;
            color: var(--urjc-gris-medio);
            cursor: pointer;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: var(--border-radius);
            font-size: 1.2rem;
        }
        
        .edit-btn:hover {
            color: var(--urjc-azul-oscuro);
            background-color: rgba(0, 149, 218, 0.1);
        }
        
        .star-rating {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        
        .star-btn {
            font-size: 2rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
            padding: 0.5rem;
            border-radius: var(--border-radius);
        }
        
        .star-btn:hover {
            color: gold;
            background-color: rgba(255, 215, 0, 0.1);
        }
        
        .star-btn.active {
            color: gold;
        }
        
        .votacion-section {
            margin-bottom: 2rem;
            padding: 1rem;
            border: 1px solid var(--urjc-azul-claro);
            border-radius: var(--border-radius);
            background-color: var(--urjc-gris-claro);
        }
        
        .votacion-section h3 {
            margin-top: 0;
            color: var(--urjc-azul-oscuro);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .pregunta-header {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .expositor-actual-ponente {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>InterAPPctúa</h1>
        <p><?php echo htmlspecialchars($evento['nombre']); ?></p>
    </header>
    
    <div class="container">
        <div class="card">
            <h2 class="card-title">Sesión del Evento</h2>
            
            <div class="phase-indicator">Fase 1: Exposición</div>
            
            <!-- Pantalla de espera mientras no hay expositor -->
            <div id="waiting-screen" class="waiting-screen" style="display: <?php echo $turno_actual ? 'none' : 'block'; ?>;">
                <h3>¡Te has registrado correctamente!</h3>
                <p>Espera a que el administrador inicie el evento.</p>
            </div>
            
            <!-- Pantalla principal -->
            <div id="main-screen" style="display: <?php echo $turno_actual ? 'block' : 'none'; ?>;">
                <?php if ($es_ponente_actual): ?>
                    <!-- Pantalla para el ponente actual -->
                    <div class="expositor-actual-ponente">
                        <p>¡Eres tú quien expone! 🖕</p>
                    </div>
                <?php else: ?>
                    <!-- Pantalla para otros participantes -->
                    <div class="expositor-actual">
                        <h3>Expositor Actual</h3>
                        <?php if ($turno_actual): ?>
                            <p id="expositor-nombre" style="font-size: 2rem; font-weight: bold;">
                                <?php echo htmlspecialchars($turno_actual['nombre'] . ' ' . $turno_actual['apellidos']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sección de votación (solo para otros participantes) -->
                    <div class="votacion-section" id="votacion-section">
                        <h3>Votación del Expositor</h3>
                        <div id="votacion-content">
                            <!-- Los criterios de votación se cargarán aquí -->
                        </div>
                        <button id="enviar-votacion" class="btn btn-success btn-block mt-2">Enviar Votación</button>
                    </div>
                    
                    <!-- Formulario para hacer preguntas (solo para otros participantes) -->
                    <div class="card pregunta-form">
                        <h3>Hacer una Pregunta</h3>
                        <form id="pregunta-form">
                            <div class="form-group">
                                <textarea id="texto-pregunta" name="texto_pregunta" placeholder="Escribe tu pregunta aquí..." rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Enviar Pregunta</button>
                        </form>
                    </div>
                    
                    <!-- Listado de preguntas -->
                    <div class="card">
                        <h3>Preguntas Realizadas</h3>
                        <div id="preguntas-list" class="question-list">
                            <!-- Las preguntas se cargarán aquí dinámicamente -->
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <p>InterAPPctúa - Plataforma de Gamificación por Oriol Borrás-Gené (@OriolTIC)</p>
        </div>
    </footer>

    <script>
        // Variables globales
        const idEvento = <?php echo (int)$id_evento; ?>;
        const idUsuario = <?php echo (int)$id_usuario; ?>;
        const tiempoMaximo = <?php echo (int)$evento['tiempo_maximo']; ?>;
        let timeLeft = tiempoMaximo;
        let timerRunning = false;
        let idTurnoActual = <?php echo $turno_actual ? (int)$turno_actual['id'] : 0; ?>;
        let idExpositorActual = <?php echo $turno_actual ? (int)$turno_actual['id_usuario'] : 0; ?>;
        const esPonenteActual = <?php echo $es_ponente_actual ? 'true' : 'false'; ?>;
        let turnoFinalizado = <?php echo $turno_finalizado ? 'true' : 'false'; ?>;
        
        // Inicializar la aplicación
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar preguntas iniciales (solo si no es el ponente actual)
            if (!esPonenteActual) {
                cargarPreguntas();
                // Cargar criterios de votación
                cargarCriteriosVotacion();
            }
            
            // Configurar actualización periódica (cada 5 segundos)
            if (!esPonenteActual) {
                setInterval(cargarPreguntas, 5000); // Actualizar preguntas cada 5 segundos
            }
            
            // Configurar eventos de formulario (solo si no es el ponente actual)
            if (!esPonenteActual) {
                document.getElementById('pregunta-form').addEventListener('submit', enviarPregunta);
                document.getElementById('enviar-votacion').addEventListener('click', enviarVotacion);
            }
            
            // Configurar eventos de las estrellas de votación (solo si no es el ponente actual)
            if (!esPonenteActual) {
                // Los eventos se configurarán dinámicamente al cargar los criterios
            }
        });
        
        // Función para cargar preguntas (solo para otros participantes)
        function cargarPreguntas() {
            if (idTurnoActual <= 0 || esPonenteActual) return;
            
            fetch(`obtener_preguntas.php?id_evento=${idEvento}&id_expositor=${idExpositorActual}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarPreguntasEnUI(data.preguntas);
                    }
                })
                .catch(error => {
                    console.error('Error al cargar preguntas:', error);
                });
        }
        
        // Función para mostrar preguntas en la UI
        function mostrarPreguntasEnUI(preguntas) {
            const container = document.getElementById('preguntas-list');
            if (!container) return;
            
            if (preguntas.length === 0) {
                container.innerHTML = '<p class="text-center">No hay preguntas aún.</p>';
                return;
            }
            
            container.innerHTML = preguntas.map(pregunta => {
                // Determinar si el usuario actual es el autor de esta pregunta
                const esAutor = pregunta.id_autor == idUsuario;
                // Determinar si el usuario actual es el destinatario de esta pregunta
                const esDestinatario = pregunta.id_destinatario == idUsuario;
                
                return `
                <div class="pregunta-item" data-id-pregunta="${pregunta.id}">
                    <div class="pregunta-header">
                        <span>Para: ${pregunta.nombre_destinatario} ${pregunta.apellidos_destinatario} 🎤</span>
                        <span>Por: ${pregunta.nombre_autor} ${pregunta.apellidos_autor}</span>
                    </div>
                    <div class="pregunta-texto">
                        ${pregunta.texto}
                    </div>
                    <div class="pregunta-actions">
                        <button class="like-btn ${pregunta.usuario_ha_votado ? 'liked' : ''}" 
                                onclick="toggleLike(${pregunta.id}, this)"
                                ${esAutor ? 'disabled title="No puedes votar tu propia pregunta"' : ''}>
                            👍 <span class="like-count">${pregunta.votos}</span>
                        </button>
                        ${esAutor && pregunta.votos == 0 ? 
                            `<button class="edit-btn" onclick="editarPregunta(${pregunta.id})" title="Editar pregunta">✏️</button>` : 
                            ''
                        }
                    </div>
                </div>
            `}).join('');
        }
        
        // Función para enviar pregunta (solo para otros participantes)
        function enviarPregunta(e) {
            e.preventDefault();
            
            const textoPregunta = document.getElementById('texto-pregunta').value.trim();
            if (!textoPregunta) return;
            
            const formData = new FormData();
            formData.append('id_evento', idEvento);
            formData.append('id_destinatario', idExpositorActual);
            formData.append('id_autor', idUsuario);
            formData.append('texto', textoPregunta);
            
            fetch('enviar_pregunta.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('texto-pregunta').value = '';
                    cargarPreguntas(); // Recargar preguntas
                } else {
                    alert('Error al enviar la pregunta: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al enviar la pregunta.');
            });
        }
        
        // Función para toggle like
        function toggleLike(idPregunta, buttonElement) {
            // Verificar si el botón está deshabilitado
            if (buttonElement.disabled) {
                return;
            }
            
            const formData = new FormData();
            formData.append('id_pregunta', idPregunta);
            formData.append('id_usuario', idUsuario);
            
            fetch('toggle_like.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar el contador de likes
                    const countElement = buttonElement.querySelector('.like-count');
                    countElement.textContent = data.nuevos_votos;
                    
                    // Cambiar clase para indicar que se ha votado
                    if (data.accion === 'agregado') {
                        buttonElement.classList.add('liked');
                    } else {
                        buttonElement.classList.remove('liked');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // Función para editar pregunta
        function editarPregunta(idPregunta) {
            const nuevoTexto = prompt('Edita tu pregunta:');
            if (nuevoTexto !== null && nuevoTexto.trim() !== '') {
                const formData = new FormData();
                formData.append('id_pregunta', idPregunta);
                formData.append('nuevo_texto', nuevoTexto.trim());
                
                fetch('editar_pregunta.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cargarPreguntas(); // Recargar preguntas
                    } else {
                        alert('Error al editar la pregunta: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión al editar la pregunta.');
                });
            }
        }
        
        // Función para cargar criterios de votación
        function cargarCriteriosVotacion() {
            fetch(`obtener_criterios.php?id_evento=${idEvento}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarCriteriosEnUI(data.criterios);
                    }
                })
                .catch(error => {
                    console.error('Error al cargar criterios:', error);
                });
        }
        
        // Función para mostrar criterios en la UI
        function mostrarCriteriosEnUI(criterios) {
            const container = document.getElementById('votacion-content');
            if (!container) return;
            
            if (criterios.length === 0) {
                container.innerHTML = '<p class="text-center">No hay criterios de evaluación definidos para este evento.</p>';
                return;
            }
            
            container.innerHTML = `
                <p>Votando a: <strong>${document.getElementById('expositor-nombre') ? document.getElementById('expositor-nombre').textContent : 'Expositor'}</strong></p>
                ${criterios.map(criterio => `
                    <div class="form-group">
                        <label>${criterio.nombre}:</label>
                        <div class="star-rating" data-criterio="${criterio.nombre}">
                            ${[10, 20, 30, 40, 50].map(valor => `
                                <button class="star-btn" data-valor="${valor}">★</button>
                            `).join('')}
                        </div>
                    </div>
                `).join('')}
            `;
            
            // Agregar eventos a los botones de estrella
            document.querySelectorAll('.star-rating').forEach(rating => {
                const criterio = rating.getAttribute('data-criterio');
                const stars = rating.querySelectorAll('.star-btn');
                
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const valor = parseInt(this.getAttribute('data-valor'));
                        
                        // Verificar si esta estrella ya está activa
                        const isActive = this.classList.contains('active');
                        
                        // Desactivar todas las estrellas del mismo criterio
                        stars.forEach(s => s.classList.remove('active'));
                        
                        // Si la estrella clickeada no estaba activa, activarla y las anteriores
                        if (!isActive) {
                            let activate = true;
                            stars.forEach(s => {
                                if (activate) {
                                    s.classList.add('active');
                                }
                                if (s === star) {
                                    activate = false;
                                }
                            });
                        }
                        // Si estaba activa, al hacer clic se desactivan todas (ya lo hicimos arriba)
                        // Esto permite desmarcar
                    });
                });
            });
        }
        
        // Función para enviar votación
        function enviarVotacion() {
            // Recopilar las votaciones de cada criterio
            const votaciones = [];
            let todasVacias = true; // Bandera para verificar si todas son 0
            document.querySelectorAll('.star-rating').forEach(rating => {
                const criterio = rating.getAttribute('data-criterio');
                const estrellaActiva = rating.querySelector('.star-btn.active');
                const puntos = estrellaActiva ? parseInt(estrellaActiva.getAttribute('data-valor')) : 0;
                
                // Validar puntos (deben ser 0, 10, 20, 30, 40, o 50)
                if (![0, 10, 20, 30, 40, 50].includes(puntos)) {
                    alert(`Error: Valor de puntos inválido para el criterio "${criterio}".`);
                    console.error(`Puntos inválidos para ${criterio}:`, puntos);
                    // Limpiar votaciones y salir
                    votaciones.length = 0;
                    return;
                }

                if (puntos > 0) todasVacias = false; // Al menos una tiene puntos

                votaciones.push({
                    criterio: criterio,
                    puntos: puntos
                });
            });
            
            // Validar que se haya hecho al menos una votación
            if (todasVacias) {
                if (!confirm("No has seleccionado ninguna puntuación. ¿Quieres enviar la votación con 0 puntos en todos los criterios?")) {
                    return; // Cancelar envío
                }
            }

            // Enviar votaciones al servidor
            const formData = new FormData();
            formData.append('id_evento', idEvento);
            formData.append('id_votante', idUsuario);
            formData.append('id_ponente', idExpositorActual);
            formData.append('votaciones', JSON.stringify(votaciones));
            
            fetch('enviar_votacion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Votación enviada correctamente.');
                    // Opcional: Limpiar las estrellas seleccionadas
                    document.querySelectorAll('.star-btn.active').forEach(btn => btn.classList.remove('active'));
                } else {
                    alert('Error al enviar la votación: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al enviar la votación.');
            });
        }
        
        // Función para actualizar la sesión periódicamente
        function actualizarSesion() {
            fetch(`actualizar_sesion.php?id_evento=${idEvento}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Si hay un nuevo turno, actualizar la interfaz
                        if (data.nuevo_turno && data.turno_actual.id != idTurnoActual) {
                            console.log(">>> CAMBIO DE ID DE TURNO detectado (" + idTurnoActual + " -> " + data.turno_actual.id + "). El admin pulsó Next. Recargando...");
                            idTurnoActual = data.turno_actual.id;
                            idExpositorActual = data.turno_actual.id_usuario;
                            
                            // Actualizar información del expositor
                            if (document.getElementById('expositor-nombre')) {
                                document.getElementById('expositor-nombre').textContent = 
                                    data.turno_actual.nombre + ' ' + data.turno_actual.apellidos;
                            }
                            
                            // Mostrar pantalla principal si estaba oculta
                            document.getElementById('waiting-screen').style.display = 'none';
                            document.getElementById('main-screen').style.display = 'block';
                            
                            // Recargar preguntas (solo si no es el ponente actual)
                            // Y recargar la interfaz completa para verificar si el usuario es el nuevo ponente
                            location.reload(); // Recargar toda la página para asegurar que se actualice correctamente
                        }
                        
                        // Si no hay turno y la pantalla principal está visible, mostrar pantalla de espera
                        if (!data.nuevo_turno && document.getElementById('main-screen').style.display === 'block') {
                            document.getElementById('main-screen').style.display = 'none';
                            document.getElementById('waiting-screen').style.display = 'block';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error al actualizar sesión:', error);
                });
        }
        
        // Configurar actualización periódica de la sesión (cada 5 segundos)
        setInterval(actualizarSesion, 5000);
    </script>
</body>
</html>