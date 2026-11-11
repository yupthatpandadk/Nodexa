# Wallet-kredit: Pterodactyl og VPS

Nodexa Wallet kan administreres under **Admin Area → Wallet & Kredit**.

Indstillingen **Wallet – hvilke produkter må betales med kredit?** har to valg:

- **Pterodactyl + VPS:** Wallet må betale både `game_server` og `flax_vps` produkter.
- **Kun Pterodactyl:** Wallet må kun betale `game_server` produkter. VPS-delen af en blandet ordre betales med Stripe.

Den eksisterende adfærd (begge produkttyper) er standard, indtil admin ændrer indstillingen. Værdien gemmes som `nodexa::wallet.allow_vps` i Nodexas settings-lager. Ingen migration er nødvendig.

## Sikker betaling

`WalletUsagePolicy` beregner beløbet der må betales med Wallet efter udsalg og rabatkode. For blandede kurve fordeles rabatkoden proportionalt til varelinjerne. En ukendt produkttype er aldrig Wallet-godkendt.

Reglen kontrolleres i `WalletService::payCheckout` og `WalletService::prepareStripeSplit` samt før Stripe-service automatisk skifter til fuld Wallet-betaling. Derfor kan ikke-godkendte ordrer ikke omgå reglen ved at sende en POST-request manuelt eller betale en ældre ubetalt ordre.

Ved delbetaling reserveres kun det tilladte Wallet-beløb. Stripe-betalingen holder minimum 2,50 DKK, når Wallet ikke betaler hele ordren. En igangværende Stripe-reservation, der blev oprettet **før** indstillingen blev ændret, bevares, indtil den betales, udløber eller annulleres. Det undgår ubalancer i eksisterende betalingssessioner.

**Ingen ændring** af Wallet-saldi eller historiske betalte ordrer. VPS-køb uden Wallet fortsætter med Stripe.

## Regressionstest

`tests/Nodexa/WalletUsagePolicyTest.php` dækker tilladte og afviste produkttyper, blandede kurve med rabat, fail-closed for ukendte produkter samt Stripe-minimum.

## Betalingstests 11. november 2026

Testene er udført **isoleret på PHP 8.4**, uden adgang til produktionens database, Stripe-nøgler eller en staging-server. Ingen betalinger, reservationer eller refunderinger blev oprettet.

- 9 kildekodebaserede unit tests for Wallet-reglerne og Stripes beløbsgrænse bestod.
- 8 ekstra scenarier for produktmix og rabatter bestod.
- 9 mock-integrationstests med simuleret settings- og checkout-datalag bestod.
- 39.912 tilfældigt genererede egenskabskontroller af fordelinger og minimumsbeløb på mindst 2,50 DKK bestod.

Stresstesten fandt scenarier, hvor beløbet efter rabat ligger under Stripes minimum på 2,50 DKK, og ikke kan dækkes 100 % med tilladt Wallet-kredit. Cart checkout afviser nu disse ordre **før** oprettelse med en forståelig fejl, og de to tilfælde er dækket af nye unit tests. Kun Stripe-betalinger på mindst minimumsbeløbet er medtaget i den generelle property-check.

**Udført:** Regel- og beregningstest samt mock-integration af checkout-lagring. **Ikke udført:** fuld Laravel/PHPUnit-testpakke og Stripe Test Mode checkout/webhook på en live testserver. En sådan E2E-test kræver en isoleret deployment med testnøgler og adgang til at kontrollere både Wallet-transaktioner og Stripe-betalinger.
