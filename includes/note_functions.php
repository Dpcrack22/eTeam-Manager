<?php

function normalizeNoteTagsInput(string $rawTags): array
{
    $tags = preg_split('/[;,\n]+/', $rawTags) ?: [];
    $normalizedTags = [];

    foreach ($tags as $tag) {
        $tag = trim(strtolower($tag));
        if ($tag === '') {
            continue;
        }

        if (!in_array($tag, $normalizedTags, true)) {
            $normalizedTags[] = $tag;
        }
    }

    return array_slice($normalizedTags, 0, 8);
}

function getNoteTagByName(PDO $conn, string $name): array|false
{
    $statement = $conn->prepare(
        'SELECT id, name FROM note_tags WHERE name = :name LIMIT 1'
    );
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function ensureNoteTag(PDO $conn, string $name): int
{
    $normalizedName = trim(strtolower($name));
    if ($normalizedName === '') {
        throw new InvalidArgumentException('El tag no puede estar vacío');
    }

    $tag = getNoteTagByName($conn, $normalizedName);
    if ($tag) {
        return (int) $tag['id'];
    }

    $statement = $conn->prepare(
        'INSERT INTO note_tags (name) VALUES (:name)'
    );
    $statement->bindValue(':name', $normalizedName, PDO::PARAM_STR);
    $statement->execute();

    return (int) $conn->lastInsertId();
}

function syncNoteTags(PDO $conn, int $noteId, array $tagNames): void
{
    $normalizedTagNames = [];

    foreach ($tagNames as $tagName) {
        $tagName = trim(strtolower((string) $tagName));
        if ($tagName === '') {
            continue;
        }

        if (!in_array($tagName, $normalizedTagNames, true)) {
            $normalizedTagNames[] = $tagName;
        }
    }

    $conn->beginTransaction();

    try {
        $deleteStatement = $conn->prepare('DELETE FROM note_tag_relations WHERE note_id = :note_id');
        $deleteStatement->bindValue(':note_id', $noteId, PDO::PARAM_INT);
        $deleteStatement->execute();

        if (!empty($normalizedTagNames)) {
            $insertRelation = $conn->prepare(
                'INSERT INTO note_tag_relations (note_id, tag_id) VALUES (:note_id, :tag_id)'
            );

            foreach ($normalizedTagNames as $tagName) {
                $tagId = ensureNoteTag($conn, $tagName);
                $insertRelation->bindValue(':note_id', $noteId, PDO::PARAM_INT);
                $insertRelation->bindValue(':tag_id', $tagId, PDO::PARAM_INT);
                $insertRelation->execute();
            }
        }

        $conn->commit();
    } catch (Throwable $throwable) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        throw $throwable;
    }
}

function getTeamNoteTags(PDO $conn, int $teamId): array
{
    $statement = $conn->prepare(
        'SELECT nt.id, nt.name, COUNT(DISTINCT n.id) AS notes_count
         FROM note_tags nt
         INNER JOIN note_tag_relations ntr ON ntr.tag_id = nt.id
         INNER JOIN notes n ON n.id = ntr.note_id
         WHERE n.team_id = :team_id
         GROUP BY nt.id, nt.name
         ORDER BY nt.name ASC'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getTeamNotes(PDO $conn, int $teamId, string $searchQuery = '', ?int $tagId = null): array
{
    $sql =
        'SELECT n.id, n.team_id, n.title, n.content, n.created_by, n.created_at, n.updated_at,
                u.username AS author_name,
                GROUP_CONCAT(DISTINCT nt.name ORDER BY nt.name SEPARATOR ",") AS tag_list,
                COUNT(DISTINCT ntr.tag_id) AS tags_count
         FROM notes n
         INNER JOIN users u ON u.id = n.created_by
         LEFT JOIN note_tag_relations ntr ON ntr.note_id = n.id
         LEFT JOIN note_tags nt ON nt.id = ntr.tag_id
         WHERE n.team_id = :team_id';

    if ($searchQuery !== '') {
        $sql .= ' AND (n.title LIKE :search_query OR n.content LIKE :search_query OR u.username LIKE :search_query)';
    }

    if ($tagId !== null) {
        $sql .= ' AND EXISTS (
            SELECT 1
            FROM note_tag_relations filter_rel
            WHERE filter_rel.note_id = n.id AND filter_rel.tag_id = :tag_id
        )';
    }

    $sql .= ' GROUP BY n.id, n.team_id, n.title, n.content, n.created_by, n.created_at, n.updated_at, u.username
              ORDER BY n.updated_at DESC, n.created_at DESC, n.id DESC';

    $statement = $conn->prepare($sql);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);

    if ($searchQuery !== '') {
        $statement->bindValue(':search_query', '%' . $searchQuery . '%', PDO::PARAM_STR);
    }

    if ($tagId !== null) {
        $statement->bindValue(':tag_id', $tagId, PDO::PARAM_INT);
    }

    $statement->execute();

    return $statement->fetchAll();
}

function getTeamNoteById(PDO $conn, int $noteId, int $teamId): array|false
{
    $statement = $conn->prepare(
        'SELECT n.id, n.team_id, n.title, n.content, n.created_by, n.created_at, n.updated_at,
                u.username AS author_name,
                GROUP_CONCAT(DISTINCT nt.name ORDER BY nt.name SEPARATOR ",") AS tag_list
         FROM notes n
         INNER JOIN users u ON u.id = n.created_by
         LEFT JOIN note_tag_relations ntr ON ntr.note_id = n.id
         LEFT JOIN note_tags nt ON nt.id = ntr.tag_id
         WHERE n.id = :note_id AND n.team_id = :team_id
         GROUP BY n.id, n.team_id, n.title, n.content, n.created_by, n.created_at, n.updated_at, u.username
         LIMIT 1'
    );
    $statement->bindValue(':note_id', $noteId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function createTeamNote(PDO $conn, int $teamId, int $createdBy, string $title, string $content, array $tagNames): int
{
    $statement = $conn->prepare(
        'INSERT INTO notes (team_id, title, content, created_by, created_at, updated_at)
         VALUES (:team_id, :title, :content, :created_by, NOW(), NOW())'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->bindValue(':title', $title, PDO::PARAM_STR);
    $statement->bindValue(':content', $content, PDO::PARAM_STR);
    $statement->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    $statement->execute();

    $noteId = (int) $conn->lastInsertId();
    syncNoteTags($conn, $noteId, $tagNames);

    return $noteId;
}

function updateTeamNote(PDO $conn, int $noteId, int $teamId, string $title, string $content, array $tagNames): bool
{
    $statement = $conn->prepare(
        'UPDATE notes
         SET title = :title,
             content = :content,
             updated_at = NOW()
         WHERE id = :note_id AND team_id = :team_id'
    );
    $statement->bindValue(':title', $title, PDO::PARAM_STR);
    $statement->bindValue(':content', $content, PDO::PARAM_STR);
    $statement->bindValue(':note_id', $noteId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);

    $updated = $statement->execute();
    if ($updated) {
        syncNoteTags($conn, $noteId, $tagNames);
    }

    return $updated;
}

function deleteTeamNote(PDO $conn, int $noteId, int $teamId): bool
{
    $statement = $conn->prepare(
        'DELETE FROM notes WHERE id = :note_id AND team_id = :team_id'
    );
    $statement->bindValue(':note_id', $noteId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);

    return $statement->execute();
}