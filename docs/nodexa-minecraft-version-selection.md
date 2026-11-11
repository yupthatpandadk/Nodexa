# Minecraft: valg af software og version ved bestilling

På en aktiv Minecraft-hostingpakke (`game = Minecraft`) viser Nodexa nu et bestillingstrin med valg af **Minecraft Egg** og **Minecraft Java-version**.

## Hvor kommer mulighederne fra?

- Minecraft-software hentes dynamisk fra alle Eggs, der aktuelt tilhører et Nest med navnet Minecraft (f.eks. Paper, Forge, Fabric, Vanilla og Sponge). Nye Eggs bliver automatisk tilgængelige ved næste sidevisning.
- Ingen Egg-ID'er er hardcodet. Valget kontrolleres mod Minecraft Nest på serveren, også ved checkout.
- Versionslisten hentes fra Mojangs officielle Java-release-manifest og caches i 6 timer. `latest` findes altid som fallback.
- Versionsdropdown vises kun, når det valgte Egg har en Minecraft-versionsvariabel (MINECRAFT_VERSION, MC_VERSION, VANILLA_VERSION m.fl.). Eggs, der mangler den, kan vælges, men viser tydeligt, at versionen styres af Eggs standardindstillinger.
- Mojangs Java-release-liste beviser ikke kompatibilitet med alle Forge/Fabric/Paper-udgivelser. Installationen kan afvise en version, som ikke er understøttet af det valgte Eggs installationsscript.

## Gennem hele betalingsflowet

1. Kunden vælger hostingpakke og servernavn.
2. Kunden vælger software fra Minecraft Nest og relevant Minecraft-version.
3. Valgene opbevares i kurven og gemmes som snapshot i ordrelinjens `configuration`.
4. Når betaling registreres, gemmes Egg-ID og Minecraft-version på `nodexa_store_orders` via en ny migration.
5. Provisionering vælger dette Egg i stedet for produktets standard-Egg og overfører versionen til det korrekte Pterodactyl-miljøfelt.
6. En passende Java-image vælges, når den findes blandt det valgte Eggs Docker-images.

Produktets pris, RAM, CPU, SSD og normale abonnementsvilkår ændres ikke, når man vælger anden server-software.
Gamle ordrer uden Minecraft-valg bruger fortsat produktets standard-Egg.

## Installation

En Laravel-migration tilføjer nullable `minecraft_egg_id` og `minecraft_version` på `nodexa_store_orders`. Nodexas normale opdateringsscript kører migrationen. Eksisterende ordrer påvirkes ikke.

Installer med Update Center eller via SSH:

```bash
curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash -s -- update
```

Test med en ubetalt testordre til et Minecraft Egg, før der gennemføres rigtige betalinger. Test både software-, versionsvalg og faktisk Pterodactyl-installation.
