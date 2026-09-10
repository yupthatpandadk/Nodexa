# Nodexa Changelog

## v0.14.62 — 2026-09-10

### VPS
- Fjernet det redigerbare brugernavn fra "Nulstil adgangskode".
- Nodexa bestemmer nu administratorkontoen automatisk ud fra VPS'ens operativsystem.
- Linux bruger altid `root`.
- Windows bruger altid `Administrator`.
- Valget foretages server-side før reset-kaldet sendes gennem Flax Hosting API, så kunden ikke kan manipulere brugernavnet i formularen.
- VPS Control viser hvilken konto Nodexa automatisk vil nulstille.

### Release
- Versionsnummer opdateret fra v0.14.61 til v0.14.62.

## v0.14.61 — 2026-09-10

### Design
- Redesignet Client Area, så layout, farver, kort, typografi og navigation følger Nodexa Hosting Cloud-temaet.
- Redesignet VPS Control Panel med samme Nodexa-visuelle stil og bedre mobilvisning.
- Nyt samlet kunde-dashboard med tydeligere game servers, VPS'er, fakturaer, support og hurtige handlinger.
- VPS Control har nyt server-headerkort, statusoversigt, strømstyring, loginoplysninger og tydelig danger zone.

### VPS
- Bevarer Flax Hosting API-integration til VPS-data og handlinger.
- Bevarer krypteret password-visning, vis/skjul og kopiér-funktion.
- Bevarer reset password, power controls og geninstallation af operativsystem.

### Release
- Versionsnummer opdateret fra v0.14.60 til v0.14.61.
