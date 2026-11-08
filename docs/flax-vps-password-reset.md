# Flax VPS password reset in Nodexa

Nodexa can initiate a password reset on a customer's existing VPS and save the
new credential **only if Flax has supplied a verified reset action for their
reseller API**. This is not the same as the VirtFusion Global API.

## Required provider confirmation

Request these details from Flax Hosting:

1. The exact `action` name for a **POST** to their reseller
   `/api-endpoint.php` that resets a VPS operating-system password.
2. Whether it accepts `server_id` and `user` (`Administrator` or `root`).
3. Whether the response directly returns the generated password, for example
   `{"success":true,"data":{"password":"..."}}` (illustrative only).
4. Whether it queues a reset, how to check the queue state, and whether the
   password is valid immediately or only when the queue finishes.
5. Whether the reseller API key is permitted to perform the operation.

**Do not share API keys or generated passwords in support tickets.**

## Configuration (after confirmation)

In `/var/www/pterodactyl/.env`, set:

```env
NODEXA_FLAX_PASSWORD_RESET_ACTION=ACTION_CONFIRMED_BY_FLAX
```

Replace the placeholder with the **exact action from Flax**. Do not use an
unverified action name.

Then refresh Laravel's cached configuration:

```sh
cd /var/www/pterodactyl
sudo php artisan optimize:clear
sudo php artisan config:cache
```

Log in as a customer and open `/client/vps/{service}`. Under
**Adgang til VPS → Nulstil VPS-password**, the button calls Nodexa's
authenticated, per-customer endpoint. Nodexa submits a reset to Flax using the
existing configured reseller API key and the **matching upstream server ID**.

If Flax returns a new password, Nodexa stores it using Laravel encryption in
`nodexa_flax_services.credential_password_encrypted`. It is then accessible
only to the authenticated VPS owner through the existing **Vis / Kopiér**
buttons. It is not placed into log messages, URLs, or flash messages.

If Flax reports success without a password, Nodexa **does not** claim a
completed reset and does **not** replace the saved password: the upstream reset
may be pending. Check the provider before retrying.

## Important distinction

VirtFusion documents
`POST /api/v1/servers/{serverId}/resetPassword` and a response with
`data.expectedPassword` and `data.queueId`. Those credentials and
permissions **do not automatically apply** to Flax's separate reseller
`/api-endpoint.php`. Do not point Flax's reseller URL at the VirtFusion API.
