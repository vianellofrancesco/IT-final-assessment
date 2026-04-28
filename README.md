# IT-final-assessment

# Progetto: Server REST PHP - Energy & News Dashboard

Applicazione per il monitoraggio dell'impatto ambientale e delle notizie energetiche globali.

---

## Tecnologie
- **Backend**: PHP REST
- **Frontend**: JavaScript, HTML5, CSS
- **Mappe**: Leaflet.js + GeoJSON
- **API Esterne**: 
  - Electricity Maps: https://electricitymaps.com
  - NewsAPI: https://newsapi.org

---

## Database e Logica (M:N e 1:N)
- Stored Procedures e transactions

---

## Frontend

### Mappa (libreria js Leaflet)
Utilizzo di GeoJSON. Al click si apre una finestra che permette: 
- Recupero dati real-time dall api.
- Visualizzazione notizie energetiche correlate al paese (newsapi).
- Possibilità di aggiungere la zona ai preferiti.

### Zone Salvate
- Elenco delle aree monitorate.
- Visualizzazione delle note personali (nome e descrizione) per ogni zona.
- Pulsante per l'aggiunta di nuove zone.

### Impostazioni
- scelta Light/Dark Mode.
- Customizzazione colori (Palette standard o Color wheel).

### Statistiche 
- permette di fare "analisi" statistiche come ordinare le zone per tipo di energia usata o per co2 nell'aria

---

## Livelli di Accesso

### Utente
- Accesso tramite Login.
- Gestione dei propri dati e zone.

### Admin
Area riservata protetta da password
- Monitoraggio e verifica del database.
- rimozione utenti, notizie o zone.

---

## Schema Tabelle Principali
- **utenti**: Dati anagrafici, credenziali e ruolo.
- **zone_salvate**: Relazione utente-zona con campo aggiuntivo per le note.
- **letture**: Storico dei dati estratti dalle API esterne.
ulteriori tabelle di "servizio" sono previste in fase di sviluppo 
