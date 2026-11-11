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
