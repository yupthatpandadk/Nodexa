# VPS password retrieval and reset (Flax reseller API)

This document is **internal operator documentation**. Customer pages must
display Nodexa branding only.

The reseller dashboard documentation (provided 9 November 2026) describes
the following API actions:

| Action | Method | Parameters | Expected response |
| --- | --- | --- | --- |
| `get_password` | POST | `server_id` (numeric upstream VPS ID) | `data.available`, `data.password` (nullable), `data.server_id`, `data.source=last_stored` |
| `resetPassword` | GET/POST | `server_id`, optional `user` (defaults to root) | Reset acknowledgment; credential may be stored asynchronously |

Nodexa uses **POST** for both actions, and its existing encrypted reseller API
key configured in Admin. No new `.env` variable is required. Do **not**
copy API keys from documentation or screenshots into repository code.

## Customer workflow

1. The authenticated customer opens `/client/vps/{service}`.
2. `Hent / Vis` or `Kopiér` calls the authenticated POST
   `/client/vps/{service}/password/reveal` route (CSRF-protected and throttled).
3. Nodexa independently confirms that the service belongs to the signed-in
   customer and has a valid upstream server ID.
4. Nodexa requests `get_password` for that upstream VPS ID. If
   `data.available=false`, there is no automatically stored password.
5. On a valid result, Nodexa returns the password over HTTPS in a
   **no-store** JSON response, stores an encrypted copy using Laravel Crypt,
   and displays it temporarily. Secrets are never rendered into initial HTML.
6. If the upstream API has no password, an existing customer-managed copy can
   be displayed **explicitly marked as a saved, unverified copy**.

## Reset workflow

1. The customer confirms a reset in Nodexa.
2. Nodexa rate-limits and locks the service, selects `Administrator` for
   Windows or `root` for Linux, and sends `resetPassword`.
3. Nodexa calls `get_password` again to retrieve the latest stored password.
4. If the returned credential differs from the previously stored credential,
   Nodexa saves it encrypted, and the customer can reveal/copy it.
5. If the provider has accepted the reset but no **new** password is stored
   yet, Nodexa records `credential_reset_pending_at` and a keyed fingerprint
   of the previous password. The previous password is never shown as the
   newly reset credential. The customer can retry `Hent / Vis` later.
6. No provider response body, API key, or password is included in session
   notifications, redirect URLs, application audit events or exception text
   shown to customers.

A password changed manually inside Windows/Linux may not update the
provider's saved password. Show the result as the **last stored** credential,
not proof of the currently accepted OS login.

## Security and operational validation

- Customer authorization uses `user_id` and the internal service ID,
  never client-supplied upstream server IDs.
- API credential remains server-side and encrypted.
- Response is private with `Cache-Control: no-store`.
- Automatic password-reset is available when the existing reseller API
  connection is configured.
- Run migrations with the Nodexa installer to create pending-reset fields.
- Test `get_password` and `resetPassword` on an authorized test VPS.
- Confirm Windows Administrator and Linux root behavior, and observe any
  asynchronous queue delays.
- If an actual reseller API key appears in a screenshot or shared example,
  rotate/revoke it in the provider portal and update the saved key in Nodexa.

Note: `get_password` is only documented for VPS credentials; it does not
retrieve Wisp/SFTP credentials.
