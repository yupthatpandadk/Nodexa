# Nodexa Frontend Theme Center

## Uafhængige frontend-layouts

Gå til **Admin → Settings → Temaer → Frontend Theme Center**, eller åbn temaerne via **Storefront & Billing → Administrér frontend-temaer**.

Vælg mellem fem layouts til den offentlige hjemmeside:

| ID | Navn | Karakter |
|---|---|---|
| `classic` | Nodexa Original | Det eksisterende design, helt uændret, valgt ved første installation |
| `latch` | Latch-stil | Mørkt operationelt layout, mintaccent og tydelige serverkort |
| `xlumia` | Xlumia-stil | Premium violet/cyan, afrundede kort og diskrete auroraeffekter |
| `horizon` | Horizon-stil | Struktureret grafitblåt layout med tydeligere navigation |
| `dinonode` | DinoNode-stil | Mørkegrønt gaminglayout og stærkere produktfokus |

Dette er **selvstændigt udviklede Nodexa-stilvarianter**, ikke originalkode eller aktiver fra de eksterne betalte skabeloner. Ønskes præcis originalintegration, kræver det de lovligt erhvervede skabelonfiler og passende brugsrettigheder.

## Aktivering og forhåndsvisning

1. Vælg et layout i Frontend Theme Center.
2. Brug **Forhåndsvis frontend** på et kort. Kun root-administrator kan se uoffentliggjorte previews via `?frontend_theme_preview=<id>`.
3. Tryk **Gem frontend-tema**. Ny frontend vises ved næste sideindlæsning uden serverrestart.
4. Vend tilbage til **Nodexa Original** når som helst.

Den valgte frontend gemmes som `nodexa::frontend.theme` i eksisterende settings repository. Ukendte værdier falder tilbage til `classic`. Theme IDs og farver er hardcodet; besøgende kan ikke indsprøjte CSS eller HTML.

## Hvad ændres?

- Layout, kort, knapper, typografi og dekorative flader på offentlige Blade-baserede storefront- og kundesider.
- Alle sider der bruger `store.layout` påvirkes: forside, hosting, produktsider, kurv, checkout og Client Area.
- Ingen ændringer af priser, ordrer, betalingsflow, Wallet, Pterodactyl, Proxmox, Admin Area, React-serverpanelet eller data.
- De eksisterende farvetemaer, Halloween-/juletemaer og sneindstillinger er en særskilt funktion, som stadig kan bruges.

## Teknisk implementering

- `app/Services/Nodexa/FrontendThemeService.php`: presets, settings, validering og root-admin-forhåndsvisning.
- `app/Http/Controllers/Admin/Settings/ThemesController.php`: separat save-handling.
- `routes/admin.php`: `admin.settings.themes.frontend.update`.
- `resources/views/admin/settings/themes.blade.php`: responsive temakort, status og Gem-knap.
- `resources/views/store/layout.blade.php`: sikker data-attribut til det valgte layout.
- `public/css/nodexa-frontend-layouts.css`: frontend-only CSS, originaldesign uden ændringer.

CSS-filen indeholder ingen kopierede skrifttyper, billeder, JavaScript eller HTML fra de kommercielle templates.

## Testplan

1. Verificér at frontend-layout er `classic`, indtil administrator vælger andet.
2. Forhåndsvis hver af de fire nye stilarter på mobil og desktop.
3. Kontrollér forside, hosting, produktkonfiguration, kurv, checkout, Wallet og Client Area.
4. Skift frontend-layout, og kontrollér at Admin Area og `/server` fortsat har eksisterende tema.
5. Gå tilbage til Nodexa Original og sammenlign udseendet med før opdateringen.
6. Afvis ukendt preset og preview fra ikke-admin bruger.
