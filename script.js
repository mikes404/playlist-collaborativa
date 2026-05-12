// ─────────────────────────────────────────────────────────────
// script.js  –  BeatVote
// ─────────────────────────────────────────────────────────────

const API  = 'api.php';
const AUTH = 'login.php';

var NICKNAME         = '';
var editingId        = null;
var votingInProgress = false;
var typingInProgress = false;
var currentSearch    = '';

// ── Verifica sessione all'avvio ───────────────────────────────
(function checkSession() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', AUTH + '?action=me');
    xhr.onload = function () {
        if (xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            NICKNAME = data.username;
            document.getElementById('current-user').textContent = '👤 ' + NICKNAME;
            loadPlaylist();
            // Polling avviato UNA VOLTA SOLA dopo il primo caricamento
            setInterval(loadPlaylist, 2000);
        } else {
            window.location.href = 'login.php';
        }
    };
    xhr.onerror = function () { window.location.href = 'login.php'; };
    xhr.send();
})();

// ── XHR generico ─────────────────────────────────────────────
function xhrRequest(method, url, body, onSuccess, onError) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, url);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function () {
        var data;
        try { data = JSON.parse(xhr.responseText); } catch (e) { data = {}; }
        if (xhr.status >= 200 && xhr.status < 300) onSuccess(data);
        else onError(data.error || 'Errore ' + xhr.status);
    };
    xhr.onerror = function () { onError('Errore di rete'); };
    xhr.send(body ? JSON.stringify(body) : null);
}

// ── Logout ────────────────────────────────────────────────────
function logout() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', AUTH + '?action=logout');
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function () { window.location.href = 'login.php'; };
    xhr.onerror = function () { window.location.href = 'login.php'; };
    xhr.send();
}

// ── Carica playlist ───────────────────────────────────────────
function loadPlaylist() {
    if (votingInProgress || typingInProgress) return;
    if (currentSearch !== '') { doSearch(currentSearch); return; }
    xhrRequest('GET', API + '?action=list', null,
        function (songs) { renderPlaylist(songs); },
        function (err)   { showContainer('<p class="msg err">⚠️ ' + escapeHtml(err) + '</p>'); }
    );
}

// ── Render ────────────────────────────────────────────────────
function renderPlaylist(songs) {
    if (!songs.length) {
        showContainer('<p class="loading">Nessuna canzone ancora. Aggiungine una!</p>');
        return;
    }

    var html = '<ul class="song-list">';
    songs.forEach(function (song, i) {
        var isAuthor   = song.addedBy === NICKNAME;
        var iUpvoted   = (song.upvotes   || []).indexOf(NICKNAME) !== -1;
        var iDownvoted = (song.downvotes || []).indexOf(NICKNAME) !== -1;
        var scoreClass = song.score < 0 ? 'song-score negative' : 'song-score';

        html += '<li class="song-item">';
        html += '  <div class="song-rank">#' + (i + 1) + '</div>';
        html += '  <div class="song-info">';
        html += '    <div class="song-title">'  + escapeHtml(song.title)  + '</div>';
        html += '    <div class="song-artist">' + escapeHtml(song.artist) + '</div>';
        if (song.genre) html += '<div class="song-genre">' + escapeHtml(song.genre) + '</div>';
        html += '    <div class="song-author">aggiunto da <strong>' + escapeHtml(song.addedBy || 'anonimo') + '</strong></div>';
        html += '  </div>';
        html += '  <div class="' + scoreClass + '">⭐ ' + song.score + '</div>';
        html += '  <div class="song-actions">';
        html += '    <button class="btn-up'   + (iUpvoted   ? ' active' : '') + '" onclick="vote(\'' + song._id + '\',\'upvote\')">👍</button>';
        html += '    <button class="btn-down' + (iDownvoted ? ' active' : '') + '" onclick="vote(\'' + song._id + '\',\'downvote\')">👎</button>';
        if (isAuthor) {
            html += '<button class="btn-edit"   onclick="startEdit(\'' + song._id + '\',\'' + escapeAttr(song.title) + '\',\'' + escapeAttr(song.artist) + '\',\'' + escapeAttr(song.genre||'') + '\')">✏️</button>';
            html += '<button class="btn-delete" onclick="deleteSong(\'' + song._id + '\')">🗑️</button>';
        }
        html += '  </div>';
        html += '</li>';
    });
    html += '</ul>';
    showContainer(html);
}

function showContainer(html) {
    document.getElementById('playlist-container').innerHTML = html;
}

// ── Aggiungi canzone ──────────────────────────────────────────
function addSong() {
    var title  = document.getElementById('input-title').value.trim();
    var artist = document.getElementById('input-artist').value.trim();
    var genre  = document.getElementById('input-genre').value.trim();
    var msgEl  = document.getElementById('msg-add');
    if (!title || !artist) { setMsg(msgEl, '⚠️ Titolo e artista obbligatori', 'err'); return; }
    xhrRequest('POST', API + '?action=add', { title: title, artist: artist, genre: genre },
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

// ── Voto ──────────────────────────────────────────────────────
function vote(songId, type) {
    votingInProgress = true;
    xhrRequest('POST', API + '?action=' + type + '&id=' + songId, {},
        function () { votingInProgress = false; loadPlaylist(); },
        function (err) { votingInProgress = false; alert('⚠️ ' + err); }
    );
}

// ── Modifica ──────────────────────────────────────────────────
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
    if (!title && !artist) { setMsg(msgEl, '⚠️ Inserisci almeno titolo o artista', 'err'); return; }
    xhrRequest('PUT', API + '?action=update&id=' + editingId, { title: title, artist: artist, genre: genre },
        function () { setMsg(msgEl, '✅ Modificato!', 'ok'); setTimeout(cancelEdit, 800); loadPlaylist(); },
        function (err) { setMsg(msgEl, '❌ ' + err, 'err'); }
    );
}

function cancelEdit() {
    editingId = null;
    document.getElementById('section-edit').style.display = 'none';
}

// ── Elimina canzone ───────────────────────────────────────────
function deleteSong(songId) {
    if (!confirm('Eliminare questa canzone?')) return;
    xhrRequest('DELETE', API + '?action=delete&id=' + songId, null,
        function () { loadPlaylist(); },
        function (err) { alert('❌ ' + err); }
    );
}

// ── Ricerca ───────────────────────────────────────────────────
function searchSongs() {
    var q = document.getElementById('input-search').value.trim();
    currentSearch = q;
    if (!q) { loadPlaylist(); return; }
    doSearch(q);
}

function doSearch(q) {
    xhrRequest('GET', API + '?action=search&q=' + encodeURIComponent(q), null,
        function (songs) { renderPlaylist(songs); },
        function (err)   { showContainer('<p class="msg err">⚠️ ' + escapeHtml(err) + '</p>'); }
    );
}

function resetSearch() {
    currentSearch = '';
    document.getElementById('input-search').value = '';
    loadPlaylist();
}

// ── Utility ───────────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escapeAttr(str) {
    if (!str) return '';
    return String(str).replace(/'/g,"\\'");
}

function setMsg(el, text, type) {
    el.textContent = text;
    el.className = 'msg ' + (type || '');
}
