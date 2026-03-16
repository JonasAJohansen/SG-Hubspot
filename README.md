# hs-woo-sync

WordPress-plugin som sender ferdigbetalte WooCommerce-ordrer til HubSpot via REST API.

## Installasjon

1. Kopier `hs-woo-sync/` til `wp-content/plugins/`
2. Aktiver pluginen under **Plugins** i WordPress admin
3. Gå til **WooCommerce → HubSpot Sync** og verifiser at endpoint og token er riktig

Standardverdier settes automatisk ved aktivering (`https://joinevent.no/mock/?route=ingest` og `demo-token-123`).

## Hvordan teste

Du kan verifisere integrasjonen på flere måter for å sikre at både HubSpot og din plugin fungerer som forventet.

### 1. Manuelt i WP-Admin (Klikk)
Gå til **WooCommerce** -> **Ordrer** -> åpne en ordre som er *Processing* -> endre status til **Fullført** -> klikk **Oppdater**. 
Sjekk resultatet umiddelbart under **WooCommerce** -> **HubSpot Sync**.

### 2. Via WP-CLI
For rask testing fra terminalen på serveren:
```bash
wp wc order update 123 --status=completed --user=admin
```
*(Bytt ut 123 med en ekte ordre-ID)*

### 3. Verifiser Mock API-et direkte (HubSpot-enden)
Dette steget bekrefter at mottaker-serveren er klar til å ta imot data.

*   **Sjekk helse (Health):**
    `curl -s "https://joinevent.no/mock/?route=health"` -> Skal returnere OK.

*   **Manuelt test-kall (Ingest):**
    Send en test-pakke manuelt for å se at HubSpot-endepunktet svarer riktig:
    ```bash
    curl -s -X POST "https://joinevent.no/mock/?route=ingest" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer demo-token-123" \
    -d '{"order_id":123,"email":"test@example.com"}'
    ```

---

## Idempotens (Ingen duplikater)
For å sikre at vi ikke sender samme ordre flere ganger: 
Sett en allerede synket ordre tilbake til *Processing* -> sett den til *Fullført* igjen. 
Sjekk loggen: `wp-content/uploads/wc-logs/hs-woos-*.log`. Pluginen vil se at `_hs_synced` allerede er satt og hopper over API-kallet.

## Forenklinger og avveininger

*   **Synkron utførelse:** API-kallet skjer i det ordren lagres. Dette er valgt for enkelhet i denne casen, men kan påvirke ytelsen ved svært mange samtidige kjøp.
*   **Enkel logging:** Feil logges til `wp_options` (siste 10). Dette er lett tilgjengelig, men ikke skalerbart for tusenvis av logger.
*   **Secrets:** Token lagres i databasen som standard for enkel konfigurasjon, men støtter overstyring via `wp-config.php`.
*   **Ingen Retry-løsning:** Hvis API-et er nede, blir ikke ordren sendt på nytt automatisk. Den må trigges manuelt ved statusendring.

## Skalerbarhet (Veien videre)

Hvordan gjøre denne løsningen klar for 100 000+ ordrer?

1.  **Asynkron prosessering:** Flytte API-kallene til en kø-løsning som **WooCommerce Action Scheduler**. Dette gjør at ordrebekreftelsen i admin går lynraskt, mens praten med HubSpot skjer i bakgrunnen.
2.  **Robust feilhåndtering:** Ved bruk av Action Scheduler kan vi implementere "Exponential Backoff" -> automatisk re-forsøk hvis HubSpot er midlertidig nede.
3.  **Batch-sending:** Istedenfor å sende én og én ordre, kan man samle opp ordrer og sende dem i bulker (batches) for å redusere antall forespørsler.
4.  **Egen database-tabell:** Flytte loggføring fra `wp_options` til en egen indeksert tabell. Dette gir bedre ytelse og mulighet for avansert søk og statistikk over synkroniseringer.
5.  **Caching & Pre-fetching:** Cache ofte brukte konfigurasjoner for å minimere database-oppslag under kritiske operasjoner.
6.  **Sikkerhets-herding:** Flytte API-nøkler til miljøvariabler (.env) eller kryptert lagring for å fjerne dem helt fra databasen.

## Struktur

```text
SG-Hubspot/
├── includes/
│   ├── class-api-client.php
│   ├── class-sync-handler.php
│   └── class-admin-log.php
├── hs-woo-sync.php
└── README.md
```
