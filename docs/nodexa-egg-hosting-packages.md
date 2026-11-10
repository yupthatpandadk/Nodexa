# Nodexa · Pterodactyl Egg-hostingpakker

## Funktion

Under **Admin → Storefront & Billing → Åbn Egg-pakker** (eller direkte\n\`/admin/store/egg-packages\`) kan
ejer/admin nu generere fire færdige hostingpakker til et installeret Egg:

- **Starter**
- **Plus** (fremhævet som anbefalet)
- **Pro**
- **Ultimate**

Eksisterende Pterodactyl Eggs og serverprovisionering bruges direkte.
Pakkerne oprettes som separate `nodexa_store_products` med egg-ID,
RAM, CPU%, disk, databaser, backups, allocation-limit og DKK/måned.

**Alle nyoprettede pakker er skjulte.** Aktivér dem først manuelt,
efter at serverkapacitet og pris er kontrolleret. Prisfelterne i
pakkegeneratoren kan overskrive de foreslåede standardpriser.
Efterfølgende ændringer kan foretages med
**Redigér pakke og pris** på produktlisten.

Generering er idempotent via slug `egg-{egg_id}-{tier}`; gentagen
generering springer allerede oprettede pakker over og ændrer ikke
manuelt redigerede produkter. Oprettelse og ændring af pakkepriser
ændrer ikke eksisterende kundeserveres RAM/CPU/disk eller eksisterende
abonnementsbeløb.

## Forslag til pakker

Vejledende standardpriser i DKK pr. måned, **ikke** markedsverificerede
eller bundet til dine serveromkostninger:

| Spiltype | Starter | Plus | Pro | Ultimate |
|---|---|---|---|---|
| Minecraft (RAM, kr.) | 2 GB / 29 | 4 GB / 49 | 8 GB / 89 | 12 GB / 129 |
| FiveM (RAM, kr.) | 4 GB / 59 | 6 GB / 89 | 8 GB / 129 | 12 GB / 189 |
| Rust (RAM, kr.) | 6 GB / 89 | 8 GB / 129 | 12 GB / 189 | 16 GB / 259 |
| ARK (RAM, kr.) | 8 GB / 119 | 12 GB / 169 | 16 GB / 229 | 24 GB / 329 |
| Voice (RAM, kr.) | 0.5 GB / 15 | 1 GB / 25 | 2 GB / 39 | 4 GB / 59 |
| Andre spil (RAM, kr.) | 2 GB / 29 | 4 GB / 49 | 8 GB / 89 | 16 GB / 159 |

Hver spiltype har også forskellige CPU-, disk-, database-, backup-
og allocation-grænser. Vis alle detaljer i generatorens forhåndsvisning.

CPU-grænser er Pterodactyl-procenter, hvor omkring 100% svarer til
én kerne. Kapaciteten og spillets egen kravprofil kan afvige.
Intensive modpacks, Rust, ARK og høje FiveM-spillertal kan kræve
mere RAM/CPU end disse forslag.

## Opsætning

1. Installér/importér et Pterodactyl Egg under
   Admin → Nests → Eggs med de korrekte Docker images, startup
   parametre og miljøvariabler.
2. Gå til `/admin/store` og find **Pterodactyl Egg-pakker**.
3. Vælg Egg. Kategori bestemmes automatisk ud fra Egg/Nest-navn
   eller kan vælges manuelt.
4. Markér ønskede niveauer og justér evt. priser i DKK/md.
5. Vælg **Opret valgte pakker som skjulte produkter**.
6. Kontroller pris, ressourcer og at mindst én egnet Pterodactyl-node
   har ledig RAM/disk og en ledig allocation.
7. Aktivér hvert godkendt produkt. Kunder kan herefter se og købe
   dem på `/hosting`. Filtrene Minecraft/FiveM/Andre spil er nu
   funktionelle.

Der findes allerede en fælles betalings- og checkout-proces i Nodexa,
inkl. Wallet-first og Stripe ved resterende beløb. Der er ikke tilføjet
en konkurrerende bestillingsmekanisme.

Når checkout er gennemført, bruger Nodexas eksisterende
`ProvisionStoreOrderService` produktets Egg og ressourcer til at
oprette serveren via Pterodactyl `ServerCreationService`.

## Drift og sikkerhed

- Tilpas priser efter dine faktiske omkostninger og momsforhold.
- Kontrollér Eggs' environment defaults, adgangskrav og licenser.
- Sikr passende node-kapacitet og allocations, før et produkt aktiveres.
- Der oprettes ingen nye servere under pakke-generering.
- Eksisterende aktive servere og abonnementer ændres ikke.
- Opgradering af et eksisterende abonnement er ikke implementeret
  gennem denne generator.
- Det anbefales at teste først med et lille Minecraft- eller
  standard-Egg og én prøveordre.

## Installation

```bash
curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash -s -- update
```

Der kræves ingen ny migration for denne generator. Koden er
kontrolleret i GitHub, men test af den aktive Pterodactyl-node og
betalingerne er en separat driftskontrol.
