<?php
// ─────────────────────────────────────────────────────────────
// api.php  –  BeatVote  –  API REST canzoni
// ─────────────────────────────────────────────────────────────

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Config MongoDB ────────────────────────────────────────────
define('MONGO_URI',  'mongodb://10.10.13.2:27017');
define('DB_NAME',    'playlistDB');
define('COLLECTION', 'songs');

// ── Routing ───────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$id     = $_GET['id']     ?? '';

// Azioni che richiedono login
$protected = ['add','update','delete','upvote','downvote'];
if (in_array($action, $protected) && !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Devi essere loggato']);
    exit;
}

// ── Connessione MongoDB ───────────────────────────────────────
try {
    $manager = new MongoDB\Driver\Manager(MONGO_URI);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'MongoDB non raggiungibile']);
    exit;
}

// ── Body JSON ─────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Username dalla sessione (per azioni protette) ─────────────
$me = $_SESSION['user'] ?? '';

switch ($action) {

    // ── GET list ──────────────────────────────────────────────
    case 'list':
        $songs = runQuery([], ['sort' => ['score' => -1]]);
        echo json_encode($songs, JSON_UNESCAPED_UNICODE);
        break;

    // ── GET search ────────────────────────────────────────────
    case 'search':
        $q = $_GET['q'] ?? '';
        $filter = $q === '' ? [] : ['$or' => [
            ['title'  => ['$regex' => $q, '$options' => 'i']],
            ['artist' => ['$regex' => $q, '$options' => 'i']],
        ]];
        echo json_encode(runQuery($filter, ['sort' => ['score' => -1]]), JSON_UNESCAPED_UNICODE);
        break;

    // ── POST add ──────────────────────────────────────────────
    case 'add':
        $title  = trim($body['title']  ?? '');
        $artist = trim($body['artist'] ?? '');
        $genre  = trim($body['genre']  ?? '');
        if ($title === '' || $artist === '') { err(400, 'Titolo e artista obbligatori'); }

        $doc = [
            'title'     => $title,
            'artist'    => $artist,
            'genre'     => $genre,
            'addedBy'   => $me,
            'upvotes'   => [],
            'downvotes' => [],
            'score'     => 0,
            'comments'  => [],
            'createdAt' => date('c'),
        ];
        $bulk = new MongoDB\Driver\BulkWrite();
        $oid  = $bulk->insert($doc);
        $manager->executeBulkWrite(DB_NAME.'.'.COLLECTION, $bulk);
        $doc['_id'] = (string)$oid;
        http_response_code(201);
        echo json_encode($doc, JSON_UNESCAPED_UNICODE);
        break;

    // ── PUT update ────────────────────────────────────────────
    case 'update':
        $oid  = getOid($id);
        $song = getSong($oid);
        if ($song['addedBy'] !== $me) { err(403, 'Non puoi modificare questa canzone'); }

        $fields = [];
        if (!empty($body['title']))  $fields['title']  = trim($body['title']);
        if (!empty($body['artist'])) $fields['artist'] = trim($body['artist']);
        if (isset($body['genre']))   $fields['genre']  = trim($body['genre']);
        if (empty($fields))          { err(400, 'Nessun campo da aggiornare'); }

        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(['_id' => $oid], ['$set' => $fields]);
        $manager->executeBulkWrite(DB_NAME.'.'.COLLECTION, $bulk);
        echo json_encode(['message' => 'Aggiornato']);
        break;

    // ── DELETE delete ─────────────────────────────────────────
    case 'delete':
        $oid  = getOid($id);
        $song = getSong($oid);
        if ($song['addedBy'] !== $me) { err(403, 'Non puoi eliminare questa canzone'); }

        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete(['_id' => $oid], ['limit' => 1]);
        $manager->executeBulkWrite(DB_NAME.'.'.COLLECTION, $bulk);
        echo json_encode(['message' => 'Eliminata']);
        break;

    // ── POST upvote / downvote ────────────────────────────────
    case 'upvote':
    case 'downvote':
        $oid  = getOid($id);
        $song = getSong($oid);
        $ups  = $song['upvotes']   ?? [];
        $dns  = $song['downvotes'] ?? [];
        $type = $action === 'upvote' ? 'up' : 'down';

        if ($type === 'up') {
            if (in_array($me, $ups, true)) {
                $newUps = array_values(array_filter($ups, fn($u) => $u !== $me));
                $newDns = $dns;
            } else {
                $newDns = array_values(array_filter($dns, fn($u) => $u !== $me));
                $newUps = array_merge($ups, [$me]);
            }
        } else {
            if (in_array($me, $dns, true)) {
                $newDns = array_values(array_filter($dns, fn($u) => $u !== $me));
                $newUps = $ups;
            } else {
                $newUps = array_values(array_filter($ups, fn($u) => $u !== $me));
                $newDns = array_merge($dns, [$me]);
            }
        }

        $newScore = count($newUps) - count($newDns);
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(['_id' => $oid], ['$set' => [
            'upvotes'   => $newUps,
            'downvotes' => $newDns,
            'score'     => $newScore,
        ]]);
        $manager->executeBulkWrite(DB_NAME.'.'.COLLECTION, $bulk);
        echo json_encode(['message' => 'Voto registrato']);
        break;

    default:
        err(404, 'Endpoint non trovato');
}

// ─────────────────────────────────────────────────────────────
// HELPER
// ─────────────────────────────────────────────────────────────
function getOid(string $id): MongoDB\BSON\ObjectId {
    if ($id === '') err(400, 'ID mancante');
    try { return new MongoDB\BSON\ObjectId($id); }
    catch (Exception $e) { err(400, 'ID non valido'); }
}

function getSong(MongoDB\BSON\ObjectId $oid): array {
    $songs = runQuery(['_id' => $oid], []);
    if (empty($songs)) err(404, 'Canzone non trovata');
    return $songs[0];
}

function runQuery(array $filter, array $options): array {
    global $manager;
    $cursor  = $manager->executeQuery(DB_NAME.'.'.COLLECTION, new MongoDB\Driver\Query($filter, $options));
    $results = [];
    foreach ($cursor as $doc) {
        $arr = json_decode(json_encode($doc), true);
        if (isset($arr['_id']['$oid'])) $arr['_id'] = $arr['_id']['$oid'];
        $results[] = $arr;
    }
    return $results;
}

function err(int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
