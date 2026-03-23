<?php
declare(strict_types=1);

function fetch_user_campaigns(PDO $pdo, int $userId): array
{
    $campaignStmt = $pdo->prepare(
        'SELECT c.id, c.name, c.dm_user_id
           FROM campaigns c
           INNER JOIN campaign_users cu ON cu.campaign_id = c.id
          WHERE cu.user_id = :uid
          ORDER BY c.name ASC, c.id ASC'
    );
    $campaignStmt->execute([':uid' => $userId]);
    $campaignRows = $campaignStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $characterStmt = $pdo->prepare(
        'SELECT id, name, description
           FROM characters
          WHERE user_id = :uid
            AND campaign_id = :cid
          ORDER BY name ASC, id ASC'
    );

    $campaigns = [];
    foreach ($campaignRows as $campaignRow) {
        $campaignId = (int)$campaignRow['id'];

        $characterStmt->execute([
            ':uid' => $userId,
            ':cid' => $campaignId,
        ]);

        $characters = [];
        foreach ($characterStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $characterRow) {
            $characters[] = [
                'id' => (int)$characterRow['id'],
                'name' => (string)$characterRow['name'],
                'description' => $characterRow['description'] !== null
                    ? (string)$characterRow['description']
                    : '',
            ];
        }

        $campaigns[] = [
            'id' => $campaignId,
            'name' => (string)$campaignRow['name'],
            'is_dm' => ((int)$campaignRow['dm_user_id'] === $userId),
            'characters' => $characters,
        ];
    }

    return $campaigns;
}

function require_campaign_membership(PDO $pdo, int $userId, int $campaignId): array
{
    $membershipStmt = $pdo->prepare(
        'SELECT c.id, c.name, c.dm_user_id
           FROM campaigns c
           INNER JOIN campaign_users cu
                   ON cu.campaign_id = c.id
                  AND cu.user_id = :uid
          WHERE c.id = :cid
          LIMIT 1'
    );
    $membershipStmt->execute([
        ':uid' => $userId,
        ':cid' => $campaignId,
    ]);

    $campaign = $membershipStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($campaign)) {
        throw new RuntimeException('You are not assigned to that campaign.');
    }

    return [
        'id' => (int)$campaign['id'],
        'name' => (string)$campaign['name'],
        'is_dm' => ((int)$campaign['dm_user_id'] === $userId),
    ];
}

function require_campaign_character(PDO $pdo, int $userId, int $campaignId, int $characterId): array
{
    $characterStmt = $pdo->prepare(
        'SELECT id, name, description
           FROM characters
          WHERE id = :character_id
            AND user_id = :user_id
            AND campaign_id = :campaign_id
          LIMIT 1'
    );
    $characterStmt->execute([
        ':character_id' => $characterId,
        ':user_id' => $userId,
        ':campaign_id' => $campaignId,
    ]);

    $character = $characterStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($character)) {
        throw new RuntimeException('Invalid character selection.');
    }

    return [
        'id' => (int)$character['id'],
        'name' => (string)$character['name'],
        'description' => $character['description'] !== null
            ? (string)$character['description']
            : '',
    ];
}

function validate_character_name(string $rawName): string
{
    $name = trim($rawName);

    if ($name === '' || mb_strlen($name) > 60) {
        throw new RuntimeException('Character names must be between 1 and 60 characters.');
    }

    if (!preg_match('/^[\p{L}\p{N} _\'\-.]{1,60}$/u', $name)) {
        throw new RuntimeException('Character names may contain letters, numbers, spaces, apostrophes, periods, underscores, and hyphens.');
    }

    return $name;
}

function create_campaign_character(PDO $pdo, int $userId, int $campaignId, string $rawName): array
{
    $campaign = require_campaign_membership($pdo, $userId, $campaignId);
    $name = validate_character_name($rawName);

    $pdo->beginTransaction();

    try {
        $duplicateStmt = $pdo->prepare(
            'SELECT id
               FROM characters
              WHERE campaign_id = :campaign_id
                AND user_id = :user_id
                AND name = :name
              LIMIT 1'
        );
        $duplicateStmt->execute([
            ':campaign_id' => $campaignId,
            ':user_id' => $userId,
            ':name' => $name,
        ]);

        if ($duplicateStmt->fetchColumn()) {
            $pdo->rollBack();
            throw new RuntimeException('You already have a character with that name in this campaign.');
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO characters (campaign_id, user_id, name, created_at, updated_at)
             VALUES (:campaign_id, :user_id, :name, NOW(), NOW())'
        );
        $insertStmt->execute([
            ':campaign_id' => $campaignId,
            ':user_id' => $userId,
            ':name' => $name,
        ]);

        $characterId = (int)$pdo->lastInsertId();
        $pdo->commit();

        return [
            'id' => $characterId,
            'name' => $name,
            'campaign' => $campaign,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof RuntimeException) {
            throw $exception;
        }

        throw new RuntimeException('Unable to create character right now.');
    }
}

function apply_campaign_session_scope(int $campaignId, ?int $characterId, bool $isDm): void
{
    $_SESSION['campaign_id'] = $campaignId;
    $_SESSION['character_id'] = $characterId;
    $_SESSION['is_dm'] = $isDm;

    if (($_SESSION['role'] ?? 'user') !== 'admin') {
        $_SESSION['role'] = 'user';
    }

    session_regenerate_id(true);
}
