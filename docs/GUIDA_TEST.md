# Guida super dettagliata - gestpark online

## 1. Cosa contiene questa versione
Questa build è un **MVP funzionante** del plugin **gestpark online** per WordPress.

Funzioni incluse:
- importazione veicoli da endpoint JSON esterno
- mappatura campi API
- supporto ai campi obbligatori richiesti:
  - usato/nuovo
  - anno
  - prezzo
  - alimentazione
  - chilometraggio
  - carrozzeria
  - cambio
  - cilindrata
- campi extra importabili
- elenco veicoli come Custom Post Type
- modifica manuale della scheda veicolo in WordPress
- note interne, note pubbliche, specifiche e accessori
- blocco sovrascrittura per i singoli campi
- gestione vetrina con programmazione da dashboard generale
- layout frontend per scheda veicolo, griglia e carosello
- blocchi Gutenberg
- compatibilità Elementor tramite widget base
- shortcode utilizzabili in qualsiasi editor o builder

## 2. Limiti attuali di questa build
Questa versione è già utilizzabile per test reali, ma non è ancora una release enterprise.

Cose da sapere:
- l’importazione API attesa è in formato JSON
- la sincronizzazione è pensata per endpoint REST che restituiscono array di veicoli
- non include ancora OAuth avanzato
- non include ancora import multiplo da più gestionali contemporaneamente
- non include ancora filtri frontend AJAX avanzati
- i widget Elementor inclusi sono essenziali
- la sincronizzazione automatica è predisposta, ma l’interfaccia per scegliere frequenze multiple non è ancora completa

## 3. Test senza WordPress: cosa puoi fare davvero
Il plugin **non può essere eseguito completamente senza WordPress**, perché usa funzioni native WordPress.

Però puoi fare **tre tipi di test locali senza un sito WordPress già pronto**:

### Test A - Controllo sintassi PHP
Serve per verificare che i file PHP non abbiano errori di sintassi.

Comando:

```bash
find gestpark-online -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Test B - Simulazione endpoint API locale
Dentro `docs/` trovi:
- `sample-vehicles.json`
- `mock-api.php`

Puoi simulare un endpoint API locale con:

```bash
cd gestpark-online/docs
php -S 127.0.0.1:8090
```

Poi l’endpoint da usare sarà:

```text
http://127.0.0.1:8090/mock-api.php
```

### Test C - Revisione struttura file
Puoi verificare che lo zip contenga:
- file principale plugin
- classi admin
- classi sync
- template frontend
- asset CSS/JS
- blocchi Gutenberg
- widget Elementor
- documentazione

## 4. Test completo: qui WordPress è necessario
Per provare davvero il plugin serve un’installazione WordPress.

La strada più semplice è una di queste:
- LocalWP
- XAMPP
- MAMP
- Laragon
- Docker WordPress
- un hosting di staging

## 5. Installazione su WordPress - metodo più semplice

### Opzione consigliata
1. accedi al pannello admin WordPress
2. vai su **Plugin > Aggiungi nuovo plugin**
3. clicca **Carica plugin**
4. seleziona lo zip del plugin
5. clicca **Installa ora**
6. clicca **Attiva**

Dopo l’attivazione vedrai il menu:

```text
gestpark online
```

## 6. Installazione manuale via cartella
Se preferisci installazione manuale:

1. estrai lo zip
2. copia la cartella `gestpark-online` in:

```text
wp-content/plugins/
```

3. entra in **Plugin**
4. attiva **gestpark online**

## 7. Prima configurazione
Dopo l’attivazione fai questi passaggi esattamente in ordine.

### Passo 1 - Apri il pannello API
Vai in:

```text
gestpark online > Connessioni API
```

Compila:
- **Endpoint API**
- **Autenticazione**
- eventuale **Bearer token** oppure **API key**
- **Percorso elementi** se il JSON è annidato
- **Timeout**

### Caso più semplice
Se l’endpoint restituisce direttamente un array JSON come questo:

```json
[
  {
    "id": "CAR-001",
    "brand": "Audi"
  }
]
```

allora:
- Endpoint API = URL endpoint
- Percorso elementi = lascia vuoto

### Caso con JSON annidato
Se l’endpoint restituisce:

```json
{
  "data": {
    "vehicles": [
      {
        "id": "CAR-001"
      }
    ]
  }
}
```

allora:
- Endpoint API = URL endpoint
- Percorso elementi = `data.vehicles`

## 8. Test connessione API
Sempre in **Connessioni API**, clicca:

```text
Test connessione
```

Esito atteso:
- messaggio verde con numero elementi letti

Se fallisce:
- controlla URL
- controlla autenticazione
- controlla formato JSON
- controlla percorso elementi
- controlla che l’endpoint sia raggiungibile dal server WordPress

## 9. Configurazione mappatura campi
Vai in:

```text
gestpark online > Mappatura campi
```

Qui devi collegare i nomi dei campi JSON ai campi del plugin.

### Campi obbligatori
Questi devono essere mappati:
- `condition`
- `year`
- `price`
- `fuel`
- `mileage`
- `body_type`
- `transmission`
- `engine_size`

### Esempio completo con JSON di test incluso
Se usi `sample-vehicles.json`, puoi impostare:
- ID esterno = `id`
- Marca = `brand`
- Modello = `model`
- Versione = `version`
- Descrizione = `description`
- Condizione = `condition`
- Anno = `year`
- Prezzo = `price`
- Alimentazione = `fuel`
- Chilometraggio = `mileage`
- Carrozzeria = `body_type`
- Cambio = `transmission`
- Cilindrata = `engine_size`
- Potenza = `power`
- Colore = `color`
- Targa = `plate`
- URL galleria = `images`

Poi salva.

## 10. Prima sincronizzazione
Vai nella dashboard del plugin e clicca:

```text
Sincronizza adesso
```

Esito atteso:
- il plugin crea i veicoli nel Custom Post Type `Veicoli`
- importa i meta campi
- prova a scaricare le immagini
- salva un log

## 11. Dove trovi i veicoli importati
Vai in:

```text
gestpark online > Veicoli
```

Apri un veicolo e verifica:
- titolo
- contenuto
- anno
- prezzo
- alimentazione
- km
- carrozzeria
- cambio
- cilindrata
- note
- specifiche
- accessori
- vetrina

## 12. Modifica manuale locale di un veicolo
Apri la scheda di un veicolo importato.

Puoi modificare localmente:
- dati principali
- contenuto del post
- note interne
- note pubbliche
- specifiche
- accessori
- stato vetrina
- badge
- date vetrina

### Molto importante
Se vuoi evitare che un campo venga sovrascritto alla sincronizzazione successiva, spunta:

```text
Mantieni questo campo locale e non sovrascriverlo alla prossima sincronizzazione
```

Questo ti permette di usare il gestionale solo come sorgente iniziale e poi rifinire il contenuto sul sito.

## 13. Gestione vetrina da dashboard generale
Vai in:

```text
gestpark online > Vetrina
```

Qui puoi:
- selezionare i veicoli da mettere in vetrina
- definire l’ordine
- impostare data inizio
- impostare data fine
- definire il badge

### Test consigliato
1. spunta 2 o 3 veicoli come in vetrina
2. assegna ordine 1, 2, 3
3. salva
4. crea una pagina frontend con carosello vetrina
5. verifica che compaiano nell’ordine definito

## 14. Inserimento nel sito con Gutenberg
Il plugin registra blocchi server-side per l’editor WordPress.

### Blocchi inclusi
- Gestpark - Griglia veicoli
- Gestpark - Carosello vetrina
- Gestpark - Veicolo in vetrina

### Procedura
1. crea o modifica una pagina
2. apri l’editor blocchi WordPress
3. cerca “Gestpark”
4. inserisci il blocco desiderato
5. aggiorna la pagina
6. visualizza frontend

## 15. Inserimento nel sito con shortcode
Puoi usare gli shortcode in:
- editor classico
- blocco shortcode Gutenberg
- widget testo
- Elementor shortcode widget
- builder compatibili

### Shortcode disponibili

```text
[gestpark_vehicle_grid limit="6"]
[gestpark_featured_vehicle]
[gestpark_featured_carousel]
```

## 16. Uso con Elementor
Il plugin **non dipende da Elementor**, ma se Elementor è attivo registra anche widget base.

### Procedura
1. attiva Elementor
2. modifica una pagina con Elementor
3. cerca “Gestpark”
4. trascina:
   - Griglia veicoli
   - Carosello vetrina

Se non li vedi:
- svuota cache Elementor
- rigenera CSS e dati
- verifica che il plugin sia attivo

## 17. Test della scheda singolo veicolo
Apri la pagina pubblica di un veicolo.

Controlli da fare:
- immagine principale visibile
- gallery visibile
- meta obbligatori visibili
- note pubbliche visibili
- specifiche visibili
- accessori visibili
- stile coerente con i colori del plugin

## 18. Test immagini
Se il tuo endpoint JSON restituisce una lista URL immagini nel campo mappato su `gallery_urls`, il plugin prova a scaricarle nella libreria media WordPress.

### Controlli da fare
- immagine in evidenza creata
- allegati presenti nella Libreria Media
- gallery salvata

Se non funziona:
- verifica che gli URL siano pubblici e diretti
- verifica che il server consenta download remoti
- verifica che PHP possa scrivere in uploads

## 19. Come testare bene un flusso reale
Ti consiglio questo test completo in 12 minuti.

### Scenario suggerito
1. avvia endpoint locale `mock-api.php`
2. installa WordPress locale
3. attiva il plugin
4. imposta endpoint su `http://127.0.0.1:8090/mock-api.php`
5. salva
6. fai test connessione
7. salva la mappatura proposta sopra
8. lancia sync
9. apri l’elenco veicoli
10. modifica un veicolo
11. blocca la sovrascrittura del prezzo
12. riesegui sync e verifica che il prezzo locale resti invariato

## 20. Test di regressione minimo
Quando fai modifiche future al plugin, controlla sempre:
- attivazione plugin senza errori fatali
- apertura dashboard plugin
- test connessione API
- sincronizzazione veicoli
- apertura scheda veicolo
- salvataggio note/accessori
- salvataggio vetrina
- blocchi Gutenberg
- shortcode
- template singolo veicolo
- widget Elementor se Elementor è attivo

## 21. Problemi comuni e soluzioni

### Problema: la sync dice mappatura incompleta
Motivo: manca un campo obbligatorio.

Soluzione:
- vai in **Mappatura campi**
- completa tutti i campi obbligatori

### Problema: nessun veicolo importato
Motivo possibile:
- endpoint errato
- JSON non valido
- `id` esterno mancante

Soluzione:
- controlla endpoint
- controlla `external_id`
- controlla log plugin

### Problema: le immagini non arrivano
Motivo possibile:
- URL non diretto
- hotlink bloccato
- permessi uploads

Soluzione:
- prova URL pubblici diretti JPG/PNG
- controlla log

### Problema: Elementor non mostra i widget
Motivo possibile:
- Elementor non attivo
- cache editor

Soluzione:
- verifica plugin Elementor attivo
- rigenera dati Elementor

## 22. Struttura tecnica utile per il tuo sviluppatore
Le parti principali sono:
- `gestpark-online.php` = bootstrap plugin
- `admin/` = pannello impostazioni e dashboard
- `includes/` = CPT, sync, frontend, blocchi
- `public/templates/` = template singolo veicolo
- `blocks/` = blocchi Gutenberg
- `elementor/` = widget compatibilità Elementor
- `docs/` = documentazione e mock API

## 23. Cosa sviluppare subito nella versione successiva
Appena confermi l’impianto, la v2 dovrebbe aggiungere:
- mapping visuale avanzato
- regole sconto automatiche
- filtri frontend completi
- sync pianificata da interfaccia
- più layout frontend
- webhook in ingresso
- multi-gestionale
- esportazione feed portali
- gestione stato venduto e archiviazione avanzata

## 24. Conclusione pratica
Per testarlo davvero ti basta:
- uno zip del plugin
- WordPress locale
- l’endpoint di test incluso

Il modo più rapido è:
- avvia `mock-api.php`
- installa plugin su WordPress locale
- salva endpoint
- salva mapping
- sincronizza
- prova Gutenberg e shortcode
- poi prova Elementor come compatibilità extra
