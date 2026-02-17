#!/bin/bash
# =============================================================================
# rename-vm.sh — Rename hostname and username on Ubuntu
# Usage: sudo bash rename-vm.sh
# =============================================================================

set -e

# --- Colour helpers ----------------------------------------------------------
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*"; }

# --- Must run as root --------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    error "This script must be run as root. Try:  sudo bash $0"
    exit 1
fi

echo -e "\n${BOLD}========================================${RESET}"
echo -e "${BOLD}       Ubuntu VM Rename Utility         ${RESET}"
echo -e "${BOLD}========================================${RESET}\n"

# =============================================================================
# 1. HOSTNAME
# =============================================================================
CURRENT_HOSTNAME=$(hostname)
info "Current hostname: ${BOLD}${CURRENT_HOSTNAME}${RESET}"
echo

while true; do
    read -rp "$(echo -e ${BOLD}Enter new hostname:${RESET} ) " NEW_HOSTNAME
    # Validate: letters, numbers, hyphens; no leading/trailing hyphens; max 63 chars
    if [[ -z "$NEW_HOSTNAME" ]]; then
        error "Hostname cannot be empty."
    elif [[ ! "$NEW_HOSTNAME" =~ ^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$ ]]; then
        error "Invalid hostname. Use only letters, numbers, and hyphens (not at start/end). Max 63 chars."
    else
        break
    fi
done

# =============================================================================
# 2. USERNAME
# =============================================================================
echo

# Detect the non-root user who invoked sudo (fallback: list human users)
if [[ -n "$SUDO_USER" && "$SUDO_USER" != "root" ]]; then
    CURRENT_USER="$SUDO_USER"
else
    # Pick the first non-root account with a home in /home
    CURRENT_USER=$(awk -F: '$6 ~ /^\/home/ && $3 >= 1000 {print $1; exit}' /etc/passwd)
fi

info "Current username: ${BOLD}${CURRENT_USER}${RESET}"
echo

while true; do
    read -rp "$(echo -e ${BOLD}Enter new username:${RESET} ) " NEW_USER
    if [[ -z "$NEW_USER" ]]; then
        error "Username cannot be empty."
    elif [[ ! "$NEW_USER" =~ ^[a-z_][a-z0-9_-]{0,31}$ ]]; then
        error "Invalid username. Use lowercase letters, numbers, underscores, hyphens. Must start with a letter or underscore. Max 32 chars."
    elif id "$NEW_USER" &>/dev/null; then
        error "User '${NEW_USER}' already exists. Choose a different name."
    else
        break
    fi
done

# =============================================================================
# 3. CONFIRM
# =============================================================================
echo
echo -e "${BOLD}--- Pending changes ---${RESET}"
echo -e "  Hostname : ${YELLOW}${CURRENT_HOSTNAME}${RESET}  →  ${GREEN}${NEW_HOSTNAME}${RESET}"
echo -e "  Username : ${YELLOW}${CURRENT_USER}${RESET}  →  ${GREEN}${NEW_USER}${RESET}"
echo -e "  Home dir : ${YELLOW}/home/${CURRENT_USER}${RESET}  →  ${GREEN}/home/${NEW_USER}${RESET}"
echo

read -rp "$(echo -e ${BOLD}Apply these changes? [y/N]:${RESET} ) " CONFIRM
if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    warn "Aborted. No changes were made."
    exit 0
fi

echo

# =============================================================================
# 4. APPLY HOSTNAME CHANGE
# =============================================================================
info "Setting hostname to '${NEW_HOSTNAME}'..."

hostnamectl set-hostname "$NEW_HOSTNAME"

# Update /etc/hosts — replace old hostname references
if grep -q "$CURRENT_HOSTNAME" /etc/hosts; then
    sed -i "s/\b${CURRENT_HOSTNAME}\b/${NEW_HOSTNAME}/g" /etc/hosts
    success "/etc/hosts updated."
else
    warn "Old hostname not found in /etc/hosts — no changes needed there."
fi

success "Hostname changed to '${NEW_HOSTNAME}'."

# =============================================================================
# 5. APPLY USERNAME CHANGE
# =============================================================================
info "Renaming user '${CURRENT_USER}' to '${NEW_USER}'..."

OLD_HOME="/home/${CURRENT_USER}"
NEW_HOME="/home/${NEW_USER}"

# Rename the login name
usermod -l "$NEW_USER" "$CURRENT_USER"

# Rename the primary group if it matches the old username
if getent group "$CURRENT_USER" &>/dev/null; then
    groupmod -n "$NEW_USER" "$CURRENT_USER"
    success "Primary group renamed to '${NEW_USER}'."
fi

# Move and rename the home directory
if [[ -d "$OLD_HOME" ]]; then
    usermod -d "$NEW_HOME" -m "$NEW_USER"
    success "Home directory moved: ${OLD_HOME} → ${NEW_HOME}"
else
    warn "Old home directory '${OLD_HOME}' not found — skipping home move."
fi

# Update GECOS (display name) to new username
chfn -f "$NEW_USER" "$NEW_USER" 2>/dev/null || true

success "Username changed to '${NEW_USER}'."

# =============================================================================
# 6. UPDATE SUDOERS IF NEEDED
# =============================================================================
SUDOERS_FILE="/etc/sudoers.d/${CURRENT_USER}"
if [[ -f "$SUDOERS_FILE" ]]; then
    sed -i "s/\b${CURRENT_USER}\b/${NEW_USER}/g" "$SUDOERS_FILE"
    mv "$SUDOERS_FILE" "/etc/sudoers.d/${NEW_USER}"
    success "Sudoers entry updated."
fi

# Also check the main sudoers file
if grep -q "^${CURRENT_USER}" /etc/sudoers 2>/dev/null; then
    sed -i "s/^${CURRENT_USER}\b/${NEW_USER}/" /etc/sudoers
    success "Main /etc/sudoers updated."
fi

# =============================================================================
# 7. DONE
# =============================================================================
echo
echo -e "${GREEN}${BOLD}========================================${RESET}"
echo -e "${GREEN}${BOLD}         All changes applied!           ${RESET}"
echo -e "${GREEN}${BOLD}========================================${RESET}"
echo
echo -e "  New hostname : ${GREEN}${NEW_HOSTNAME}${RESET}"
echo -e "  New username : ${GREEN}${NEW_USER}${RESET}"
echo -e "  New home dir : ${GREEN}${NEW_HOME}${RESET}"
echo
warn "A reboot is recommended for all changes to take full effect."
echo
read -rp "$(echo -e ${BOLD}Reboot now? [y/N]:${RESET} ) " REBOOT
if [[ "$REBOOT" =~ ^[Yy]$ ]]; then
    info "Rebooting..."
    reboot
else
    warn "Remember to reboot before deploying this VM."
fi