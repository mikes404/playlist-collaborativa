<?php
// ─────────────────────────────────────────────────────────────
// api.php  –  Backend REST per Playlist Collaborativa
// Tutti gli endpoint sono gestiti da questo file unico.
//
// ENDPOINT:
//   GET    api.php?action=list
//   GET    api.php?action=search&q=...
//   POST   api.php?action=add
//   PUT    api.php?action=update&id=...
//   DELETE api.php?action=delete&id=...
//   POST   api.php?action=upvote&id=...
//   POST   api.php?action=downvote&id=...
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Risponde subito alle richieste OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Configurazione MongoDB ────────────────────────────────────
define('MONGO_URI',  'mongodb://10.10.13.2:27017');
define('DB_NAME',    'playlistDB');
define('COLLECTION', 'songs');

// ── Connessione ───────────────────────────────────────────────
try {
    $manager = new MongoDB\Driver\Manager(MONGO_URI);
} catch (Exception $e) {
    sendError(500, 'Connessione al database fallita: ' . $e->getMessage());
}

// ── Legge il body JSON (per POST/PUT) ────────────────────────
$body = [];
$rawBody = file_get_contents('php://input');
if (!empty($rawBody)) {
    $body = json_decode($rawBody, true) ?? [];
}

// ── Routing ───────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$id     = $_GET['id']     ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {

    // ── GET list ──────────────────────────────────────────────
    case 'list':
        requireMethod('GET');
        $options = ['sort' => ['score' => -1]];
        $songs   = runQuery([], $options);
        echo json_encode($songs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    // ── GET search ────────────────────────────────────────────
    case 'search':
        requireMethod('GET');
        $q = $_GET['q'] ?? '';
        if ($q === '') {
            $filter = [];
        } else {
            $filter = [
                '$or' => [
                    ['title'  => ['$regex' => $q, '$options' => 'i']],
                    ['artist' => ['$regex' => $q, '$options' => 'i']],
                ]
            ];
        }
        $songs = runQuery($filter, ['sort' => ['score' => -1]]);
        echo json_encode($songs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    // ── POST add ──────────────────────────────────────────────
    case 'add':
        requireMethod('POST');
        $title  = trim($body['title']  ?? '');
        $artist = trim($body['artist'] ?? '');
        $genre  = trim($body['genre']  ?? '');

        if ($title === '' || $artist === '') {
            sendError(400, 'title e artist sono obbligatori');
        }

        $newSong = [
            'title'     => $title,
            'artist'    => $artist,
            'genre'     => $genre,
            'upvotes'   => [],
            'downvotes' => [],
            'score'     => 0,
            'createdAt' => date('c'),
        ];

        $bulk = new MongoDB\Driver\BulkWrite();
        $insertedId = $bulk->insert($newSong);
        $manager->executeBulkWrite(DB_NAME . '.' . COLLECTION, $bulk);

        $newSong['_id'] = (string) $insertedId;
        http_response_code(201);
        echo json_encode($newSong, JSON_UNESCAPED_UNICODE);
        break;

    // ── PUT update ────────────────────────────────────────────
    case 'update':
        requireMethod('PUT');
        if ($id === '') sendError(400, 'ID mancante');

        $updateFields = [];
        if (!empty($body['title']))             $updateFields['title']  = trim($body['title']);
        if (!empty($body['artist']))            $updateFields['artist'] = trim($body['artist']);
        if (isset($body['genre']))              $updateFields['genre']  = trim($body['genre']);

        if (empty($updateFields)) sendError(400, 'Nessun campo da aggiornare');

        try {
            $filter = ['_id' => new MongoDB\BSON\ObjectId($id)];
        } catch (Exception $e) {
            sendError(400, 'ID non valido');
        }

        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update($filter, ['$set' => $updateFields]);
        $result = $manager->executeBulkWrite(DB_NAME . '.' . COLLECTION, $bulk);

        if ($result->getMatchedCount() === 0) sendError(404, 'Canzone non trovata');
        echo json_encode(['message' => 'Canzone aggiornata']);
        break;

    // ── DELETE delete ─────────────────────────────────────────
    case 'delete':
        requireMethod('DELETE');
        if ($id === '') sendError(400, 'ID mancante');

        try {
            $filter = ['_id' => new MongoDB\BSON\ObjectId($id)];
        } catch (Exception $e) {
            sendError(400, 'ID non valido');
        }

        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete($filter, ['limit' => 1]);
        $result = $manager->executeBulkWrite(DB_NAME . '.' . COLLECTION, $bulk);

        if ($result->getDeletedCount() === 0) sendError(404, 'Canzone non trovata');
        echo json_encode(['message' => 'Canzone eliminata con successo']);
        break;

    // ── POST upvote ───────────────────────────────────────────
    case 'upvote':
        requireMethod('POST');
        handleVote($id, $body['userId'] ?? 'anonymous', 'up');
        break;

    // ── POST downvote ─────────────────────────────────────────
    case 'downvote':
        requireMethod('POST');
        handleVote($id, $body['userId'] ?? 'anonymous', 'down');
        break;

    default:
        sendError(404, 'Endpoint non trovato. Usa ?action=list|search|add|update|delete|upvote|downvote');
}

// ─────────────────────────────────────────────────────────────
// FUNZIONI HELPER
// ─────────────────────────────────────────────────────────────

/**
 * Gestisce upvote e downvote con logica anti-doppio voto.
 */
function handleVote(string $id, string $userId, string $type): void {
    global $manager;
    if ($id === '') sendError(400, 'ID mancante');

    try {
        $oid = new MongoDB\BSON\ObjectId($id);
    } catch (Exception $e) {
        sendError(400, 'ID non valido');
    }

    // Leggi la canzone attuale
    $songs = runQuery(['_id' => $oid], []);
    if (empty($songs)) sendError(404, 'Canzone non trovata');
    $song = $songs[0];

    $upvotes   = $song['upvotes']   ?? [];
    $downvotes = $song['downvotes'] ?? [];

    if ($type === 'up') {
        if (in_array($userId, $upvotes, true)) {
            sendError(400, 'Hai già messo upvote a questa canzone');
        }
        $hadDownvote = in_array($userId, $downvotes, true);
        $newDownvotes = array_values(array_filter($downvotes, fn($u) => $u !== $userId));
        $newUpvotes   = array_merge($upvotes, [$userId]);
        $scoreChange  = $hadDownvote ? 2 : 1;
    } else {
        if (in_array($userId, $downvotes, true)) {
            sendError(400, 'Hai già messo downvote a questa canzone');
        }
        $hadUpvote  = in_array($userId, $upvotes, true);
        $newUpvotes   = array_values(array_filter($upvotes, fn($u) => $u !== $userId));
        $newDownvotes = array_merge($downvotes, [$userId]);
        $scoreChange  = $hadUpvote ? -2 : -1;
    }

    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->update(
        ['_id' => $oid],
        [
            '$set' => ['upvotes' => $newUpvotes, 'downvotes' => $newDownvotes],
            '$inc' => ['score' => $scoreChange],
        ]
    );
    $manager->executeBulkWrite(DB_NAME . '.' . COLLECTION, $bulk);

    $label = $type === 'up' ? 'Upvote' : 'Downvote';
    echo json_encode(['message' => $label . ' registrato']);
}

/**
 * Esegue una query su MongoDB e restituisce un array di documenti.
 */
function runQuery(array $filter, array $options): array {
    global $manager;
    $query  = new MongoDB\Driver\Query($filter, $options);
    $cursor = $manager->executeQuery(DB_NAME . '.' . COLLECTION, $query);

    $results = [];
    foreach ($cursor as $doc) {
        $arr = json_decode(json_encode($doc), true);
        // Converti ObjectId in stringa leggibile
        if (isset($arr['_id']['$oid'])) {
            $arr['_id'] = $arr['_id']['$oid'];
        }
        $results[] = $arr;
    }
    return $results;
}

/**
 * Verifica che il metodo HTTP sia quello atteso.
 */
function requireMethod(string $expected): void {
    if ($_SERVER['REQUEST_METHOD'] !== $expected) {
        sendError(405, "Metodo non consentito. Usa $expected");
    }
}

/**
 * Invia una risposta di errore JSON e termina lo script.
 */
function sendError(int $code, string $message): never {
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
