# Nodexa VM Templates – Proxmox VE

## Implemented in Nodexa (November 2026)

The owner-only VM template catalog is under **Admin → Proxmox VE → VM Templates**:

`https://nordicnode.org/admin/proxmox/templates`

Available built-in OS entries:

- Ubuntu Server 24.04 LTS
- Ubuntu Server 22.04 LTS
- Debian 13 / 12
- Rocky Linux 9
- AlmaLinux 9
- Windows Server 2022 / 2025

These are **preset definitions**, not preinstalled OS disk images. One-time
setup is required on the Proxmox node: prepare and convert a QEMU guest to
a template and then map the template's VM ID to an OS preset in Nodexa.

Once the mapping is active, the owner can choose a preset, enter a VM name,
and click **Opret og start VM**. Nodexa:

1. Verifies the source is still a QEMU template.
2. Gets a suggested free VM ID from `GET /cluster/nextid`.
3. Reserves the VM ID in Nodexa under a DB row lock.
4. Sends a **single** `POST /nodes/{node}/qemu/{source-vmid}/clone`
   with `full=1`, the new VM ID and name.
5. Stores Proxmox's task UPID and polls
   `GET /nodes/{node}/tasks/{upid}/status` via the scheduled job.
6. Only after the clone task reports `stopped` with `exitstatus=OK`,
   sends a **single** VM start request:
   `POST /nodes/{node}/qemu/{newid}/status/start`.
7. Polls start task status and reports **Startet** only when that task
   succeeds. New machines are independent full clones, not linked disks.

If a mutation times out or returns an invalid response, Nodexa marks the
result **unknown** and refuses to retry automatically. The operator must
verify the actual destination VM ID directly in Proxmox first.
The form uses an idempotency nonce to prevent duplicate submissions.
A failed clone is not automatically deleted, to avoid accidental data loss.

## One-time Ubuntu cloud-image template creation

The repository includes an optional bootstrap helper that runs on the
**Proxmox host**, not the Nodexa PHP server:

`scripts/proxmox/create-ubuntu-cloud-template.sh`

It fetches official released Ubuntu Server cloud images from
`cloud-images.ubuntu.com`, verifies the repository's SHA256SUMS, creates
a 2-vCPU/2-GB template with a cloud-init disk, DHCP and an SSH public
key. It does not delete existing VM IDs or create default passwords.

Example: first place the helper on your Proxmox host, ensure you have an
SSH **public** key, then run on Proxmox:

```sh
bash create-ubuntu-cloud-template.sh ubuntu-2404 9000 local-lvm vmbr0 /root/.ssh/id_ed25519.pub
```

After the VM is converted, use node name `pve` (or your real node name)
and VMID `9000` to register **Ubuntu Server 24.04 LTS** in Nodexa.

Never share the same inherited SSH login across unrelated customers.
Use individual cloud-init access settings, SSH keys and passwords before
turning this into a customer-facing VPS product.

For Debian, Rocky Linux, AlmaLinux or Windows, prepare a source template
on Proxmox first and link its VMID. Windows requires legitimate installation
media, an appropriate license, and typically Sysprep before converting to
a reusable template.

## Required API token rights

The original **PVEAuditor** token for reading node and VM inventory is
insufficient to clone or start machines. Create a separate dedicated service
token with only the permissions needed for the intended deployment scope.

Proxmox permissions include (exact ACL scope depends on your storage/network):

- `VM.Audit` to inspect the source template.
- `VM.Clone` on the source VM.
- `VM.Allocate` for new VM IDs, or for a configured destination pool.
- `Datastore.AllocateSpace` for disk allocation to permitted storage.
- `VM.PowerMgmt` for starting created VMs.
- `SDN.Use` if needed for network bridges/VNets.

The built-in read-only PVEAuditor role does **not** grant those write
permissions. Assign only the required privileges to the token's owner and
(privilege-separated) token ACLs. Avoid using root tokens.

The deployment API uses an encrypted Proxmox token only on the Nodexa
server. Customer storefront routes do **not** expose VM creation.

## Operational requirements

- Run the regular Nodexa update. It creates
  `nodexa_proxmox_templates` and `nodexa_proxmox_deployments`.
- Confirm Laravel's scheduler runs every minute; the new
  `nodexa:proxmox:process` command polls asynchronous Proxmox jobs.
- Manually check current status with
  `php artisan nodexa:proxmox:process` or click **Opdatér status** in
  the Nodexa admin panel.
- Ensure Proxmox HTTP(S) connectivity and a trusted TLS certificate.
- Source template disk and destination storage must have enough free space
  for a full clone. The source VM determines default CPU/RAM/disks/network.
- The new VM has no automated billing, client ownership mapping or root
  password generation. This feature is for an authorized Nodexa owner/admin
  to manage their own Proxmox infrastructure. Do not offer it to paying
  customers until isolation and access credentials are implemented.

## Verification checklist

1. Confirm migrations succeed and VM Templates appears in Proxmox admin.
2. Confirm ordinary admins/customers get HTTP 403.
3. Use a small test Proxmox QEMU template, connect it to an OS preset.
4. Create `ubuntu-test-01`; confirm one new VM ID is created.
5. Observe status changes from **Kloner** → **Starter** → **Startet**.
6. Verify no second VM is cloned if a deployment form is submitted twice.
7. Verify the original source template is unchanged.
8. Verify a timeout leaves an **unknown** state rather than replaying the
   mutation. Never clear it without inspecting Proxmox.

Code has been added to GitHub; live Proxmox cloning has not been tested
without an actual connected hypervisor.

Proxmox API: https://pve.proxmox.com/pve-docs/api-viewer/
Official Ubuntu cloud images:
https://cloud-images.ubuntu.com/releases/noble/release/
