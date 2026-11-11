# Nodexa Update Center – systemd update service

The Nodexa web application runs as `www-data`. Giving this user write
access to the application source tree is unsafe. Update Center therefore
requests a dedicated systemd service with one precisely restricted sudo rule.

## One-time setup / upgrading from older versions

Run a privileged Nodexa update on the server over SSH:

```bash
curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash -s -- update
```

The script performs the normal backup, downloads `main`, installs
dependencies, builds frontend assets and runs database migrations. It then runs
`installers/setup_update_service.sh` to install:

- `/etc/systemd/system/nodexa-update.service` – fixed systemd service
- `/etc/sudoers.d/nodexa-update` – only allows `www-data` to call
  `/usr/bin/systemctl start --no-block nodexa-update.service`

This does **not** grant a shell, unrestricted sudo, access to arbitrary
systemd commands, or writable Git/source directories to `www-data`.
The updater script, its parent directories and Git metadata are root-owned.
Only this fixed unit can be started via the web action. The unit uses
additional systemd isolation and does not run system-level Wings/domain
migration during web-triggered updates.

After the one-time setup, future updates can be started from
**Admin Area → Update Center**, assuming the server uses systemd and sudo.

## Diagnosing a failed update

```bash
sudo systemctl status nodexa-update.service --no-pager
sudo journalctl -u nodexa-update.service -n 80 --no-pager
sudo tail -n 80 /var/www/pterodactyl/storage/logs/nodexa-update.log
cat /var/www/pterodactyl/NODEXA_VERSION
```

Do **not** use `chmod -R 777`, `chown -R www-data:www-data` over the source
tree, or a wildcard NOPASSWD rule to fix an update.

Update Center marks a queued update as failed if its systemd job did not start,
rather than continuing to display an indefinite “Updating” state. The system
must not be considered updated until `NODEXA_VERSION` reflects the target
version and the updater reports completion.

## Notes

- The privileged service executes code from the configured public GitHub
  repository. Only use the expected, trusted repository.
- A Git reset overwrites uncommitted changes to tracked source files; a
  backup is created first.
- Rollback and system-level operations should be performed over SSH with
  administrator access, not via the web button.
