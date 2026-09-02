#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export NODEXA_SOURCE_ROOT="$ROOT"
RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'
die(){ echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }
[[ $EUID -eq 0 ]] || die "Run as root (sudo -i)."

case "${1:-}" in
 panel) exec bash "$ROOT/installer/scripts/panel.sh" ;;
 node|agent) exec bash "$ROOT/installer/scripts/node.sh" ;;
 both) exec bash "$ROOT/installer/scripts/both.sh" ;;
 status) systemctl --no-pager --full status nodexa-agent nodexa-queue nodexa-scheduler.timer nodexa-monitor.timer nginx 2>/dev/null || true; exit 0 ;;
 update) exec bash "$ROOT/installer/scripts/update.sh" ;;
 uninstall) exec bash "$ROOT/installer/scripts/uninstall.sh" ;;
 '') ;;
 *) die "Unknown mode: $1" ;;
esac

clear || true
cat <<'BANNER'
 _   _           _
| \ | | ___   __| | _____  ____ _
|  \| |/ _ \ / _` |/ _ \ \/ / _` |
| |\  | (_) | (_| |  __/>  < (_| |
|_| \_|\___/ \__,_|\___/_/\_\__,_|

Game Server Management Platform
BANNER
echo
menu(){
 echo "What would you like to do?"
 echo "  [1] Install Nodexa Panel"
 echo "  [2] Install Nodexa Agent"
 echo "  [3] Install Panel + Agent"
 echo "  [4] Show service status"
 echo "  [5] Update Nodexa"
 echo "  [6] Uninstall Nodexa"
 echo "  [0] Exit"
 read -rp "Select [0-6]: " c
 case "$c" in
  1) bash "$ROOT/installer/scripts/panel.sh" ;;
  2) bash "$ROOT/installer/scripts/node.sh" ;;
  3) bash "$ROOT/installer/scripts/both.sh" ;;
  4) systemctl --no-pager --full status nodexa-agent nodexa-queue nodexa-scheduler.timer nodexa-monitor.timer nginx 2>/dev/null || true ;;
  5) bash "$ROOT/installer/scripts/update.sh" ;;
  6) bash "$ROOT/installer/scripts/uninstall.sh" ;;
  0) exit 0 ;;
  *) die "Invalid selection" ;;
 esac
}
menu
