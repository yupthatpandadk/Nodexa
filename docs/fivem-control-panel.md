# Nodexa FiveM Control

FiveM-servere får automatisk et separat, mørkt operationsdashboard under **Server → FiveM Overview**. Almindelige Minecraft-servere, Rust og andre spil beholder det eksisterende Pterodactyl-panel.

## Valg af panel

Backend sender `is_fivem_server` fra serverens Pterodactyl Nest. Der er intet kundestyret skift af servertype. Nodexa accepterer navnene `FiveM`, `Five M`, `FXServer` og `GTA V` (normaliseret), ikke Minecraft Egg- eller produktnavnet.

- Serverens FiveM Overview er en selvstændig React-komponent, ikke blot nye farver.
- Overview indeholder Live Console, Power-knapper, CPU/RAM/disk-grænser, netværks-/ressourcegrafer, genveje til filer, backups, startup, databaser og schedules.
- En særskilt **Resources**-fane viser mappestruktur fra Pterodactyls reelle `/resources` via den eksisterende, adgangsstyrede fil-API.
- Antallet i Ressources er **antal mapper**, ikke en falsk statistik for aktive scripts eller tilsluttede spillere.
- Resource-listen er read-only og åbner de korrekte mapper i File Manager. Start/stop af enkelte scripts overlades til FiveM/txAdmin, da der ikke er et autoriseret resource-status API integreret endnu.
- txAdmin, ESX og FiveM får ikke stiltiende adgang til yderligere handlinger. De eksisterende Pterodactyl permissions gælder fortsat.
- FiveM har stadig adgang til alle oprindelige Console/Files/Databases/Schedules/Users/Backups/Network/Startup/Settings/Activity-funktioner efter behov.

## Frontend i hostingdelen

Frontend Theme Center styrer **kun den offentlige hjemmeside**. Det er uafhængigt af FiveM Control, så ændring fra Latch til Xlumia osv. ændrer ikke kundens serverpanel.

## Testplan

1. Åbn server i FiveM Nest og kontrollér separat layout + Resources-nav.
2. Kontrollér Start/Stop/Restart fra serverens Console og korrekt realtidsstatus.
3. Kontrollér Resources-visningen på en server med og uden `/resources`-mappe.
4. Test en FiveM-medbruger med begrænsede tilladelser: utilgængelige genveje og faner må ikke give ekstra rettigheder.
5. Kontrollér Minecraft Paper/Fabric og Rust – de skal fortsat bruge deres eksisterende kontrolpanel.
6. Byg panelet med `yarn build:production` eller Nodexas installationsscript og kontrollér TypeScript- og Webpack-fejl.

Dette er kodeændringer i GitHub, ikke en påstand om en gennemført test på din server.


## txAdmin – officiel FXServer-integration

Nodexa indeholder nu en separat **Server → txAdmin**-fane for FiveM-servers, samt genvej fra FiveM Overview. txAdmin er **inkluderet i FXServer**, ikke et script der uploades til \`resources\`.

Nodexa viser:
- Om \`TXADMIN_ENABLED\` er slået til (\`1\` / \`0\`), hvis Egg'et udstiller indstillingen.
- Den forventede webport fra \`TXHOST_TXA_PORT\`, ældre portvariabel eller standarden \`40120\`.
- Om den præcise TCP-port faktisk er tildelt **denne** Pterodactyl-server.
- Den sikre, bestemte adresse i knappen **Åbn txAdmin**, når porten er tildelt og funktionen ikke er deaktiveret.
- Fejlbeskeder og genveje til Startup, Network og Console.

### Første opsætning (FiveM + Pterodactyl)
1. Serveradministrator tildeler en ekstra TCP-allocation til webpanelet, fx \`40120\` (og bevarer separat spilport).
2. I **Startup** aktiveres \`TXADMIN_ENABLED=1\`, hvis den installerede FiveM Egg understøtter dette.
3. Hvis Egg'et understøtter \`TXHOST_TXA_PORT\`, skal værdien matche den tildelte TCP-allocation. Ved andre eller nyoprettede Eggs skal installationskommandoen verificeres.
4. txAdmin monitor startes via FXServer **uden** \`+exec server.cfg\`. Start ikke en ny txAdmin recipe på en eksisterende server uden backup af \`txData\`, server.cfg og resources.
5. Start serveren. Brug txAdmins URL og førstegangs-PIN fra console. Opret login i txAdmin, og tildel egne txAdmin-rettigheder.

txAdmin-fanen åbner den **rigtige txAdmin-webapp i en ny fane**, hvor login, spillerstyring, resource-styring, bans, restart-schedule og øvrige funktioner administreres. Nodexa forsøger ikke at kopiere txAdmins adgangsrettigheder, fange sessionscookies eller indlejre siden i en iframe.

En tildelt port er **ikke** det samme som en kørende txAdmin-service. Status er derfor \`Adresse konfigureret – drift ikke verificeret\` indtil faktiske driftsdata kan verificeres. Hvis webpanelet ikke kan åbnes, skal administrator kontrollere portmapping, firewall, korrekt FXServer startup og eventuel HTTPS reverse proxy.

#### Link-sikkerhed
- API'et udleder kun et link, hvis den konfigurerede port er tildelt den konkrete server.
- Arbitrære eksterne URL'er fra Egg-startup ignoreres. En HTTPS \`TXHOST_TXA_URL\` accepteres kun med et hostname, der matcher serverens tildelte adresse eller node-adresse.
- TxAdmin-URL vises kun for brugere med Pterodactyl-rettigheden \`startup.read\`, og txAdmin har derefter sit eget login.
- TXHOST_API_TOKEN, txAdmin-password, Cfx.re-adgangskoder og cookies kopieres aldrig til Nodexa-klienten.
- En HTTP-port på internettet er ikke optimal; brug en adgangsbegrænset HTTPS reverse proxy hvor det er muligt.

**Driftstilstand og player bans er fortsat txAdmins ansvar**. Nodexa udgiver ikke udokumenterede administrative endpoints, og den officielle \`/host/status\`-integration er ikke automatisk aktiveret uden en beskyttet tokenkonfiguration.

### Testplan
- Verificér, at en FiveM-server med \`TXADMIN_ENABLED=1\`, \`TXHOST_TXA_PORT\` og en matching port-allocation får txAdmin-knappen.
- Verificér, at den samme server uden matching allocation viser en opsætningsbesked.
- Verificér, at en FiveM-server med \`TXADMIN_ENABLED=0\` ikke viser et aktivt login-link.
- Verificér, at Minecraft-servere og medbrugere uden \`startup.read\` ikke kan åbne txAdmin-fanen.
- Test førstegangs-login, txAdmin resource-manager og en reel start/stop fra **txAdmin** separat på en FiveM-testserver.
