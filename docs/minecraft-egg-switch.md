# Minecraft Egg-skift i kundens kontrolpanel

Nodexa viser **Server → Settings → Minecraft – skift servertype** for serverens ejer, når den aktuelle server ligger i et Minecraft Nest.
Kunden kan eksempelvis skifte **Paper → Fabric** eller **Fabric → Forge**, når administrator har installeret de pågældende Eggs i samme Minecraft Nest.

## Adgang og sikkerhed

- API: `GET /api/client/servers/{server}/settings/minecraft-eggs` og `POST /api/client/servers/{server}/settings/minecraft-egg`.
- Kun den faktiske serverejer får adgang; ikke medbrugere, og ikke engang hvis de kender et Egg-ID.
- Både den aktuelle server og det valgte Egg skal høre til **præcis samme Minecraft Nest**. FiveM, Rust og alle øvrige Nests afvises også ved direkte API-requests.
- Serveren må ikke være i gang med installation, backup-gendannelse, overførsel eller vedligeholdelse.
- Serveren skal være stoppet på Wings. Status kontrolleres af backend, ikke blot frontend.
- **Backup er valgfri, men anbefales stærkt.** Hvis der ikke er en nyere fuldført backup, viser panelet en tydelig advarsel om permanent datatab. Kunden kan fortsætte uden backup efter at have bekræftet risikoen.
- Forespørgsler begrænses af API-rate limit og en per-server-lås mod samtidige Egg-skift.
- Skiftet opdaterer `egg_id`, Nest, startup, Docker-image og Egg-variabler til den valgte software, synkroniserer Wings og starter geninstallation.
- Kunden beholder sin serveridentitet, RAM, CPU, diskplads, allocations, betaling og produktpakke. **Ældre verdensfiler, plugins eller mods kan kræve manuel tilpasning efter geninstallation.**
- Ændringen logges i serverens aktivitetslog. Der foretages ingen automatisk konvertering af mods/plugins eller Minecraft-verdener.
- Hvis Wings ikke kan synkronisere ændringen, genskabes den tidligere Egg-konfiguration, og forespørgslen fejler.

## Hvordan kunden bruger funktionen

1. Tag eventuelt en backup under **Server → Backups** (anbefalet, men valgfrit).
2. Stop Minecraft-serveren helt.
3. Åbn **Server → Settings**.
4. Vælg et nyt Minecraft Egg (f.eks. Fabric) i den nye sektion.
5. Bekræft, at du accepterer risikoen – særligt hvis du ikke har taget en backup.
6. Bekræft Egg-skiftet og vent på geninstallationen. Gennemgå serverfiler og mods/plugins, før serveren startes.

### Vigtigt

Skift af server-software er ikke en garanteret sikker migration af verdener fra Paper til Fabric eller omvendt. Uden backup kan worlds, konfiguration, plugins og mods gå tabt permanent. Backup er et tilbud til kunden, ikke en forudsætning for knappen. Hvis dataene er vigtige, anbefales en ekstern/downloadet backup.

## Test

`tests/Nodexa/MinecraftEggSwitchServiceTest.php` tester adgang for Minecraft-ejer, afvisning af FiveM/Rust og medbrugere samt forbud mod at vælge Eggs fra et andet Nest. Før brug i produktion skal en testserver afprøves med Paper → Fabric og tilbage igen, inkl. Wings-sync og geninstallation.
