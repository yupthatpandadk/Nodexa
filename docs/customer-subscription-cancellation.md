# Nodexa customer self-service subscription cancellation

## Customer flow

From **Client Area → Billing & ordrer → Abonnementer**, the customer can:

- Open **Opsig produkt**, enter an optional reason and explicitly confirm.
- See the termination date and status **Opsagt · afventer udløb**.
- Use **Fortryd opsigelse** before the end of the already-paid term.

The same subscription is linked from the customer VPS control panel,
**Mine VPS'er**, **Mine servere** and **CFX EUP Keys**.

Cancellation is per subscription, not per customer or per checkout bundle.
Existing paid invoices and previously issued invoices remain intact.
Cancellation does **not** automatically issue a Stripe/card/Wallet refund.

## Billing and safety

The `SubscriptionCancellationService` locks the subscription by customer
ID, marks it `cancel_pending` and records `cancel_at`.
The automatic renewal invoice creator locks the same row, and only invoices
`active` subscriptions that have not been scheduled for cancellation.
The customer can restore the subscription before its effective cutoff.

For VPS services, the effective date is at least the next invoice date and
not earlier than a verified upstream `expires_at`.

Laravel's existing `nodexa:automation` scheduler runs every five minutes.
It processes due cancellations before creating new renewal invoices:

- **Flax VPS:** call the provider's `terminate` API, and only mark success
  after it returns successfully. Provider errors are left pending for retry,
  visible under **Admin → Operations → Abonnementer**. Prior invoices remain.
- **CFX EUP:** revoke the order/key assignment and release the key at expiry.
- **Game server:** suspend the Pterodactyl server at expiry, but retain files
  for administrative cleanup; no automatic destructive deletion.

A customer cannot cancel a different user's subscription by guessing its ID.
All forms use Laravel CSRF validation and actions are rate-limited.

## Operational checklist

1. Ensure database migrations have run (new cancellation metadata columns).
2. Ensure `php artisan schedule:run` is being invoked every minute by cron,
   or the `nodexa:automation` job is otherwise run periodically.
3. Test cancellation and undo against a test account and a test subscription.
4. Test automated completion of each product type on a test service before
   enabling the flow for live products.
5. For Flax, verify upstream termination works and failed upstream requests
   remain pending rather than being silently marked completed.

## Policy

Customer-facing messaging explains termination at the end of the paid term,
without any automatic refund. Service-specific consequences are shown before
confirmation. Any applicable statutory withdrawal/refund rights remain
outside this technical workflow and require separate policy handling.
