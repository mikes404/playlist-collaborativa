# 🎵 BeatVote

Playlist collaborativa con sistema di voto in tempo reale.  
Progetto TPSIT – Classe 5ª DSB – A.S. 2026/27

---

## 📁 Struttura del progetto

```
playlist-collaborativa/
├── index.html         ← Pagina principale dell'app
├── style.css          ← Stili
├── script.js          ← Logica frontend (solo XHR)
├── api.php            ← API REST canzoni (MongoDB)
├── login.php          ← Autenticazione (MariaDB) + pagina login
├── .gitignore
└── README.md
```

---

## 🔌 Endpoint API

### Canzoni (`api.php`)

| Metodo | URL | Descrizione | Auth |
|--------|-----|-------------|------|
| GET | `api.php?action=list` | Lista canzoni per score | No |
| GET | `api.php?action=search&q=...` | Ricerca per titolo/artista | No |
| POST | `api.php?action=add` | Aggiunge una canzone | Sì |
| PUT | `api.php?action=update&id=...` | Modifica una canzone | Sì* |
| DELETE | `api.php?action=delete&id=...` | Elimina una canzone | Sì* |
| POST | `api.php?action=upvote&id=...` | Toggle upvote | Sì |
| POST | `api.php?action=downvote&id=...` | Toggle downvote | Sì |

### Autenticazione (`login.php`)

| Metodo | URL | Descrizione |
|--------|-----|-------------|
| GET | `login.php?action=me` | Utente loggato corrente |
| POST | `login.php?action=register` | Registrazione |
| POST | `login.php?action=login` | Login |
| POST | `login.php?action=logout` | Logout |

*Solo l'autore originale può modificare/eliminare i propri contenuti.
