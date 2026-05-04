# 🎵 Playlist Collaborativa

Applicazione web collaborativa per gestire una playlist musicale con sistema di voti.  
Progetto PCTO – Classe 5ª – a.s. 2025/26

---

## 📁 Struttura del progetto

```
playlist-collaborativa/
├── backend/
│   └── api.php          ← Tutta la logica API REST in PHP
├── frontend/
│   ├── index.html
│   ├── style.css
│   └── script.js        ← Tutte le chiamate XHR
├── .gitignore
└── README.md
```

---

## 🚀 Deploy sul server della scuola

1. Copia la cartella `frontend/` e il file `backend/api.php` sul server
2. Metti `index.html` e `api.php` nella **stessa cartella**  
   (oppure aggiorna la variabile `API` in `script.js`)
3. Il database MongoDB è già configurato su `10.10.13.2:27017`

---

## 🔌 Endpoint API

| Metodo | URL | Descrizione |
|--------|-----|-------------|
| GET | `api.php?action=list` | Lista tutte le canzoni |
| GET | `api.php?action=search&q=...` | Cerca per titolo o artista |
| POST | `api.php?action=add` | Aggiunge una canzone |
| PUT | `api.php?action=update&id=...` | Modifica una canzone |
| DELETE | `api.php?action=delete&id=...` | Elimina una canzone |
| POST | `api.php?action=upvote&id=...` | Upvote |
| POST | `api.php?action=downvote&id=...` | Downvote |

---

## 👥 Divisione del lavoro

| Ruolo | Compiti |
|-------|---------|
| Backend PHP | `api.php` — endpoint GET, POST, PUT, DELETE |
| Logica voti | upvote/downvote, score, anti-doppio voto |
| Frontend XHR | `index.html`, `style.css`, `script.js` |
| Testing & Docs | Postman, README, relazione PDF, slide |

---

## 🛠️ Tecnologie

- **Backend**: PHP 8+ con driver MongoDB nativo
- **Frontend**: HTML5, CSS3, JavaScript (solo XHR)
- **Database**: MongoDB su server scuola (`10.10.13.2:27017`)
- **Versionamento**: Git + GitHub
