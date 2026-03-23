<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/campaigns.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: /auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? null) === 'admin') {
    header('Location: /panels/admin-dashboard-panel.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'Adventurer');
$errorMessage = '';
$successMessage = '';
$campaigns = [];

try {
    $pdo = db();
} catch (Throwable $exception) {
    $pdo = null;
    http_response_code(500);
    $errorMessage = 'Unable to load campaigns right now. Please try again later.';
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($pdo === null) {
        $errorMessage = 'Unable to load campaigns right now. Please try again later.';
    } elseif (!csrf_validate_request()) {
        $errorMessage = 'Security token invalid. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        try {
            if ($action === 'enter_campaign') {
                $campaignId = (int)($_POST['campaign_id'] ?? 0);
                if ($campaignId <= 0) {
                    throw new RuntimeException('Choose a campaign before continuing.');
                }

                $campaign = require_campaign_membership($pdo, $userId, $campaignId);
                $rawCharacterId = trim((string)($_POST['character_id'] ?? ''));
                $characterId = $rawCharacterId === '' ? null : (int)$rawCharacterId;

                if ($characterId === null && !$campaign['is_dm']) {
                    throw new RuntimeException('Choose a character before entering this campaign.');
                }

                if ($characterId !== null) {
                    require_campaign_character($pdo, $userId, $campaignId, $characterId);
                }

                apply_campaign_session_scope($campaignId, $characterId, $campaign['is_dm']);
                header('Location: /map.php');
                exit;
            }

            elseif ($action === 'create_character') {
                $campaignId = (int)($_POST['campaign_id'] ?? 0);
                if ($campaignId <= 0) {
                    throw new RuntimeException('Choose a campaign before creating a character.');
                }

                $character = create_campaign_character(
                    $pdo,
                    $userId,
                    $campaignId,
                    (string)($_POST['character_name'] ?? '')
                );

                $successMessage = sprintf(
                    'Created %s for %s.',
                    $character['name'],
                    $character['campaign']['name']
                );
            } else {
                throw new RuntimeException('Unsupported action.');
            }
        } catch (RuntimeException $exception) {
            $errorMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            http_response_code(500);
            $errorMessage = 'Unexpected server error. Please try again.';
        }
    }
}

if ($pdo !== null) {
    $campaigns = fetch_user_campaigns($pdo, $userId);
}
$selectedCampaignId = isset($_SESSION['campaign_id']) ? (int)$_SESSION['campaign_id'] : null;
$selectedCharacterId = isset($_SESSION['character_id']) && $_SESSION['character_id'] !== null
    ? (int)$_SESSION['character_id']
    : null;
$selectedCampaignName = null;
$selectedCharacterName = null;

foreach ($campaigns as $campaign) {
    if ((int)$campaign['id'] !== $selectedCampaignId) {
        continue;
    }

    $selectedCampaignName = (string)$campaign['name'];

    foreach ($campaign['characters'] as $character) {
        if ((int)$character['id'] === $selectedCharacterId) {
            $selectedCharacterName = (string)$character['name'];
            break;
        }
    }

    break;
}

$csrfField = csrf_input();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select Campaign</title>
  <link href="https://fonts.googleapis.com/css2?family=Bilbo&family=Jim+Nightshade&family=Quattrocento&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/select-campaign.css">
</head>
<body>
  <main class="selector-page">
    <section class="selector-shell" aria-labelledby="selector-title">
      <header class="selector-header">
        <h1 id="selector-title">Choose Your Campaign</h1>
        <p class="selector-subtitle">
          Signed in as <strong><?php echo h($username); ?></strong>. Select a campaign scope for this session and choose the character you want to play.
        </p>
        <div class="selector-actions">
          <?php if ($selectedCampaignId !== null): ?>
            <a class="button-link" href="/map.php">Return to Map</a>
          <?php endif; ?>
          <a class="button-link" href="/auth/logout.php">Sign Out</a>
        </div>
      </header>

      <?php if ($errorMessage !== ''): ?>
        <p class="notice notice-error"><?php echo h($errorMessage); ?></p>
      <?php endif; ?>

      <?php if ($successMessage !== ''): ?>
        <p class="notice notice-success"><?php echo h($successMessage); ?></p>
      <?php endif; ?>

      <?php if ($selectedCampaignId !== null): ?>
        <p class="notice notice-info">
          Current session: <?php echo h($selectedCampaignName ?? ('Campaign #' . $selectedCampaignId)); ?><?php if ($selectedCharacterId !== null): ?> as <?php echo h($selectedCharacterName ?? ('Character #' . $selectedCharacterId)); ?><?php endif; ?>.
        </p>
      <?php endif; ?>

      <?php if ($campaigns === []): ?>
        <p class="empty-state">No campaigns are assigned to this account yet. Ask an administrator to add you to a campaign.</p>
      <?php else: ?>
        <div class="campaign-grid">
          <?php foreach ($campaigns as $campaign): ?>
            <?php
            $campaignId = (int)$campaign['id'];
            $characters = $campaign['characters'];
            $isDm = (bool)$campaign['is_dm'];
            $canEnter = $isDm || $characters !== [];
            ?>
            <article class="campaign-card">
              <header>
                <h2><?php echo h($campaign['name']); ?></h2>
                <p class="campaign-meta"><?php echo $isDm ? 'Dungeon Master access' : 'Player access'; ?></p>
              </header>

              <section class="campaign-section" aria-labelledby="characters-<?php echo $campaignId; ?>">
                <h3 id="characters-<?php echo $campaignId; ?>">Characters</h3>
                <?php if ($characters === []): ?>
                  <p>No characters created for this campaign yet.</p>
                <?php else: ?>
                  <ul class="character-list">
                    <?php foreach ($characters as $character): ?>
                      <li>
                        <strong><?php echo h($character['name']); ?></strong>
                        <?php if ($character['description'] !== ''): ?>
                          <span>: <?php echo h($character['description']); ?></span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </section>

              <section class="campaign-section" aria-labelledby="enter-<?php echo $campaignId; ?>">
                <h3 id="enter-<?php echo $campaignId; ?>">Enter Campaign</h3>
                <form method="post" action="/auth/select_campaign.php">
                  <?php echo $csrfField; ?>
                  <input type="hidden" name="action" value="enter_campaign">
                  <input type="hidden" name="campaign_id" value="<?php echo $campaignId; ?>">
                  <div class="field">
                    <label for="character-<?php echo $campaignId; ?>">Character</label>
                    <select id="character-<?php echo $campaignId; ?>" name="character_id">
                      <?php if ($isDm): ?>
                        <option value=""<?php echo $selectedCampaignId === $campaignId && $selectedCharacterId === null ? ' selected' : ''; ?>>No character</option>
                      <?php endif; ?>
                      <?php foreach ($characters as $character): ?>
                        <option
                          value="<?php echo (int)$character['id']; ?>"
                          <?php echo $selectedCampaignId === $campaignId && $selectedCharacterId === (int)$character['id'] ? ' selected' : ''; ?>
                        >
                          <?php echo h($character['name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php if (!$canEnter): ?>
                    <p>Create a character before entering this campaign.</p>
                  <?php endif; ?>
                  <div class="form-actions">
                    <button class="button-primary" type="submit"<?php echo $canEnter ? '' : ' disabled'; ?>>Enter Campaign</button>
                  </div>
                </form>
              </section>

              <section class="campaign-section" aria-labelledby="create-<?php echo $campaignId; ?>">
                <h3 id="create-<?php echo $campaignId; ?>">Create Character</h3>
                <form method="post" action="/auth/select_campaign.php">
                  <?php echo $csrfField; ?>
                  <input type="hidden" name="action" value="create_character">
                  <input type="hidden" name="campaign_id" value="<?php echo $campaignId; ?>">
                  <div class="field">
                    <label for="character-name-<?php echo $campaignId; ?>">Name</label>
                    <input
                      id="character-name-<?php echo $campaignId; ?>"
                      name="character_name"
                      type="text"
                      maxlength="60"
                      autocomplete="off"
                      required
                    >
                  </div>
                  <div class="form-actions">
                    <button class="button-secondary" type="submit">Create Character</button>
                  </div>
                </form>
              </section>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
