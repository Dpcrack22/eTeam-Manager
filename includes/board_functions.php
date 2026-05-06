<?php

function getTeamBoard(PDO $conn, int $teamId): array|false
{
    $statement = $conn->prepare(
        'SELECT id, team_id, name, created_at
         FROM boards
         WHERE team_id = :team_id
         ORDER BY created_at ASC, id ASC
         LIMIT 1'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function getBoardById(PDO $conn, int $boardId, int $teamId): array|false
{
    $statement = $conn->prepare(
        'SELECT id, team_id, name, created_at
         FROM boards
         WHERE id = :board_id AND team_id = :team_id
         LIMIT 1'
    );
    $statement->bindValue(':board_id', $boardId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function getBoardColumns(PDO $conn, int $boardId): array
{
    $statement = $conn->prepare(
        'SELECT id, board_id, name, order_index
         FROM board_columns
         WHERE board_id = :board_id
         ORDER BY order_index ASC, id ASC'
    );
    $statement->bindValue(':board_id', $boardId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getBoardColumnById(PDO $conn, int $boardColumnId, int $boardId): array|false
{
    $statement = $conn->prepare(
        'SELECT id, board_id, name, order_index
         FROM board_columns
         WHERE id = :board_column_id AND board_id = :board_id
         LIMIT 1'
    );
    $statement->bindValue(':board_column_id', $boardColumnId, PDO::PARAM_INT);
    $statement->bindValue(':board_id', $boardId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function ensureBoardColumns(PDO $conn, int $boardId): array
{
    $columns = getBoardColumns($conn, $boardId);
    if (!empty($columns)) {
        return $columns;
    }

    $defaultColumns = ['Por hacer', 'En progreso', 'Hecho'];
    $insertStatement = $conn->prepare(
        'INSERT INTO board_columns (board_id, name, order_index)
         VALUES (:board_id, :name, :order_index)'
    );

    foreach ($defaultColumns as $index => $name) {
        $insertStatement->bindValue(':board_id', $boardId, PDO::PARAM_INT);
        $insertStatement->bindValue(':name', $name, PDO::PARAM_STR);
        $insertStatement->bindValue(':order_index', $index + 1, PDO::PARAM_INT);
        $insertStatement->execute();
    }

    return getBoardColumns($conn, $boardId);
}

function ensureTeamBoard(PDO $conn, int $teamId): array|false
{
    $board = getTeamBoard($conn, $teamId);

    if (!$board) {
        $insertStatement = $conn->prepare(
            'INSERT INTO boards (team_id, name, created_at)
             VALUES (:team_id, :name, NOW())'
        );
        $insertStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $insertStatement->bindValue(':name', 'Kanban - Equipo activo', PDO::PARAM_STR);
        $insertStatement->execute();

        $board = getBoardById($conn, (int) $conn->lastInsertId(), $teamId);
    }

    if ($board) {
        ensureBoardColumns($conn, (int) $board['id']);
    }

    return $board;
}

function getBoardTasks(PDO $conn, int $boardId): array
{
    $statement = $conn->prepare(
        'SELECT t.id, t.board_column_id, t.team_id, t.title, t.description, t.priority,
                t.assigned_to, t.due_date, t.status, t.created_by, t.created_at, t.updated_at,
                COALESCE(u.username, "Sin asignar") AS assignee_name,
                COALESCE(u.avatar_url, NULL) AS assignee_avatar_url,
                DATE_FORMAT(t.due_date, "%d/%m/%Y") AS due_date_label,
                DATE_FORMAT(t.due_date, "%H:%i") AS due_time_label,
                bc.name AS column_name,
                bc.order_index AS column_order
         FROM tasks t
         INNER JOIN board_columns bc ON bc.id = t.board_column_id
         LEFT JOIN users u ON u.id = t.assigned_to
         WHERE bc.board_id = :board_id
         ORDER BY bc.order_index ASC, FIELD(t.priority, "critical", "high", "medium", "low") ASC, t.due_date ASC, t.id ASC'
    );
    $statement->bindValue(':board_id', $boardId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getBoardTaskById(PDO $conn, int $taskId, int $boardId, int $teamId): array|false
{
    $statement = $conn->prepare(
        'SELECT t.id, t.board_column_id, t.team_id, t.title, t.description, t.priority,
                t.assigned_to, t.due_date, t.status, t.created_by, t.created_at, t.updated_at,
                bc.board_id
         FROM tasks t
         INNER JOIN board_columns bc ON bc.id = t.board_column_id
         WHERE t.id = :task_id AND bc.board_id = :board_id AND t.team_id = :team_id
         LIMIT 1'
    );
    $statement->bindValue(':task_id', $taskId, PDO::PARAM_INT);
    $statement->bindValue(':board_id', $boardId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function normalizeBoardTaskStatus(string $columnName): string
{
    $normalized = strtolower(trim($columnName));

    return match ($normalized) {
        'por hacer' => 'Por hacer',
        'en progreso' => 'En progreso',
        'hecho' => 'Hecho',
        default => ucfirst($normalized),
    };
}

function createBoardTask(
    PDO $conn,
    int $boardId,
    int $teamId,
    int $boardColumnId,
    string $title,
    ?string $description,
    string $priority,
    ?int $assignedTo,
    ?string $dueDate,
    int $createdBy
): int {
    $column = getBoardColumnById($conn, $boardColumnId, $boardId);
    if (!$column) {
        throw new RuntimeException('La columna seleccionada no es válida');
    }

    $statement = $conn->prepare(
        'INSERT INTO tasks (
            board_column_id, team_id, title, description, priority, assigned_to, due_date, status, created_by, created_at, updated_at
         ) VALUES (
            :board_column_id, :team_id, :title, :description, :priority, :assigned_to, :due_date, :status, :created_by, NOW(), NOW()
         )'
    );
    $statement->bindValue(':board_column_id', $boardColumnId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->bindValue(':title', $title, PDO::PARAM_STR);
    $statement->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':priority', $priority, PDO::PARAM_STR);
    $statement->bindValue(':assigned_to', $assignedTo, $assignedTo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $statement->bindValue(':due_date', $dueDate, $dueDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':status', normalizeBoardTaskStatus((string) $column['name']), PDO::PARAM_STR);
    $statement->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    $statement->execute();

    return (int) $conn->lastInsertId();
}

function updateBoardTask(
    PDO $conn,
    int $taskId,
    int $boardId,
    int $teamId,
    int $boardColumnId,
    string $title,
    ?string $description,
    string $priority,
    ?int $assignedTo,
    ?string $dueDate
): bool {
    $column = getBoardColumnById($conn, $boardColumnId, $boardId);
    if (!$column) {
        throw new RuntimeException('La columna seleccionada no es válida');
    }

    $statement = $conn->prepare(
        'UPDATE tasks
         SET board_column_id = :board_column_id,
             title = :title,
             description = :description,
             priority = :priority,
             assigned_to = :assigned_to,
             due_date = :due_date,
             status = :status,
             updated_at = NOW()
         WHERE id = :task_id AND team_id = :team_id'
    );
    $statement->bindValue(':board_column_id', $boardColumnId, PDO::PARAM_INT);
    $statement->bindValue(':title', $title, PDO::PARAM_STR);
    $statement->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':priority', $priority, PDO::PARAM_STR);
    $statement->bindValue(':assigned_to', $assignedTo, $assignedTo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $statement->bindValue(':due_date', $dueDate, $dueDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':status', normalizeBoardTaskStatus((string) $column['name']), PDO::PARAM_STR);
    $statement->bindValue(':task_id', $taskId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);

    return $statement->execute();
}

function moveBoardTask(PDO $conn, int $taskId, int $teamId, int $boardId, int $targetColumnId): bool
{
    $column = getBoardColumnById($conn, $targetColumnId, $boardId);
    if (!$column) {
        throw new RuntimeException('La columna destino no es válida');
    }

    $statement = $conn->prepare(
        'UPDATE tasks
         SET board_column_id = :board_column_id,
             status = :status,
             updated_at = NOW()
         WHERE id = :task_id AND team_id = :team_id'
    );
    $statement->bindValue(':board_column_id', $targetColumnId, PDO::PARAM_INT);
    $statement->bindValue(':status', normalizeBoardTaskStatus((string) $column['name']), PDO::PARAM_STR);
    $statement->bindValue(':task_id', $taskId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);

    return $statement->execute();
}

function deleteBoardTask(PDO $conn, int $taskId, int $teamId): bool
{
    $statement = $conn->prepare(
        'DELETE FROM tasks WHERE id = :task_id AND team_id = :team_id'
    );
    $statement->bindValue(':task_id', $taskId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);

    return $statement->execute();
}