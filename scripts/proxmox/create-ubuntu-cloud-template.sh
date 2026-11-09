#!/usr/bin/env bash
# Proxmox VE helper: create a real Ubuntu QEMU cloud-image template.
# Run locally on a Proxmox node, not on the Nodexa webserver.
set -Eeuo pipefail
usage() {
cat <<'HELP'
On a Proxmox VE node (as root):
  bash create-ubuntu-cloud-template.sh ubuntu-2404 9000 local-lvm vmbr0 /root/.ssh/id_ed25519.pub
  bash create-ubuntu-cloud-template.sh ubuntu-2204 9001 local-lvm vmbr0 /root/.ssh/id_ed25519.pub

Arguments: OS VMID STORAGE BRIDGE SSH_PUBLIC_KEY_FILE
  OS: ubuntu-2404 or ubuntu-2204
  VMID: unused 100-999999999
  STORAGE: Proxmox VM disk storage with cloud-init volume support
  BRIDGE: existing Proxmox bridge, for example vmbr0
  SSH_PUBLIC_KEY_FILE: existing SSH public key (not a private key)

VM template defaults: 2 vCPU, 2 GB RAM, DHCP, cloud-init ubuntu user.
The script checks SHA256SUMS supplied by official Ubuntu image repository.
No existing VM will be overwritten or deleted.
HELP
}
if [[ $# -ne 5 ]]; then usage; exit 2; fi
if [[ "$(id -u)" -ne 0 ]]; then echo "Kør som root på Proxmox-host." >&2; exit 1; fi
for cmd in qm curl sha256sum pvesm; do
    command -v "$cmd" >/dev/null || { echo "$cmd mangler." >&2; exit 1; }
done
OS="$1"; VMID="$2"; STORAGE="$3"; BRIDGE="$4"; SSH_KEY="$5"
case "$OS" in
    ubuntu-2404) URL_ROOT="https://cloud-images.ubuntu.com/releases/noble/release"; IMAGE="ubuntu-24.04-server-cloudimg-amd64.img";;
    ubuntu-2204) URL_ROOT="https://cloud-images.ubuntu.com/releases/jammy/release"; IMAGE="ubuntu-22.04-server-cloudimg-amd64.img";;
    *) echo "Ukendt OS: $OS" >&2; usage; exit 2 ;;
esac
if [[ ! "$VMID" =~ ^[0-9]+$ ]] || (( VMID < 100 || VMID > 999999999 )); then
    echo "Ugyldigt VMID: $VMID" >&2; exit 1
fi
if [[ ! "$STORAGE" =~ ^[a-zA-Z0-9_-]+$ ]] || [[ ! "$BRIDGE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
    echo "STORAGE eller BRIDGE indeholder ugyldige tegn." >&2; exit 1
fi
if [[ ! -f "$SSH_KEY" ]] || ! grep -Eq '^(ssh-ed25519|ssh-rsa|ecdsa-sha2-) ' "$SSH_KEY"; then
    echo "Angiv en eksisterende SSH public key til cloud-init." >&2; exit 1
fi
if qm status "$VMID" >/dev/null 2>&1; then
    echo "VMID $VMID findes allerede. Afbryder uden ændringer." >&2; exit 1
fi
if ! pvesm status --storage "$STORAGE" >/dev/null 2>&1; then
    echo "Storage $STORAGE er ikke tilgængelig." >&2; exit 1
fi
if [[ ! -d "/sys/class/net/$BRIDGE" ]]; then
    echo "Netværksinterface $BRIDGE findes ikke på denne Proxmox-node." >&2; exit 1
fi
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
echo "Henter officielt Ubuntu-image og checksum..."
curl --fail --location --silent --show-error --retry 2 --max-time 1800 "$URL_ROOT/$IMAGE" --output "$tmp/$IMAGE"
curl --fail --location --silent --show-error --retry 2 --max-time 60 "$URL_ROOT/SHA256SUMS" --output "$tmp/SHA256SUMS"
checksum="$(awk -v image="$IMAGE" '$2 == image || $2 == "*" image {print $1; exit}' "$tmp/SHA256SUMS")"
if [[ ! "$checksum" =~ ^[0-9a-fA-F]{64}$ ]]; then
    echo "Ingen verificerbar SHA256-checksum fundet. Ingen VM oprettet." >&2; exit 1
fi
echo "$checksum  $tmp/$IMAGE" | sha256sum --check --status - || {
    echo "SHA256-kontrol fejlede. Ingen VM oprettet." >&2; exit 1
}
if qm status "$VMID" >/dev/null 2>&1; then
    echo "VMID $VMID blev taget under download. Stopper." >&2; exit 1
fi
echo "Opretter QEMU VM $VMID på storage $STORAGE..."
# If a later step fails, the partially created VM is left for inspection;
# intentionally do not remove disks or VMs automatically.
qm create "$VMID" --name "nodexa-$OS-template" \
    --cores 2 --memory 2048 --cpu host \
    --scsihw virtio-scsi-pci \
    --net0 "virtio,bridge=$BRIDGE" \
    --agent enabled=1 --serial0 socket --vga serial0 --ostype l26
qm importdisk "$VMID" "$tmp/$IMAGE" "$STORAGE"
disk="$(qm config "$VMID" | awk '/^unused[0-9]+:/{print $2; exit}')"
if [[ -z "$disk" ]]; then
    echo "Den importerede disk blev ikke fundet. Kontrollér VM $VMID manuelt." >&2; exit 1
fi
qm set "$VMID" --scsi0 "$disk"
qm set "$VMID" --ide2 "$STORAGE:cloudinit"
qm set "$VMID" --boot order=scsi0
qm set "$VMID" --ciuser ubuntu
qm set "$VMID" --sshkeys "$SSH_KEY"
qm set "$VMID" --ipconfig0 ip=dhcp
qm template "$VMID"
echo "KLAR: $OS er nu en template med VMID $VMID."
echo "Registrér den under Nodexa > Admin > Proxmox VE > VM Templates."
echo "Kloner arver SSH-nøglen: sørg for særskilt adgang før salg til kunder."
