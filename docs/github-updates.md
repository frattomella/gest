# Aggiornamenti GitHub per GestPark Online

Questo plugin puo aggiornarsi da GitHub senza ricreare e caricare manualmente la zip a ogni release.

## 1. Repository GitHub

- Crea oppure usa un repository GitHub dedicato al plugin.
- Il repository deve avere come root direttamente la cartella del plugin `gestpark-online`.
- Salva nel plugin il valore `owner/repository` oppure l URL completo del repository nella pagina `GestPark Online > Aggiornamenti`.

## 2. Configurazione nel plugin

Compila questi campi in `GestPark Online > Aggiornamenti`:

- `Abilita aggiornamenti GitHub`
- `Repository GitHub`
- `Branch principale`
- `Nome file zip release`
- `Token GitHub opzionale` solo se il repository e privato

Il nome asset consigliato e `gestpark-online.zip`.

## 3. Workflow automatico GitHub Actions

Il file `.github/workflows/release-plugin.yml` e gia incluso nel plugin.

Funziona cosi:

- quando fai push di un tag come `v0.2.5`
- GitHub Actions crea automaticamente `gestpark-online.zip`
- la release GitHub riceve quell asset
- WordPress lo usa come pacchetto di aggiornamento

## 4. Flusso release consigliato

1. Aggiorna `Version` e `GPO_VERSION` nel plugin.
2. Fai commit delle modifiche.
3. Crea un tag Git, ad esempio `v0.2.5`.
4. Fai push di branch e tag su GitHub.
5. Attendi la release automatica.
6. In WordPress usa `GestPark Online > Aggiornamenti > Forza controllo aggiornamenti`.

## 5. Sviluppo locale senza zip

Per lavorare in locale non serve usare la zip.

La soluzione migliore su Windows e una junction verso `wp-content/plugins`:

```powershell
mklink /J C:\xampp\htdocs\tuosito\wp-content\plugins\gestpark-online E:\Download\gestpark-online
```

In questo modo:

- modifichi i file nel repository
- WordPress legge subito le modifiche
- GitHub rimane il canale di release e aggiornamento

## 6. Repository privato

Se il repository e privato:

- genera un token GitHub con accesso in lettura ai contenuti del repository
- inseriscilo nel campo `Token GitHub opzionale`

## 7. Nota importante

WordPress propone l aggiornamento solo quando la release GitHub ha una versione piu alta di quella installata nel plugin.

Esempio:

- plugin installato `0.2.5`
- nuova release GitHub `v0.2.6`
- WordPress mostra l aggiornamento
