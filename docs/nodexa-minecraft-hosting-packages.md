# Minecraft-pakker: Dirt til Obsidian

De navngivne pakker kan oprettes under Admin → Storefront & Billing → Egg-pakker.
Vælg et installeret Minecraft Egg (f.eks. Paper, Spigot, Fabric eller Forge), vælg niveauer og tilpas priser.

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
3. Vælg Minecraft Egg, pakker og eventuelle prisændringer.
4. Vælg Opret valgte Minecraft-pakker.
5. Gennemgå ressourcer, faktiske nodegrænser og priser.
6. Aktivér de ønskede skjulte produkter under Storefront & Billing.

Nye produkter vises på /hosting med Minecraft-filteret, når de er aktiveret.
Stone er markeret som fremhævet, men offentliggøres ikke automatisk.

## Sikkerhed og gentagen oprettelse

- Oprettede produkter har unikke, Egg-specifikke slugs og oprettes ikke igen ved gentaget generering.
- Generering hverken ændrer eksisterende abonnementspriser eller kunders serverressourcer.
- Kunder kan ikke købe de nye produkter, før administrator aktiverer dem.
- Eksisterende Wallet/Stripe checkout og Pterodactyl-provisionering anvendes.
- Hardwarekapacitet og Minecraft-modpack-behov skal kontrolleres før aktivering.
- Der kræves ingen database-migration.
