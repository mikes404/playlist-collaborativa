<?php
// ─────────────────────────────────────────────────────────────
// login.php  –  BeatVote  –  Autenticazione
// ─────────────────────────────────────────────────────────────

session_start();

// ── Config MariaDB ────────────────────────────────────────────
define('MYSQL_HOST',  'localhost');
define('MYSQL_USER',  'dsb5');
define('MYSQL_PASS',  'Domire2@');
define('MYSQL_DB',    'dsb5_friscione');
define('MYSQL_TABLE', 'utentiPlaylist');

// ── Se è una chiamata API (XHR) ───────────────────────────────
$action = $_GET['action'] ?? '';

if ($action !== '') {
    // Manda JSON e nient'altro
    header('Content-Type: application/json; charset=UTF-8');

    // Connessione DB
    $db = @new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB);
    if ($db->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'DB non raggiungibile']);
        exit;
    }
    $db->set_charset('utf8mb4');

    // Body JSON
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {

        case 'me':
            if (isset($_SESSION['user'])) {
                echo json_encode(['username' => $_SESSION['user']]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Non autenticato']);
            }
            break;

        case 'register':
            $u = trim($body['username'] ?? '');
            $p = trim($body['password'] ?? '');
            if ($u === '' || $p === '') { http_response_code(400); echo json_encode(['error' => 'Campi obbligatori']); break; }
            if (strlen($u) < 3)         { http_response_code(400); echo json_encode(['error' => 'Username: minimo 3 caratteri']); break; }
            if (strlen($p) < 6)         { http_response_code(400); echo json_encode(['error' => 'Password: minimo 6 caratteri']); break; }

            $stmt = $db->prepare('SELECT id FROM ' . MYSQL_TABLE . ' WHERE username = ?');
            $stmt->bind_param('s', $u);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) { $stmt->close(); http_response_code(409); echo json_encode(['error' => 'Username già in uso']); break; }
            $stmt->close();

            $hash = password_hash($p, PASSWORD_BCRYPT);
            $stmt = $db->prepare('INSERT INTO ' . MYSQL_TABLE . ' (username, password) VALUES (?, ?)');
            $stmt->bind_param('ss', $u, $hash);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user'] = $u;
            http_response_code(201);
            echo json_encode(['username' => $u]);
            break;

        case 'login':
            $u = trim($body['username'] ?? '');
            $p = trim($body['password'] ?? '');
            if ($u === '' || $p === '') { http_response_code(400); echo json_encode(['error' => 'Campi obbligatori']); break; }

            $stmt = $db->prepare('SELECT password FROM ' . MYSQL_TABLE . ' WHERE username = ?');
            $stmt->bind_param('s', $u);
            $stmt->execute();
            $stmt->bind_result($hash);
            $stmt->fetch();
            $stmt->close();

            if (!$hash || !password_verify($p, $hash)) {
                http_response_code(401);
                echo json_encode(['error' => 'Username o password errati']);
                break;
            }

            $_SESSION['user'] = $u;
            echo json_encode(['username' => $u]);
            break;

        case 'logout':
            $_SESSION = [];
            session_destroy();
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Azione non trovata']);
    }

    $db->close();
    exit;
}

// ── Se già loggato → vai alla app ────────────────────────────
if (isset($_SESSION['user'])) {
    header('Location: index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>🎵 BeatVote – Accedi</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background:#0f0f1a;color:#e0e0e0;
         min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem}
    h1{font-size:2rem;color:#e94560;letter-spacing:2px}
    p.sub{color:#a0a0b0;font-size:.9rem}
    .box{background:#16213e;border:1px solid #1a3a5c;border-radius:14px;padding:2rem;width:100%;max-width:360px}
    .tabs{display:flex;border-bottom:2px solid #1a3a5c;margin-bottom:1.4rem}
    .tab{flex:1;text-align:center;padding:.55rem;cursor:pointer;color:#a0a0b0;font-size:.9rem;
         border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .2s}
    .tab.active{color:#e94560;border-bottom-color:#e94560}
    .section{display:none}.section.active{display:block}
    label{display:block;font-size:.8rem;color:#a0a0b0;margin:.8rem 0 .25rem}
    label:first-of-type{margin-top:0}
    input{width:100%;background:#0f0f1a;border:1px solid #1a3a5c;border-radius:8px;
          padding:.55rem .9rem;color:#e0e0e0;font-size:.9rem;outline:none;transition:border-color .2s}
    input:focus{border-color:#e94560}
    button{width:100%;background:#e94560;color:#fff;border:none;border-radius:8px;
           padding:.65rem;font-size:.95rem;cursor:pointer;margin-top:1.2rem;transition:background .2s}
    button:hover{background:#c73652}
    .msg{margin-top:.7rem;font-size:.82rem;min-height:1.1em;text-align:center}
    .msg.ok{color:#4caf87}.msg.err{color:#e94560}
    footer{color:#404060;font-size:.78rem}
  </style>
</head>
<body>
  <div style="text-align:center">
    <h1>🎵 BeatVote</h1>
    <p class="sub">Accedi per votare e aggiungere canzoni</p>
  </div>

  <div class="box">
    <div class="tabs">
      <div class="tab active" onclick="switchTab('login')">Accedi</div>
      <div class="tab"        onclick="switchTab('reg')">Registrati</div>
    </div>

    <div class="section active" id="s-login">
      <label>Username</label>
      <input id="l-user" type="text" placeholder="Username" autocomplete="username"/>
      <label>Password</label>
      <input id="l-pass" type="password" placeholder="Password" autocomplete="current-password"
             onkeydown="if(event.key==='Enter')doLogin()"/>
      <button onclick="doLogin()">Accedi</button>
      <p class="msg" id="m-login"></p>
    </div>

    <div class="section" id="s-reg">
      <label>Username</label>
      <input id="r-user" type="text" placeholder="Min 3 caratteri"/>
      <label>Password</label>
      <input id="r-pass" type="password" placeholder="Min 6 caratteri"
             onkeydown="if(event.key==='Enter')doRegister()"/>
      <button onclick="doRegister()">Registrati</button>
      <p class="msg" id="m-reg"></p>
    </div>
  </div>

  <footer>BeatVote &nbsp;|&nbsp; Progetto TPSIT 2026-27</footer>

  <script>
    function switchTab(t) {
      document.querySelectorAll('.tab').forEach(function(el,i){
        el.classList.toggle('active',(i===0&&t==='login')||(i===1&&t==='reg'));
      });
      document.getElementById('s-login').classList.toggle('active', t==='login');
      document.getElementById('s-reg').classList.toggle('active',   t==='reg');
    }

    function post(url, data, ok, err) {
      var x = new XMLHttpRequest();
      x.open('POST', url);
      x.setRequestHeader('Content-Type','application/json');
      x.onload = function(){
        var r; try{r=JSON.parse(x.responseText);}catch(e){r={};}
        if(x.status>=200&&x.status<300) ok(r); else err(r.error||'Errore '+x.status);
      };
      x.onerror = function(){ err('Errore di rete'); };
      x.send(JSON.stringify(data));
    }

    function doLogin(){
      var u=document.getElementById('l-user').value.trim();
      var p=document.getElementById('l-pass').value;
      var m=document.getElementById('m-login');
      if(!u||!p){msg(m,'⚠️ Compila tutti i campi','err');return;}
      post('login.php?action=login',{username:u,password:p},
        function(){window.location.href='index.html';},
        function(e){msg(m,'❌ '+e,'err');}
      );
    }

    function doRegister(){
      var u=document.getElementById('r-user').value.trim();
      var p=document.getElementById('r-pass').value;
      var m=document.getElementById('m-reg');
      if(!u||!p){msg(m,'⚠️ Compila tutti i campi','err');return;}
      post('login.php?action=register',{username:u,password:p},
        function(){window.location.href='index.html';},
        function(e){msg(m,'❌ '+e,'err');}
      );
    }

    function msg(el,t,c){el.textContent=t;el.className='msg '+c;}
  </script>
</body>
</html>
