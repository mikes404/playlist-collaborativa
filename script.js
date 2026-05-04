// ─────────────────────────────────────────────────────────────
// CONFIGURAZIONE
// Cambia questo percorso se api.php si trova in una cartella diversa
// ─────────────────────────────────────────────────────────────
const API = 'api.php';

// ID temporaneo per simulare l'utente (cambia ad ogni sessione)
const USER_ID = 'user_' + Math.random().toString(36).slice(2, 8);

// ID della canzone in fase di modifica
let editingId = null;

// ─────────────────────────────────────────────────────────────
// UTILITY – XHR generico
// ─────────────────────────────────────────────────────────────

/**
 * Esegue una richiesta XHR.
 * @param {string}      method     - 'GET', 'POST', 'PUT', 'DELETE'
 * @param {string}      url        - URL completo con eventuali query string
 * @param {object|null} body       - Dati da inviare come JSON (o null)
 * @param {function}    onSuccess  - callback(parsedData) per risposte 2xx
 * @param {function}    onError    - callback(errorMsg) per errori
 */
function xhrRequest(method, url, body, onSuccess, onError) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, url);
    xhr.setRequestHeader('Content-Type', 'application/json');

    xhr.onload = function () {
        var data;
        try { data = JSON.parse(xhr.responseText); } catch (e) { data = {}; }

        if (xhr.status >= 200 && xhr.status < 300) {
            onSuccess(data);
        } else {
            onError(data.error || 'Errore HTTP ' + xhr.status);
        }
    };

    xhr.onerror = function () {
        onError('Errore di rete: impossibile contattare il server');
    };

    xhr.send(body ? JSON.stringify(body) : null);
}

// ─────────────────────────────────────────────────────────────
// GET ?action=list  →  Carica tutta la playlist
// ─────────────────────────────────────────────────────────────
function loadPlaylist() {
    xhrRequest(
        'GET',
        API + '?action=list',
        null,
        function (songs) { renderPlaylist(songs); },
        function (err)   { showContainer('<p class="msg err">⚠️ ' + escapeHtml(err) + '</p>'); }
    );
}

function renderPlaylist(songs) {
    var container = document.getElementById('playlist-container');
    if (!songs.length) {
        container.innerHTML = '<p class="loading">Nessuna canzone ancora. Aggiungine una!</p>';
        return;
    }

    var html = '<ul class="song-list">';
    for (var i = 0; i < songs.length; i++) {
        var song = songs[i];
        var scoreClass = song.score < 0 ? 'song-score negative' : 'song-score';
        html += '<li class="song-item">';
        html += '  <div class="song-rank">#' + (i + 1) + '</div>';
        html += '  <div class="song-info">';
        html += '    <div class="song-title">'  + escapeHtml(song.title)  + '</div>';
        html += '    <div class="song-artist">' + escapeHtml(song.artist) + '</div>';
        if (song.genre) {
            html += '  <div class="song-genre">' + escapeHtml(song.genre) + '</div>';
        }
        html += '  </div>';
        html += '  <div class="' + scoreClass + '">⭐ ' + song.score + '</div>';
        html += '  <div class="song-actions">';
        html += '    <button class="btn-up"     onclick="vote(\'' + song._id + '\', \'upvote\')">👍 Upvote</button>';
        html += '    <button class="btn-down"   onclick="vote(\'' + song._id + '\', \'downvote\')">👎 Downvote</button>';
        html += '    <button class="btn-edit"   onclick="startEdit(\'' + song._id + '\', \'' + escapeAttr(song.title) + '\', \'' + escapeAttr(song.artist) + '\', \'' + escapeAttr(song.genre || '') + '\')">✏️ Modifica</button>';
        html += '    <button class="btn-delete" onclick="deleteSong(\'' + song._id + '\')">🗑️ Elimina</button>';
        html += '  </div>';
        html += '</li>';
    }
    html += '</ul>';
    showContainer(html);
}

function showContainer(html) {
    document.getElementById('playlist-container').innerHTML = html;
}

// ─────────────────────────────────────────────────────────────
// POST ?action=add  →  Aggiungi canzone
// ─────────────────────────────────────────────────────────────
function addSong() {
    var title  = document.getElementById('input-title').value.trim();
    var artist = document.getElementById('input-artist').value.trim();
    var genre  = document.getElementById('input-genre').value.trim();
    var msgEl  = document.getElementById('msg-add');

    if (!title || !artist) {
        setMsg(msgEl, '⚠️ Titolo e artista sono obbligatori', 'err');
        return;
    }

    xhrRequest(
        'POST',
        API + '?action=add',
        { title: title, artist: artist, genre: genre },
        function () {
            document.getElementById('input-title').value  = '';
            document.getElementById('input-artist').value = '';
            document.getElementById('input-genre').value  = '';
            setMsg(msgEl, '✅ Canzone aggiunta!', 'ok');
            loadPlaylist();
        },
        function (err) { setMsg(msgEl, '❌ ' + err, 'err'); }
    );
}

// ─────────────────────────────────────────────────────────────
// POST ?action=upvote|downvote&id=...  →  Vota
// ─────────────────────────────────────────────────────────────
function vote(songId, type) {
    xhrRequest(
        'POST',
        API + '?action=' + type + '&id=' + songId,
        { userId: USER_ID },
        function () { loadPlaylist(); },
        function (err) { alert('⚠️ ' + err); }
    );
}

// ─────────────────────────────────────────────────────────────
// PUT ?action=update&id=...  →  Modifica canzone
// ─────────────────────────────────────────────────────────────
function startEdit(id, title, artist, genre) {
    editingId = id;
    document.getElementById('edit-title').value  = title;
    document.getElementById('edit-artist').value = artist;
    document.getElementById('edit-genre').value  = genre;
    document.getElementById('section-edit').style.display = 'block';
    document.getElementById('section-edit').scrollIntoView({ behavior: 'smooth' });
    setMsg(document.getElementById('msg-edit'), '', '');
}

function confirmEdit() {
    if (!editingId) return;
    var title  = document.getElementById('edit-title').value.trim();
    var artist = document.getElementById('edit-artist').value.trim();
    var genre  = document.getElementById('edit-genre').value.trim();
    var msgEl  = document.getElementById('msg-edit');

    if (!title && !artist) {
        setMsg(msgEl, '⚠️ Inserisci almeno titolo o artista', 'err');
        return;
    }

    xhrRequest(
        'PUT',
        API + '?action=update&id=' + editingId,
        { title: title, artist: artist, genre: genre },
        function () {
            setMsg(msgEl, '✅ Modificato!', 'ok');
            setTimeout(cancelEdit, 800);
            loadPlaylist();
        },
        function (err) { setMsg(msgEl, '❌ ' + err, 'err'); }
    );
}

function cancelEdit() {
    editingId = null;
    document.getElementById('section-edit').style.display = 'none';
}

// ─────────────────────────────────────────────────────────────
// DELETE ?action=delete&id=...  →  Elimina canzone
// ─────────────────────────────────────────────────────────────
function deleteSong(songId) {
    if (!confirm('Sei sicuro di voler eliminare questa canzone?')) return;

    xhrRequest(
        'DELETE',
        API + '?action=delete&id=' + songId,
        null,
        function () { loadPlaylist(); },
        function (err) { alert('❌ ' + err); }
    );
}

// ─────────────────────────────────────────────────────────────
// GET ?action=search&q=...  →  Cerca
// ─────────────────────────────────────────────────────────────
function searchSongs() {
    var q = document.getElementById('input-search').value.trim();
    if (!q) { loadPlaylist(); return; }

    xhrRequest(
        'GET',
        API + '?action=search&q=' + encodeURIComponent(q),
        null,
        function (songs) { renderPlaylist(songs); },
        function (err)   { showContainer('<p class="msg err">⚠️ ' + escapeHtml(err) + '</p>'); }
    );
}

// ─────────────────────────────────────────────────────────────
// UTILITY
// ─────────────────────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'");
}

function setMsg(el, text, type) {
    el.textContent = text;
    el.className = 'msg ' + (type || '');
}

// ─────────────────────────────────────────────────────────────
// AVVIO
// ─────────────────────────────────────────────────────────────
loadPlaylist();

// Polling automatico ogni 10 secondi (dimostra XHR periodica)
setInterval(loadPlaylist, 10000);
