# 🎵 Playlist Collaborativa

Applicazione web collaborativa per gestire una playlist musicale con sistema di voti.  
Progetto TPSIT – Classe 5ª DSB– a.s. 2026/27

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

