INSTALLAZIONE IPAM SU HOSTING WEB
=====================================

REQUISITI SISTEMA:
- PHP 8.0 o superiore
- Apache/Nginx con mod_rewrite
- Permessi di scrittura per creare cartelle
- Almeno 50MB di spazio disco

INSTALLAZIONE:
1. Carica tutti i file nella cartella /ipadmin/ del tuo hosting
2. Assicurati che il web server abbia permessi di scrittura sulla cartella
3. L'applicazione creerà automaticamente la cartella "data" al primo accesso

CONFIGURAZIONE WEB SERVER:
- Apache: Il file .htaccess è già incluso
- Nginx: Aggiungi questa configurazione nel tuo virtual host:

    location /ipadmin/ {
        try_files $uri $uri/ /ipadmin/index.php?$args;
    }

ACCESSO:
- URL: https://url_web/ipadmin/
- Username: admin
- Password: admin

STRUTTURA CARTELLE:
/ipadmin/
├── index.php          (File principale)
├── .htaccess          (Configurazione Apache)
├── lib/               (Librerie PHP)
│   ├── auth.php       (Sistema di autenticazione) ATTENZIONE: Cambiare dati di accesso una volta installato
│   ├── ipam.php       (Logica di gestione IP)
│   └── storage.php    (Sistema di archiviazione)
├── public/            (File statici)
│   ├── app.js         (JavaScript dell'applicazione)
│   └── style.css      (Fogli di stile)
└── data/              (Creata automaticamente)
    ├── subnets.json   (Database subnet)
    └── ips/           (Database IP)

SICUREZZA:
- I file JSON sono protetti dall'accesso web
- Sistema di autenticazione integrato
- Token CSRF per le operazioni
- Rate limiting sui login

BACKUP:
Fai backup della cartella "data" per preservare tutti i dati delle subnet e IP.

SUPPORTO:
L'applicazione è completamente auto-contenuta e non richiede database esterni.
