# Minecraft-pakker: Dirt til Obsidian

De navngivne pakker kan oprettes under Admin → Storefront & Billing → Egg-pakker.
Du behøver ikke vælge et Egg. Nodexa vælger automatisk et almindeligt Minecraft-server-Egg som teknisk reference (helst Paper eller Vanilla). Kunden vælger selv Paper, Fabric, Forge osv. og Minecraft-version ved bestilling.

| Pakke | RAM | CPU | SSD | DB | Backups | Porte | Vejledende kr./md. |
|---|---:|---:|---:|---:|---:|---:|---:|
| Dirt | 2 GB | 100 % | 10 GB | 1 | 1 | 1 | 29 |
| Grass | 3 GB | 125 % | 15 GB | 1 | 2 | 1 | 39 |
| Stone | 4 GB | 150 % | 20 GB | 2 | 2 | 1 | 49 |
| Iron | 6 GB | 200 % | 30 GB | 2 | 3 | 2 | 69 |
| Gold | 8 GB | 250 % | 40 GB | 3 | 3 | 2 | 89 |
| Diamond | 10 GB | 300 % | 60 GB | 3 | 4 | 2 | 119 |
| Emerald | 12 GB | 350 % | 80 GB | 4 | 5 | 3 | 149 |
| Obsidian | 16 GB | 450 % | 100 GB | 5 | 6 | 3 | 199 |

CPU er angivet i Pterodactyl-procenter, hvor 100 % svarer omtrent til én kerne.
Priserne er redigerbare eksempler i DKK/md., ikke markedsverificerede tilbud.

## Arbejdsgang

1. Installer et kompatibelt Minecraft Egg under Admin → Nests → Eggs.
2. Gå til Admin → Storefront & Billing → Åbn Egg-pakker.
3. Markér pakker og justér eventuelt priserne – der kræves intet Egg-valg i adminpanelet.
4. Vælg Opret valgte Minecraft-pakker.
5. Gennemgå ressourcer, faktiske nodegrænser og priser.
6. Aktivér de ønskede skjulte produkter under Storefront & Billing.

Nye produkter vises på /hosting med Minecraft-filteret, når de er aktiveret.
Stone er markeret som fremhævet, men offentliggøres ikke automatisk.

## Sikkerhed og gentagen oprettelse

- Nye produkter får en Egg-uafhængig slug som `minecraft-dirt` eller `minecraft-stone`. Allerede oprettede produkter fra den gamle Egg-specifikke generator springes over for at undgå dubletter.
- Det automatisk valgte Egg gemmes kun som teknisk standard på produktet, da `egg_id` er påkrævet i eksisterende database. Ved bestilling vælger kunden det faktiske Egg, som bruges ved Pterodactyl-installationen.
- Hvis kun proxy-Eggs som BungeeCord eller Velocity findes, skal du installere et server-Egg først.
- Generering hverken ændrer eksisterende abonnementspriser eller kunders serverressourcer.
- Kunder kan ikke købe de nye produkter, før administrator aktiverer dem.
- Eksisterende Wallet/Stripe checkout og Pterodactyl-provisionering anvendes.
- Hardwarekapacitet og Minecraft-modpack-behov skal kontrolleres før aktivering.
- Der kræves ingen database-migration.
