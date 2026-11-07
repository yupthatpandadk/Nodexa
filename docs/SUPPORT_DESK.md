# Nodexa support desk

The support desk now includes the WHMCS-style features below within Nodexa's existing customer and administration areas. It does not require a WHMCS installation or license.

- Configurable departments, visibility, staff membership and client closure restrictions.
- Root-only support configuration. Non-root staff need their Tickets role permissions **and** department membership; read-only staff cannot reply or modify tickets.
- Reusable predefined replies, global or scoped to one department, inserted into the editor for review before sending.
- Same-customer ticket merges that preserve messages, attachments and activity history. Old links and private reply addresses follow the destination conversation.
- Customer reopening, 1–5 ratings on public staff replies, and ticket feedback after closure.
- Ticket/customer search, pagination, an overdue queue and support statistics.
- Suggestions from published knowledgebase articles while writing a ticket subject.
- SLA response deadlines, configurable escalation, inactive ticket closure and activity logging.
- Optional customer email replies through a TLS IMAP mailbox, including validated attachments, duplicate protection and an import log.

## Install an update

Run these commands in the panel directory after deploying the branch and taking your normal database backup:

```bash
php artisan migrate --force
php artisan optimize:clear
```

There are no frontend bundle changes or new Composer requirements. The new migration preserves existing tickets. Existing support-role users receive membership in the four original departments during migration; review that membership after upgrading. Root administrators always have access to all departments.

Open **Admin → Support Tickets → Support settings** to create/edit departments, assign staff and add predefined replies. Department keys are stable identifiers; rename the display name without changing the key. Disable or hide a department to stop new submissions while retaining its existing tickets. Only root administrators can change configuration and department membership.

## SLA and automation

The existing Laravel scheduler runs mail imports every minute and support automation every five minutes, with overlap protection. If the server already runs `php artisan schedule:run` every minute, no additional cron entry is needed. Otherwise add this for the panel's service account, using the actual panel path:

```cron
* * * * * cd /var/www/pterodactyl && php artisan schedule:run >> /dev/null 2>&1
```

Manual checks:

```bash
php artisan nodexa:support:automation
php artisan nodexa:support:mail
php artisan schedule:list
```

Automation is **disabled per department by default** (both rule values are 0).

- SLA starts when a customer opens or replies to a ticket and pauses after a public staff reply. Internal notes, tags and assignment changes preserve the original deadline.
- Escalation runs after the configured number of minutes **past the SLA deadline**. It can raise, never lower, priority, logs an event and notifies staff. It runs once per response cycle and resets when the customer replies.
- Auto-close applies only to `answered` / `waiting_customer` tickets after the configured hours since the last staff reply, provided there is no later customer reply. Tickets waiting for staff are never automatically closed.
- Updating SLA settings affects future response cycles; existing deadlines are retained until the next customer reply or priority change.

For multi-host scheduler deployments, run the support schedule on one designated host or use a shared cache store for overlap locks.

## Enable customer replies by email

Outgoing email continues to use the panel's existing SMTP configuration. Incoming replies require the PHP **IMAP extension** in the CLI runtime and a TLS mailbox accepting plus addressing (for example, `support+anything@example.com` must arrive in `support@example.com`). Ensure no other mail client marks messages read before the importer runs.

Add these values to the server's `.env`, using your own mailbox credentials:

```dotenv
SUPPORT_MAIL_ENABLED=true
SUPPORT_MAIL_ADDRESS=support@example.com
SUPPORT_IMAP_HOST=imap.example.com
SUPPORT_IMAP_PORT=993
SUPPORT_IMAP_USERNAME=support@example.com
SUPPORT_IMAP_PASSWORD="your-mailbox-password"
SUPPORT_IMAP_FOLDER=INBOX
```

Then run `php artisan optimize:clear` and `php artisan nodexa:support:mail`.

New ticket emails contain a private `Reply-To` address for that ticket. Replies must be sent from the customer's account email address to this private address. Existing tickets receive a reply address on their next outgoing notification. Replies to merged tickets follow the merge chain; closed tickets reopen only if reopening is enabled in support settings.

The importer processes up to 100 unread messages per run, validates attachments (up to 10 files and 10 MB per file), ignores automated mail and rejects messages over 25 MB. Unsupported files, wrong senders and missing private addresses are recorded in **Recent mail imports**. Imported/rejected mail is marked read; transient processing failures remain unread for retry. Duplicate processing does not create duplicate messages.

This inbound integration handles replies to existing customer tickets. New tickets are created in the authenticated Client Area. Department contact email fields do not configure separate inbound mailboxes. The IMAP password is read from the environment and never shown in the support UI. Configure mailbox spam filtering and normal sender authentication with your mail provider.

## Verification

```bash
composer install
php vendor/bin/phpunit -c phpunit-support.xml
```

The isolated support suite uses SQLite in memory, Laravel's array mail transport and fake local storage. It covers department access, ownership, private attachments, numeric route binding, merges, SLA behavior, automatic closure, email import validation/idempotency, ratings and HTTP page rendering. It does not connect to a real SMTP/IMAP account.

After deployment, verify one real email reply through your own configured mailbox and check the import log. SMTP delivery, mailbox plus addressing, PHP IMAP availability and the server's cron configuration are deployment prerequisites.
