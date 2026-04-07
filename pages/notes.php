<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/note_functions.php';
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

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$tagFilterId = (int) ($_GET['tag_id'] ?? 0);
$editingNoteId = (int) ($_GET['note_id'] ?? $_POST['note_id'] ?? 0);

if ($activeTeamId !== null) {
    $teamNotes = getTeamNotes($conn, (int) $activeTeamId, $searchQuery, $tagFilterId > 0 ? $tagFilterId : null);
    $teamTags = getTeamNoteTags($conn, (int) $activeTeamId);
} else {
    $teamNotes = [];
    $teamTags = [];
}

$editingNote = false;
if ($activeTeamId !== null && $editingNoteId > 0) {
    $editingNote = getTeamNoteById($conn, $editingNoteId, (int) $activeTeamId);
    if (!$editingNote) {
        $editingNoteId = 0;
    }
}

$formState = [
    'title' => $editingNote['title'] ?? '',
    'content' => $editingNote['content'] ?? '',
    'tags' => $editingNote['tag_list'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['note_action'] ?? 'save_note');
    $postedNoteId = (int) ($_POST['note_id'] ?? 0);

    if (!$activeTeamId) {
        $errors[] = 'Necesitas un equipo activo para gestionar notas';
    } elseif ($action === 'delete_note') {
        if ($postedNoteId > 0 && deleteTeamNote($conn, $postedNoteId, (int) $activeTeamId)) {
            $_SESSION['flash_success'] = 'Nota eliminada';
            header('Location: app.php?view=notes');
            exit;
        }

        $errors[] = 'No se ha podido eliminar la nota';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $tagsInput = trim((string) ($_POST['tags'] ?? ''));
        $noteTags = normalizeNoteTagsInput($tagsInput);

        if ($title === '') {
            $errors[] = 'El título de la nota es obligatorio';
        }

        if ($content === '') {
            $errors[] = 'El contenido de la nota es obligatorio';
        }

        if (empty($errors)) {
            try {
                if ($postedNoteId > 0) {
                    updateTeamNote($conn, $postedNoteId, (int) $activeTeamId, $title, $content, $noteTags);
                    $_SESSION['flash_success'] = 'Nota actualizada';
                } else {
                    createTeamNote($conn, (int) $activeTeamId, $userId, $title, $content, $noteTags);
                    $_SESSION['flash_success'] = 'Nota creada';
                }

                header('Location: app.php?view=notes');
                exit;
            } catch (Throwable $throwable) {
                $errors[] = $throwable->getMessage();
            }
        }
    }

    $formState = [
        'title' => (string) ($_POST['title'] ?? $formState['title']),
        'content' => (string) ($_POST['content'] ?? $formState['content']),
        'tags' => (string) ($_POST['tags'] ?? $formState['tags']),
    ];

    $editingNote = $postedNoteId > 0 ? getTeamNoteById($conn, $postedNoteId, (int) $activeTeamId) : $editingNote;
}

function noteExcerpt(string $content, int $maxLength = 140): string
{
    $cleanContent = trim(preg_replace('/\s+/', ' ', $content) ?? $content);
    if (strlen($cleanContent) <= $maxLength) {
        return $cleanContent;
    }

    return rtrim(substr($cleanContent, 0, $maxLength - 1)) . '…';
}

$pageTitle = 'Notas';
$pageEyebrow = 'Modulo';
$pageDescription = 'Repositorio de notas estratégicas con filtros por etiquetas, edición rápida y contexto por equipo.';
$activeSection = 'notes';
?>

<section class="notes-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Repositorio táctico</div>
            <h2 class="h2">Notas estratégicas</h2>
            <p>Centraliza reviews, estrategias y plantillas del staff en un espacio ligado al equipo activo.</p>
            <div class="stack-sm">
                <span class="badge badge-info">Estrategia</span>
                <span class="badge badge-success">Tags</span>
                <span class="badge badge-warning">Edición rápida</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars((string) ($activeTeam['name'] ?? 'Sin equipo'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Notas visibles</div>
                <div class="dashboard-hero-value"><?php echo count($teamNotes); ?></div>
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

    <?php if ($activeTeamId === null): ?>
        <div class="card dashboard-empty-state">
            No hay un equipo activo para escribir notas. Ve a Equipos y activa uno para usar este repositorio táctico.
        </div>
    <?php else: ?>
        <div class="grid-2">
            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Filtros</div>
                        <h3 class="h3">Biblioteca del equipo</h3>
                    </div>
                    <a class="btn btn-secondary" href="app.php?view=notes">Limpiar filtros</a>
                </div>

                <form class="notes-toolbar" method="get">
                    <input type="hidden" name="view" value="notes" />
                    <div class="field">
                        <label for="note_search">Buscar</label>
                        <input id="note_search" name="q" type="search" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Título, autor o contenido" />
                    </div>

                    <div class="field">
                        <label for="note_tag">Tag</label>
                        <select id="note_tag" name="tag_id">
                            <option value="">Todos</option>
                            <?php foreach ($teamTags as $tag): ?>
                                <option value="<?php echo (int) $tag['id']; ?>" <?php echo $tagFilterId === (int) $tag['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $tag['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $tag['notes_count']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="stack-sm">
                        <button class="btn btn-primary" type="submit">Aplicar</button>
                    </div>
                </form>

                <?php if (empty($teamNotes)): ?>
                    <div class="dashboard-empty-state">
                        No hay notas para el filtro actual. Crea la primera desde el panel derecho.
                    </div>
                <?php else: ?>
                    <div class="notes-grid">
                        <?php foreach ($teamNotes as $note): ?>
                            <?php $noteTagsList = !empty($note['tag_list']) ? explode(',', (string) $note['tag_list']) : []; ?>
                            <article class="card note-card">
                                <div class="dashboard-section-head">
                                    <div>
                                        <div class="small">Actualizada <?php echo htmlspecialchars((string) $note['updated_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <h4 class="h3 note-card-title"><?php echo htmlspecialchars((string) $note['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    </div>
                                    <div class="stack-sm">
                                        <a class="btn btn-secondary" href="app.php?view=notes&amp;note_id=<?php echo (int) $note['id']; ?>">Editar</a>
                                        <form method="post" onsubmit="return confirm('¿Eliminar esta nota?');">
                                            <input type="hidden" name="note_action" value="delete_note" />
                                            <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>" />
                                            <button class="btn btn-secondary" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </div>

                                <p class="note-card-excerpt"><?php echo htmlspecialchars(noteExcerpt((string) $note['content']), ENT_QUOTES, 'UTF-8'); ?></p>

                                <?php if (!empty($noteTagsList)): ?>
                                    <div class="note-tag-list">
                                        <?php foreach ($noteTagsList as $tagName): ?>
                                            <span class="badge badge-info"><?php echo htmlspecialchars(trim($tagName), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="note-card-meta">
                                    <span>Autor: <?php echo htmlspecialchars((string) $note['author_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>Tags: <?php echo (int) $note['tags_count']; ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small"><?php echo $editingNote ? 'Editar nota' : 'Nueva nota'; ?></div>
                        <h3 class="h3"><?php echo $editingNote ? 'Actualizar contenido' : 'Crear nota'; ?></h3>
                    </div>
                    <?php if ($editingNote): ?>
                        <a class="btn btn-secondary" href="app.php?view=notes">Cancelar edición</a>
                    <?php endif; ?>
                </div>

                <form class="form" method="post" novalidate>
                    <input type="hidden" name="note_action" value="save_note" />
                    <input type="hidden" name="note_id" value="<?php echo (int) ($editingNote['id'] ?? 0); ?>" />

                    <div class="field">
                        <label for="note_title">Título</label>
                        <input id="note_title" name="title" type="text" placeholder="Estrategia para Bind" value="<?php echo htmlspecialchars((string) $formState['title'], ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label for="note_tags">Tags</label>
                        <input id="note_tags" name="tags" type="text" placeholder="estrategia, review, plantilla" value="<?php echo htmlspecialchars((string) $formState['tags'], ENT_QUOTES, 'UTF-8'); ?>" />
                        <div class="small">Separa los tags con comas. Se guardan y reutilizan automáticamente.</div>
                    </div>

                    <div class="field">
                        <label for="note_content">Contenido</label>
                        <textarea id="note_content" name="content" rows="10" placeholder="Describe la táctica, el análisis o la plantilla..."><?php echo htmlspecialchars((string) $formState['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="stack-sm">
                        <button class="btn btn-primary" type="submit"><?php echo $editingNote ? 'Guardar cambios' : 'Crear nota'; ?></button>
                        <div class="small">Las notas quedan ligadas al equipo activo y se pueden filtrar por tag.</div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>