<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/board_functions.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeOrganization = $activeOrganizationId ? getOrganizationById($conn, (int) $activeOrganizationId, $userId) : false;

if (!$activeOrganization) {
    $activeOrganization = [
        'name' => 'Sin organización',
        'slug' => 'sin-organizacion',
    ];
}

$activeTeamId = $activeOrganizationId ? getActiveTeamId($conn, (int) $activeOrganizationId) : null;
$activeTeam = $activeTeamId ? getTeamById($conn, (int) $activeTeamId, (int) $activeOrganizationId) : false;
$successMessage = '';
$errors = [];

if (!empty($_SESSION['flash_success'])) {
    $successMessage = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$priorityOptions = [
    'low' => 'Baja',
    'medium' => 'Media',
    'high' => 'Alta',
    'critical' => 'Crítica',
];

if ($activeTeamId !== null) {
    $board = ensureTeamBoard($conn, (int) $activeTeamId);
} else {
    $board = false;
}

$boardColumns = $board ? ensureBoardColumns($conn, (int) $board['id']) : [];
$boardTasks = $board ? getBoardTasks($conn, (int) $board['id']) : [];
$teamMembers = $activeTeamId ? getTeamMembers($conn, (int) $activeTeamId) : [];
$taskByColumn = [];

foreach ($boardTasks as $task) {
    $taskByColumn[(int) $task['board_column_id']][] = $task;
}

$taskId = (int) ($_GET['task_id'] ?? $_POST['task_id'] ?? 0);
$editingTask = false;

if ($board && $taskId > 0) {
    $editingTask = getBoardTaskById($conn, $taskId, (int) $board['id'], (int) $activeTeamId);
    if (!$editingTask) {
        $taskId = 0;
    }
}

$formState = [
    'title' => $editingTask['title'] ?? '',
    'description' => $editingTask['description'] ?? '',
    'priority' => $editingTask['priority'] ?? 'medium',
    'board_column_id' => $editingTask['board_column_id'] ?? ($boardColumns[0]['id'] ?? ''),
    'assigned_to' => $editingTask['assigned_to'] ?? '',
    'due_date' => $editingTask['due_date'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['task_action'] ?? 'save_task');
    $postedTaskId = (int) ($_POST['task_id'] ?? 0);

    if (!$activeTeamId || !$board) {
        $errors[] = 'Necesitas un equipo activo para gestionar tareas';
    } elseif ($action === 'delete_task') {
        if ($postedTaskId > 0 && deleteBoardTask($conn, $postedTaskId, (int) $activeTeamId)) {
            $_SESSION['flash_success'] = 'Tarea eliminada';
            header('Location: app.php?view=boards');
            exit;
        }

        $errors[] = 'No se ha podido eliminar la tarea';
    } elseif ($action === 'move_task') {
        $targetColumnId = (int) ($_POST['target_column_id'] ?? 0);
        if ($postedTaskId > 0 && $targetColumnId > 0) {
            try {
                if (moveBoardTask($conn, $postedTaskId, (int) $activeTeamId, (int) $board['id'], $targetColumnId)) {
                    $_SESSION['flash_success'] = 'Tarea movida de columna';
                    header('Location: app.php?view=boards');
                    exit;
                }
            } catch (Throwable $throwable) {
                $errors[] = $throwable->getMessage();
            }
        } else {
            $errors[] = 'No se ha podido mover la tarea';
        }
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
        $boardColumnId = (int) ($_POST['board_column_id'] ?? 0);
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
        $dueDateInput = trim((string) ($_POST['due_date'] ?? ''));
        $allowedAssignedIds = array_map(static fn (array $member): int => (int) $member['user_id'], $teamMembers);
        $taskFormDate = null;

        if ($dueDateInput !== '') {
            $taskFormDateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dueDateInput);
            if ($taskFormDateTime) {
                $taskFormDate = $taskFormDateTime->format('Y-m-d H:i:s');
            } else {
                $errors[] = 'La fecha límite no tiene un formato válido';
            }
        }

        if ($title === '') {
            $errors[] = 'El título de la tarea es obligatorio';
        }

        if (!array_key_exists($priority, $priorityOptions)) {
            $errors[] = 'Selecciona una prioridad válida';
        }

        if (!getBoardColumnById($conn, $boardColumnId, (int) $board['id'])) {
            $errors[] = 'Selecciona una columna válida';
        }

        if ($assignedTo > 0 && !in_array($assignedTo, $allowedAssignedIds, true)) {
            $errors[] = 'Selecciona un miembro válido del equipo';
        }

        if (empty($errors)) {
            try {
                if ($postedTaskId > 0) {
                    updateBoardTask(
                        $conn,
                        $postedTaskId,
                        (int) $board['id'],
                        (int) $activeTeamId,
                        $boardColumnId,
                        $title,
                        $description !== '' ? $description : null,
                        $priority,
                        $assignedTo > 0 ? $assignedTo : null,
                        $taskFormDate
                    );
                    $_SESSION['flash_success'] = 'Tarea actualizada';
                } else {
                    createBoardTask(
                        $conn,
                        (int) $board['id'],
                        (int) $activeTeamId,
                        $boardColumnId,
                        $title,
                        $description !== '' ? $description : null,
                        $priority,
                        $assignedTo > 0 ? $assignedTo : null,
                        $taskFormDate,
                        $userId
                    );
                    $_SESSION['flash_success'] = 'Tarea creada';
                }

                header('Location: app.php?view=boards');
                exit;
            } catch (Throwable $throwable) {
                $errors[] = $throwable->getMessage();
            }
        }
    }

    $formState = [
        'title' => (string) ($_POST['title'] ?? $formState['title']),
        'description' => (string) ($_POST['description'] ?? $formState['description']),
        'priority' => (string) ($_POST['priority'] ?? $formState['priority']),
        'board_column_id' => (int) ($_POST['board_column_id'] ?? $formState['board_column_id']),
        'assigned_to' => (string) ($_POST['assigned_to'] ?? $formState['assigned_to']),
        'due_date' => (string) ($_POST['due_date'] ?? $formState['due_date']),
    ];

    $editingTask = $postedTaskId > 0 ? getBoardTaskById($conn, $postedTaskId, (int) $board['id'], (int) $activeTeamId) : $editingTask;
}

function boardTaskPriorityBadge(string $priority): string
{
    return match ($priority) {
        'critical' => 'badge-error',
        'high' => 'badge-warning',
        'medium' => 'badge-info',
        default => 'badge-success',
    };
}

function boardTaskPriorityLabel(string $priority): string
{
    return match ($priority) {
        'critical' => 'Crítica',
        'high' => 'Alta',
        'medium' => 'Media',
        default => 'Baja',
    };
}

$pageTitle = 'Tableros';
$pageEyebrow = 'Modulo';
$pageDescription = 'Tablero Kanban por equipo con columnas, tareas, prioridades y controles visuales para mover el trabajo.';
$activeSection = 'boards';
?>

<section class="boards-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Gestión interna</div>
            <h2 class="h2">Tableros Kanban</h2>
            <p>Este módulo organiza el trabajo del roster activo con un board real, tareas editables y movimiento entre columnas.</p>
            <div class="stack-sm">
                <span class="badge badge-info">Pendiente</span>
                <span class="badge badge-warning">En progreso</span>
                <span class="badge badge-success">Completado</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars((string) ($activeTeam['name'] ?? 'Sin equipo'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Tareas visibles</div>
                <div class="dashboard-hero-value"><?php echo count($boardTasks); ?></div>
            </div>
        </div>
    </div>

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

    <?php if ($activeTeamId === null || !$board): ?>
        <div class="card dashboard-empty-state">
            No hay un equipo activo para construir el Kanban. Ve a Equipos y activa uno para empezar a gestionar tareas.
        </div>
    <?php else: ?>
        <div class="grid-2">
            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Board activo</div>
                        <h3 class="h3"><?php echo htmlspecialchars((string) $board['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <a class="btn btn-secondary" href="app.php?view=teams">Cambiar equipo</a>
                </div>

                <div class="landing-list">
                    <div class="landing-list-item">Las tareas se guardan en la base de datos y quedan ligadas al equipo activo.</div>
                    <div class="landing-list-item">Puedes mover una tarea entre columnas con un clic.</div>
                    <div class="landing-list-item">El tablero se crea automáticamente si el equipo no tenía uno.</div>
                </div>

                <div class="kanban">
                    <?php foreach ($boardColumns as $column): ?>
                        <?php $columnTasks = $taskByColumn[(int) $column['id']] ?? []; ?>
                        <section class="kanban-column">
                            <div class="dashboard-section-head">
                                <div>
                                    <div class="small">Columna</div>
                                    <h4 class="kanban-column-title"><?php echo htmlspecialchars((string) $column['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                </div>
                                <span class="badge badge-info"><?php echo count($columnTasks); ?></span>
                            </div>

                            <?php if (empty($columnTasks)): ?>
                                <div class="dashboard-empty-state">Sin tareas en esta columna.</div>
                            <?php else: ?>
                                <?php foreach ($columnTasks as $task): ?>
                                    <?php
                                        $columnIndex = array_search((int) $column['id'], array_map(static fn (array $item): int => (int) $item['id'], $boardColumns), true);
                                        $previousColumn = $columnIndex !== false && $columnIndex > 0 ? $boardColumns[$columnIndex - 1] : null;
                                        $nextColumn = $columnIndex !== false && $columnIndex < count($boardColumns) - 1 ? $boardColumns[$columnIndex + 1] : null;
                                    ?>
                                    <article class="task<?php echo in_array((string) $task['priority'], ['high', 'critical'], true) ? ' task-priority-high' : ''; ?>">
                                        <div class="dashboard-section-head">
                                            <div>
                                                <div class="task-title"><?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="small">Asignada a <?php echo htmlspecialchars((string) $task['assignee_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <span class="badge <?php echo boardTaskPriorityBadge((string) $task['priority']); ?>"><?php echo boardTaskPriorityLabel((string) $task['priority']); ?></span>
                                        </div>

                                        <?php if (!empty($task['description'])): ?>
                                            <p class="task-desc"><?php echo htmlspecialchars((string) $task['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>

                                        <div class="task-meta">
                                            <?php if (!empty($task['due_date_label'])): ?>
                                                <span>Vence <?php echo htmlspecialchars((string) $task['due_date_label'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($task['due_time_label']) ? ' · ' . htmlspecialchars((string) $task['due_time_label'], ENT_QUOTES, 'UTF-8') : ''; ?></span>
                                            <?php else: ?>
                                                <span>Sin fecha límite</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="task-actions">
                                            <a class="btn btn-secondary" href="app.php?view=boards&amp;task_id=<?php echo (int) $task['id']; ?>">Editar</a>

                                            <?php if ($previousColumn): ?>
                                                <form method="post">
                                                    <input type="hidden" name="task_action" value="move_task" />
                                                    <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>" />
                                                    <input type="hidden" name="target_column_id" value="<?php echo (int) $previousColumn['id']; ?>" />
                                                    <button class="btn btn-secondary" type="submit">Mover atrás</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($nextColumn): ?>
                                                <form method="post">
                                                    <input type="hidden" name="task_action" value="move_task" />
                                                    <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>" />
                                                    <input type="hidden" name="target_column_id" value="<?php echo (int) $nextColumn['id']; ?>" />
                                                    <button class="btn btn-primary" type="submit">Mover adelante</button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="post" onsubmit="return confirm('¿Eliminar esta tarea?');">
                                                <input type="hidden" name="task_action" value="delete_task" />
                                                <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>" />
                                                <button class="btn btn-secondary" type="submit">Eliminar</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small"><?php echo $editingTask ? 'Editar tarea' : 'Nueva tarea'; ?></div>
                        <h3 class="h3"><?php echo $editingTask ? 'Actualizar tarjeta' : 'Crear tarjeta'; ?></h3>
                    </div>
                    <?php if ($editingTask): ?>
                        <a class="btn btn-secondary" href="app.php?view=boards">Cancelar edición</a>
                    <?php endif; ?>
                </div>

                <form class="form" method="post" novalidate>
                    <input type="hidden" name="task_action" value="save_task" />
                    <input type="hidden" name="task_id" value="<?php echo (int) ($editingTask['id'] ?? 0); ?>" />

                    <div class="field">
                        <label for="task_title">Título</label>
                        <input id="task_title" name="title" type="text" placeholder="Revisar VOD de la sesión" value="<?php echo htmlspecialchars((string) $formState['title'], ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label for="task_description">Descripción</label>
                        <textarea id="task_description" name="description" placeholder="Contexto, objetivo o siguiente paso..."><?php echo htmlspecialchars((string) $formState['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label for="task_priority">Prioridad</label>
                            <select id="task_priority" name="priority">
                                <?php foreach ($priorityOptions as $priorityKey => $priorityLabel): ?>
                                    <option value="<?php echo htmlspecialchars($priorityKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) $formState['priority'] === $priorityKey ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($priorityLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="task_column">Columna</label>
                            <select id="task_column" name="board_column_id">
                                <?php foreach ($boardColumns as $column): ?>
                                    <option value="<?php echo (int) $column['id']; ?>" <?php echo (int) $formState['board_column_id'] === (int) $column['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) $column['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label for="task_assigned_to">Asignada a</label>
                            <select id="task_assigned_to" name="assigned_to">
                                <option value="">Sin asignar</option>
                                <?php foreach ($teamMembers as $member): ?>
                                    <option value="<?php echo (int) $member['user_id']; ?>" <?php echo (int) $formState['assigned_to'] === (int) $member['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) $member['username'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="task_due_date">Fecha límite</label>
                            <input id="task_due_date" name="due_date" type="datetime-local" value="<?php echo htmlspecialchars((string) (is_string($formState['due_date']) && $formState['due_date'] !== '' ? (new DateTimeImmutable((string) $formState['due_date']))->format('Y-m-d\TH:i') : ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                    </div>

                    <div class="stack-sm">
                        <button class="btn btn-primary" type="submit"><?php echo $editingTask ? 'Guardar cambios' : 'Crear tarea'; ?></button>
                        <div class="small">Las tareas quedan vinculadas al board del equipo activo.</div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>