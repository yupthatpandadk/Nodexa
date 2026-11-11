# Nodexa Frontend Theme Center – ægte layouts

Under **Admin → Settings → Temaer → Frontend Theme Center** vælger administrator ét af fem design til den offentlige hostinghjemmeside. Temavalget gemmes i \`nodexa::frontend.theme\`.

## Hvordan det fungerer

Temasystemet vælger nu **forskellige Blade-sideopbygninger**, ikke bare nye farver. Den valgte skabelon bruges til både forsiden, game-hostingkataloget og VPS-kataloget. Fælles kundesider, checkout og produktsider anvender samtidig themespecifik navigation, typografi og layouts, mens deres fungerende backend og formularer bevares.

| Tema | Header | Forside | Hosting | VPS |
| --- | --- | --- | --- | --- |
| Nodexa Original | Præcis den gamle navigationslinje | Oprindelig forside | Oprindeligt produktgrid | Oprindeligt VPS-katalog |
| Latch-stil | Todelt operationsheader | Asymmetrisk editorial/terminal-visning | Venstreside med filtre og vandrette pakkelinjer | Opsætning med teknisk sidepanel |
| Xlumia-stil | Flydende navigation | Centreret premium-hero og dashboard-preview | Kuraterede kort med fremhævede planer | Centreret cloud-intro |
| Horizon-stil | Statuslinje, masthead og separat navigation | Enterprise split-hero med operationspanel | Tabelform med ressourcer og handlingskolonne | Ressourceorienteret virksomhedsvisning |
| DinoNode-stil | Gamingnavigation med kategori-stribe | Spilvælger for Minecraft, FiveM og VPS | Kategoriorienteret kortkatalog | Gaming-præget VPS-katalog |

Designene er **originale Nodexa-implementeringer**. Der bruges ingen licensbeskyttet HTML, billeder, scripts eller andre assets fra Latch, Xlumia, Horizon eller DinoNode.

## Skabelonarkitektur

- \`FrontendThemeService::template('home'|'hosting'|'vps')\` vælger den relevante Blade-skabelon efter det gemte eller previewede tema.
- \`store.frontend.{theme}.{page}\` indeholder 12 ægte layouts for fire alternative temaer.
- \`store.frontend.parts.header\` giver hvert design forskellig navigation. Auth, Wallet, kurv, ordreoversigt, tickets og logout fungerer fortsat.
- \`store.frontend.parts.game-cards\`, \`vps-cards\` og \`catalog-filters\` anvender **live-produkter**. Priser, rabatter og produkter ændres ikke ved temaskift.
- \`store.layout\` bruger fortsat den fuldstændigt oprindelige header ved \`classic\`. Alternative temaer indlæser CSS for shell, page-base og det valgte design.
- \`public/css/nodexa-frontend-{shells,page-base,latch,xl,horizon,dino}.css\` styrer layout. Fælles produktsider, checkout og Client Area får også forskellige navigationer og struktur via responsivt CSS.
- Ved en ukendt eller manglende temaskabelon anvendes Nodexa Original.
- Forhåndsvisning med \`?frontend_theme_preview=...\` er begrænset til root-administrator. Andre ser det publicerede tema.

## Installation og test

1. Opdatér Nodexa og vælg et frontend-tema i Admin Area.
2. Forhåndsvis forsiden, hostingkataloget og VPS-kataloget på mobil og desktop.
3. Kontrollér produkternes **faktiske priser** og kategorifiltrering.
4. Gennemfør en test af valg af Minecraft-type/version, kurv, checkout, Wallet og Client Area.
5. Bekræft, at adminpanel og serverkontrolpanel ser ud som før.
6. Vælg Nodexa Original og verificér, at det gamle design er intakt.

Laravel-views kan kontrolleres med \`php artisan view:cache\` på serveren. PHP/backend-filer kan lintes med \`php -l\`. Sidernes funktion skal testes på den faktiske deployment; GitHub-kodegennemgang er ikke en live browser-/betalingstest.
