<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calendar_functions.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeOrganization = $activeOrganizationId ? getOrganizationById($conn, $activeOrganizationId, $userId) : false;

if (!$activeOrganization) {
    $activeOrganization = [
        'name' => 'Sin organización',
        'slug' => 'sin-organizacion',
    ];
}

$activeTeamId = $activeOrganizationId ? getActiveTeamId($conn, (int) $activeOrganizationId) : null;
$activeTeam = $activeTeamId ? getTeamById($conn, (int) $activeTeamId, (int) $activeOrganizationId) : false;
$organizationTeams = $activeOrganizationId ? getOrganizationTeams($conn, (int) $activeOrganizationId) : [];
$organizationMembers = $activeOrganizationId ? getOrganizationMembers($conn, (int) $activeOrganizationId) : [];
$eventTypeOptions = getCalendarEventTypeOptions();
$participantStatusOptions = getCalendarParticipantStatusOptions();
$errors = [];
$successMessage = '';

if (!empty($_SESSION['flash_success'])) {
    $successMessage = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$eventId = (int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$event = false;
$eventParticipants = [];

if ($activeOrganizationId && $eventId > 0) {
    $event = getCalendarEventById($conn, $eventId, (int) $activeOrganizationId);
    if ($event) {
        $eventParticipants = getCalendarEventParticipants($conn, $eventId);
    }
}

$selectedTeamId = null;
if ($event) {
    $selectedTeamId = $event['team_id'] !== null ? (int) $event['team_id'] : null;
} elseif (array_key_exists('team_id', $_POST)) {
    $teamIdFromPost = (int) ($_POST['team_id'] ?? 0);
    $selectedTeamId = $teamIdFromPost > 0 ? $teamIdFromPost : null;
} elseif ($activeTeamId !== null) {
    $selectedTeamId = (int) $activeTeamId;
}

$selectedTeam = $selectedTeamId ? getTeamById($conn, (int) $selectedTeamId, (int) ($activeOrganizationId ?? 0)) : false;
if (!$selectedTeam && $selectedTeamId !== null) {
    $selectedTeamId = null;
}

$defaultParticipantIds = [];
if ($selectedTeamId !== null) {
    $selectedTeamMembers = getTeamMembers($conn, (int) $selectedTeamId);
    foreach ($selectedTeamMembers as $member) {
        $defaultParticipantIds[] = (int) $member['user_id'];
    }
}

$participantDefaults = [];
foreach ($organizationMembers as $member) {
    $participantDefaults[(int) $member['user_id']] = in_array((int) $member['user_id'], $defaultParticipantIds, true) ? 'invited' : 'none';
}

if (!empty($eventParticipants)) {
    foreach ($eventParticipants as $participant) {
        $participantDefaults[(int) $participant['user_id']] = (string) $participant['status'];
    }
}

$tomorrow = new DateTimeImmutable('tomorrow 18:00');
$defaultStart = $tomorrow->format('Y-m-d\TH:i');
$defaultEnd = $tomorrow->modify('+1 hour')->format('Y-m-d\TH:i');

$formState = [
    'title' => $event['title'] ?? '',
    'description' => $event['description'] ?? '',
    'event_type' => $event['event_type'] ?? 'practice',
    'start_datetime' => $event['start_datetime'] ?? $defaultStart,
    'end_datetime' => $event['end_datetime'] ?? $defaultEnd,
    'location' => $event['location'] ?? '',
    'team_id' => $event ? ($event['team_id'] !== null ? (int) $event['team_id'] : '') : ($selectedTeamId ?? ''),
];

$participantState = $participantDefaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['event_action'] ?? 'save_event');

    if (!$activeOrganizationId) {
        $errors[] = 'Necesitas una organización activa para gestionar eventos';
    } elseif ($action === 'delete_event') {
        if ($eventId > 0 && $event) {
            $monthRedirect = (new DateTimeImmutable((string) $event['start_datetime']))->format('Y-m');
            deleteCalendarEvent($conn, $eventId, (int) $activeOrganizationId);
            $_SESSION['flash_success'] = 'Evento eliminado';
            header('Location: app.php?view=calendar&month=' . $monthRedirect);
            exit;
        }

        $errors[] = 'No se ha encontrado el evento para eliminar';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $eventType = strtolower(trim((string) ($_POST['event_type'] ?? 'practice')));
        $startInput = trim((string) ($_POST['start_datetime'] ?? ''));
        $endInput = trim((string) ($_POST['end_datetime'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $teamIdInput = (int) ($_POST['team_id'] ?? 0);
        $participantRows = $_POST['participants'] ?? [];

        $allowedEventTypes = array_column($eventTypeOptions, 'key');

        if ($title === '') {
            $errors[] = 'El título del evento es obligatorio';
        }

        if (!in_array($eventType, $allowedEventTypes, true)) {
            $errors[] = 'Selecciona un tipo de evento válido';
        }

        $startDateObject = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startInput);
        $endDateObject = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endInput);

        if (!$startDateObject) {
            $errors[] = 'La fecha de inicio no es válida';
        }

        if (!$endDateObject) {
            $errors[] = 'La fecha de fin no es válida';
        }

        if ($startDateObject && $endDateObject && $endDateObject <= $startDateObject) {
            $errors[] = 'La fecha de fin debe ser posterior a la de inicio';
        }

        $validTeamIds = array_map(static fn (array $team): int => (int) $team['id'], $organizationTeams);
        $selectedTeamId = $teamIdInput > 0 ? $teamIdInput : null;
        if ($selectedTeamId !== null && !in_array($selectedTeamId, $validTeamIds, true)) {
            $errors[] = 'Selecciona un equipo válido';
        }

        $normalizedParticipants = [];
        foreach ($participantRows as $participantUserId => $status) {
            $participantUserId = (int) $participantUserId;
            $status = is_array($status) ? (string) ($status['status'] ?? '') : (string) $status;
            $status = strtolower(trim($status));

            if ($participantUserId <= 0 || !in_array($status, ['invited', 'accepted', 'declined'], true)) {
                continue;
            }

            $normalizedParticipants[$participantUserId] = $status;
        }

        if (empty($errors)) {
            $normalizedDescription = $description !== '' ? $description : null;
            $normalizedLocation = $location !== '' ? $location : null;
            $normalizedStart = $startDateObject->format('Y-m-d H:i:s');
            $normalizedEnd = $endDateObject->format('Y-m-d H:i:s');

            if ($event && $eventId > 0) {
                updateCalendarEvent(
                    $conn,
                    $eventId,
                    (int) $activeOrganizationId,
                    $selectedTeamId,
                    $title,
                    $normalizedDescription,
                    $eventType,
                    $normalizedStart,
                    $normalizedEnd,
                    $normalizedLocation,
                    $normalizedParticipants
                );
                $_SESSION['flash_success'] = 'Evento actualizado';
            } else {
                $createdEventId = createCalendarEvent(
                    $conn,
                    (int) $activeOrganizationId,
                    $selectedTeamId,
                    $title,
                    $normalizedDescription,
                    $eventType,
                    $normalizedStart,
                    $normalizedEnd,
                    $normalizedLocation,
                    $userId,
                    $normalizedParticipants
                );
                $eventId = $createdEventId;
                $_SESSION['flash_success'] = 'Evento creado';
            }

            $monthRedirect = $startDateObject->format('Y-m');
            header('Location: app.php?view=calendar&month=' . $monthRedirect);
            exit;
        }

        $formState = [
            'title' => $title,
            'description' => $description,
            'event_type' => $eventType,
            'start_datetime' => $startInput,
            'end_datetime' => $endInput,
            'location' => $location,
            'team_id' => $selectedTeamId ?? '',
        ];

        if (!empty($normalizedParticipants)) {
            foreach ($normalizedParticipants as $participantUserId => $status) {
                $participantState[$participantUserId] = $status;
            }
        }
    }
}

$eventMonthObject = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($formState['start_datetime'] ?? ''));
if (!$eventMonthObject) {
    $eventMonthObject = new DateTimeImmutable('first day of this month');
}

$eventMonth = $eventMonthObject->format('Y-m');
$pageScripts[] = 'js/modules/calendar.js';
$pageTitle = $event ? 'Editar evento' : 'Nuevo evento';
$pageEyebrow = 'Modulo';
$pageDescription = $event ? 'Edición visual de un evento del calendario con participación y contexto operativo.' : 'Alta visual de un evento del calendario con participación y contexto del equipo.';
$activeSection = 'calendar';
?>

<section class="calendar-page event-form-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Gestión de calendario</div>
            <h2 class="h2"><?php echo $event ? 'Editar evento' : 'Nuevo evento'; ?></h2>
            <p>El calendario ya no solo se consulta. Desde aquí puedes registrar eventos, asignarlos a un equipo y marcar quién participa.</p>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Organización activa</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars((string) ($activeOrganization['name'] ?? 'Sin organización'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Participantes marcados</div>
                <div class="dashboard-hero-value"><?php echo count(array_filter($participantState, static fn (string $status): bool => $status !== 'none')); ?></div>
            </div>
        </div>
    </div>

    <?php if (!$activeOrganizationId): ?>
        <div class="card dashboard-empty-state">
            No tienes una organización activa. Antes de crear eventos necesitas entrar en un contexto válido.
        </div>
    <?php else: ?>
        <?php if (!empty($successMessage)): ?>
            <div class="error-box app-feedback app-feedback-success" data-flash-message role="status" aria-live="polite">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <?php foreach ($errors as $error): ?>
                    <div class="error-box"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid-2 event-form-grid">
            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Datos del evento</div>
                        <h3 class="h3"><?php echo $event ? htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') : 'Completa la información base'; ?></h3>
                    </div>
                    <a class="btn btn-secondary" href="app.php?view=calendar&amp;month=<?php echo htmlspecialchars($eventMonth, ENT_QUOTES, 'UTF-8'); ?>">Volver al calendario</a>
                </div>

                <form class="form" method="post" novalidate>
                    <input type="hidden" name="event_id" value="<?php echo (int) $eventId; ?>" />
                    <input type="hidden" name="event_action" value="save_event" />

                    <div class="event-form-grid-fields">
                        <div class="field">
                            <label for="event_title">Título</label>
                            <input id="event_title" name="title" type="text" placeholder="Entrenamiento de semana" value="<?php echo htmlspecialchars((string) ($formState['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>

                        <div class="field">
                            <label for="event_type">Tipo</label>
                            <select id="event_type" name="event_type">
                                <?php foreach ($eventTypeOptions as $eventTypeOption): ?>
                                    <option value="<?php echo htmlspecialchars($eventTypeOption['key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) ($formState['event_type'] ?? 'practice') === $eventTypeOption['key'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($eventTypeOption['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="event_team">Equipo</label>
                            <select id="event_team" name="team_id">
                                <option value="">Organización completa</option>
                                <?php foreach ($organizationTeams as $team): ?>
                                    <option value="<?php echo (int) $team['id']; ?>" <?php echo (int) ($formState['team_id'] ?? 0) === (int) $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="event_location">Ubicación</label>
                            <input id="event_location" name="location" type="text" placeholder="Discord / Bootcamp / Arena" value="<?php echo htmlspecialchars((string) ($formState['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>

                        <div class="field">
                            <label for="event_start">Inicio</label>
                            <input id="event_start" name="start_datetime" type="datetime-local" value="<?php echo htmlspecialchars((string) ($formState['start_datetime'] ?? $defaultStart), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>

                        <div class="field">
                            <label for="event_end">Fin</label>
                            <input id="event_end" name="end_datetime" type="datetime-local" value="<?php echo htmlspecialchars((string) ($formState['end_datetime'] ?? $defaultEnd), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                    </div>

                    <div class="field">
                        <label for="event_description">Descripción</label>
                        <textarea id="event_description" name="description" placeholder="Objetivo, preparación o notas del evento..."><?php echo htmlspecialchars((string) ($formState['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="dashboard-section-head event-form-section-head">
                        <div>
                            <div class="small">Participación</div>
                            <h3 class="h3">Estados del evento</h3>
                        </div>
                        <span class="badge badge-info"><?php echo count($organizationMembers); ?> miembros</span>
                    </div>

                    <?php if (empty($organizationMembers)): ?>
                        <div class="dashboard-empty-state">No hay miembros disponibles para asignar participación.</div>
                    <?php else: ?>
                        <div class="event-participant-grid">
                            <?php foreach ($organizationMembers as $member): ?>
                                <?php
                                    $memberId = (int) $member['user_id'];
                                    $currentStatus = $participantState[$memberId] ?? 'none';
                                ?>
                                <div class="event-participant-row">
                                    <div class="event-participant-main">
                                        <div class="event-participant-name"><?php echo htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="event-participant-meta"><?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) $member['role'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="field event-participant-status-field">
                                        <label class="sr-only" for="participant_<?php echo $memberId; ?>">Estado</label>
                                        <select id="participant_<?php echo $memberId; ?>" name="participants[<?php echo $memberId; ?>]">
                                            <?php foreach ($participantStatusOptions as $statusOption): ?>
                                                <option value="<?php echo htmlspecialchars($statusOption['key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentStatus === $statusOption['key'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($statusOption['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="scrim-form-actions">
                        <button class="btn btn-primary" type="submit"><?php echo $event ? 'Guardar evento' : 'Crear evento'; ?></button>
                        <a class="btn btn-secondary" href="app.php?view=calendar&amp;month=<?php echo htmlspecialchars($eventMonth, ENT_QUOTES, 'UTF-8'); ?>">Cancelar</a>
                        <?php if ($event): ?>
                            <button class="btn btn-secondary" type="submit" name="event_action" value="delete_event" onclick="return confirm('¿Eliminar este evento?');">Eliminar</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Base preparada</div>
                        <h3 class="h3">Qué queda preparado</h3>
                    </div>
                </div>

                <div class="landing-list">
                    <div class="landing-list-item">Calendario por mes con eventos reales de la organización.</div>
                    <div class="landing-list-item">Alta y edición de eventos con equipo, fechas y ubicación.</div>
                    <div class="landing-list-item">Participación básica con estados invited / accepted / declined.</div>
                    <div class="landing-list-item">Base lista para seguir con vistas de detalle o filtros más avanzados.</div>
                </div>

                <div class="scrim-note-box">
                    El formulario usa la misma lógica de contexto que scrims y equipos, así que el calendario queda integrado con el resto de la app.
                </div>

                <div class="calendar-aside-note">
                    Si quieres, el siguiente paso natural es convertir esta base en una vista de detalle de evento o añadir filtros por tipo y asistencia.
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
