#!/usr/bin/env bash
# Deploy SatuPerpus ke Hostinger.
#
#   ./deploy.sh setup    Sekali saja: cek SSH & folder subdomain, lalu kirim kode pertama kali
#   ./deploy.sh          Commit & sinkron dengan GitHub, lalu kirim kode ke server
#   ./deploy.sh status   Lihat versi yang sedang berjalan di server
#   ./deploy.sh ssh      Buka terminal server di folder aplikasi
#
# Kode dikirim langsung dari komputer ini ke server lewat SSH (git push),
# jadi server tidak butuh akses ke GitHub. Data SSH disimpan di .deploy.env.
# Berkas ini sengaja hanya memakai karakter ASCII (bash 3.2 bawaan macOS).
set -euo pipefail
cd "$(dirname "$0")"

ENV_FILE=".deploy.env"
BRANCH="main"
APP_URL="https://perpustakaan.daratamalabs.com"
SERVER_REPO="repos/perpustakaan.git"   # repo git di server (relatif dari home), di luar folder web
CTL_PATH="$HOME/.ssh/cm-perpus-%C"      # satu koneksi SSH dipakai bersama: password cukup sekali

if [ -t 1 ]; then G=$'\033[32m'; Y=$'\033[33m'; R=$'\033[31m'; B=$'\033[1m'; D=$'\033[2m'; N=$'\033[0m'; else G=; Y=; R=; B=; D=; N=; fi
say()  { printf '%s==>%s %s\n' "$G" "$N" "$*"; }
warn() { printf '%s !!%s %s\n' "$Y" "$N" "$*"; }
die()  { printf '%sGAGAL: %s%s\n' "$R" "$*" "$N" >&2; exit 1; }

save_env() {
  {
    printf 'DEPLOY_HOST=%q\n' "$DEPLOY_HOST"
    printf 'DEPLOY_PORT=%q\n' "$DEPLOY_PORT"
    printf 'DEPLOY_USER=%q\n' "$DEPLOY_USER"
    printf 'DEPLOY_PATH=%q\n' "$DEPLOY_PATH"
  } > "$ENV_FILE"
  chmod 600 "$ENV_FILE"
}

load_env() {
  if [ ! -f "$ENV_FILE" ]; then
    echo "${B}Data SSH Hostinger${N} ${D}(hPanel > Advanced > SSH Access)${N}"
    read -r -p "  IP / host SSH               : " DEPLOY_HOST
    read -r -p "  Port SSH [65002]            : " DEPLOY_PORT
    read -r -p "  Username SSH (u123456789)   : " DEPLOY_USER
    local def="domains/daratamalabs.com/public_html/perpustakaan"
    echo "  Folder subdomain di server, relatif dari home."
    read -r -p "  [${def}]: " DEPLOY_PATH
    DEPLOY_PORT=${DEPLOY_PORT:-65002}
    DEPLOY_PATH=${DEPLOY_PATH:-$def}
    save_env
    say "Tersimpan di ${ENV_FILE}"
  fi
  # shellcheck disable=SC1090
  . "./$ENV_FILE"
  [ -n "${DEPLOY_HOST:-}" ] && [ -n "${DEPLOY_USER:-}" ] && [ -n "${DEPLOY_PATH:-}" ] || die "Isi ${ENV_FILE} belum lengkap. Hapus berkasnya lalu jalankan lagi."
  DEPLOY_PORT=${DEPLOY_PORT:-65002}
  mkdir -p "$HOME/.ssh"
}

# Jalankan skrip bash (dari stdin) di server; argumen jadi $1, $2, ...
# ControlMaster membuat semua langkah memakai satu koneksi: password cukup sekali.
remote() {
  local args="" a
  for a in "$@"; do args="$args $(printf '%q' "$a")"; done
  ssh -p "$DEPLOY_PORT" -o ServerAliveInterval=20 -o ConnectTimeout=15 \
      -o ControlMaster=auto -o ControlPersist=120 -o "ControlPath=$CTL_PATH" \
      "$DEPLOY_USER@$DEPLOY_HOST" "bash -s --$args"
}

# --- Skrip yang dijalankan di server --------------------------------------------------------

# Setup: siapkan repo git di server dan periksa folder subdomain. $1 folder, $2 repo, $3.. kandidat folder.
REMOTE_PREPARE='
set -e
P="$1"; GR="$2"; shift 2
case "$P" in /*) A="$P" ;; *) A="$HOME/$P" ;; esac
case "$GR" in /*) ;; *) GR="$HOME/$GR" ;; esac
if ! command -v git >/dev/null 2>&1; then echo "NOGIT"; exit 0; fi
if [ ! -d "$GR" ]; then mkdir -p "$GR"; git init -q --bare "$GR"; fi
if [ -d "$A" ]; then echo "DIR: ada"; else echo "DIR: tidak"; fi
for c in "$@"; do if [ -d "$HOME/$c" ]; then echo "CAND: $c"; fi; done
'

# Pasang versi yang baru dikirim (cabang "deploy") ke folder web. $1 folder, $2 repo.
REMOTE_INSTALL='
set -e
P="$1"; GR="$2"
case "$P" in /*) ;; *) P="$HOME/$P" ;; esac
case "$GR" in /*) ;; *) GR="$HOME/$GR" ;; esac
mkdir -p "$P"
cd "$P"
# Sisa cara deploy lama (repo git di folder web yang menarik dari GitHub) dan halaman bawaan Hostinger.
if [ -d .git ] && grep -q "github-perpustakaan" .git/config 2>/dev/null; then rm -rf .git; fi
rm -f default.php
git --git-dir="$GR" --work-tree="$P" checkout -q -f deploy
for d in sessions uploads backups cache; do mkdir -p "storage/$d"; done
chmod 755 storage storage/sessions storage/uploads storage/backups storage/cache
echo "VERSI: $(git --git-dir="$GR" log -1 --format="%h - %s - %cd" --date=format:"%d %b %Y %H:%M" deploy)"
if [ -f config.php ]; then echo "CONFIG: ada"; else echo "CONFIG: belum"; fi
'

# Status: versi yang terpasang. $1 folder, $2 repo.
REMOTE_STATUS='
P="$1"; GR="$2"
case "$P" in /*) ;; *) P="$HOME/$P" ;; esac
case "$GR" in /*) ;; *) GR="$HOME/$GR" ;; esac
if git --git-dir="$GR" rev-parse -q --verify deploy >/dev/null 2>&1; then
  git --git-dir="$GR" log -1 --format="Versi di server : %h - %s - %cd" --date=format:"%d %b %Y %H:%M" deploy
else
  echo "Versi di server : belum ada (jalankan ./deploy.sh setup)"
fi
if [ -f "$P/config.php" ]; then echo "config.php      : ada"; else echo "config.php      : belum (buka /install)"; fi
'

# --- Langkah-langkah ------------------------------------------------------------------------

# Kirim HEAD ke server lalu pasang di folder web.
ship() {
  say "Mengirim kode ke server..."
  local sshcmd
  sshcmd="ssh -p $DEPLOY_PORT -o ServerAliveInterval=20 -o ConnectTimeout=15 -o ControlMaster=auto -o ControlPersist=120 -o ControlPath=$(printf '%q' "$CTL_PATH")"
  GIT_SSH_COMMAND="$sshcmd" git -c push.negotiate=false push -q -f "$DEPLOY_USER@$DEPLOY_HOST:$SERVER_REPO" "HEAD:refs/heads/deploy" \
    || die "Gagal mengirim kode ke server. Jalankan ./deploy.sh setup dulu."
  local out
  out=$(remote "$DEPLOY_PATH" "$SERVER_REPO" <<<"$REMOTE_INSTALL") || die "Kode terkirim, tapi gagal dipasang di folder ${DEPLOY_PATH}."
  report "$out"
}

# Ringkasan setelah server diperbarui. $1 = keluaran dari server.
report() {
  printf '%s\n' "$1" | sed -n 's/^VERSI: /    Versi di server: /p'
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "${APP_URL}/") || true
  code=${code:-000}
  case "$code" in
    200|302) say "Online: ${APP_URL} (HTTP ${code})" ;;
    404) warn "${APP_URL} menjawab 404 dari Hostinger: folder subdomain bukan ${DEPLOY_PATH}. Cek folder di hPanel > Domains > Subdomains, hapus ${ENV_FILE}, lalu ./deploy.sh setup lagi." ;;
    *) warn "${APP_URL} belum bisa dibuka (HTTP ${code}). Cek subdomain & SSL di hPanel, atau buka langsung di browser." ;;
  esac
  if printf '%s\n' "$1" | grep -q '^CONFIG: belum'; then
    warn "Aplikasi belum diinstal. Lanjutkan di browser: ${B}${APP_URL}/install${N}"
  fi
}

# Ambil perubahan terbaru dari GitHub; push hanya jika ada commit lokal baru.
sync_github() {
  say "Sinkron dengan GitHub..."
  git pull -q --rebase origin "$BRANCH" || die "Gagal mengambil dari GitHub. Cek koneksi, atau login dengan: gh auth login"
  if [ -n "$(git rev-list "origin/${BRANCH}..HEAD")" ]; then
    git push -q origin "$BRANCH" || die "Gagal push ke GitHub. Login dulu dengan: gh auth login (pilih HTTPS), lalu ulangi ./deploy.sh"
  fi
}

check_branch() {
  git rev-parse --git-dir >/dev/null 2>&1 || die "Jalankan dari folder repo perpustakaan."
  local cur; cur=$(git rev-parse --abbrev-ref HEAD)
  [ "$cur" = "$BRANCH" ] || die "Sedang di branch '${cur}'. Pindah ke '${BRANCH}' dulu: git checkout ${BRANCH}"
}

cmd_setup() {
  load_env
  check_branch
  say "Memeriksa SSH & folder subdomain di server..."
  local host="${APP_URL#*://}"; host="${host%%/*}"
  local sub="${host%%.*}" parent="${host#*.}"
  local out
  out=$(remote "$DEPLOY_PATH" "$SERVER_REPO" "domains/${host}/public_html" "domains/${parent}/public_html/${sub}" "public_html/${sub}" <<<"$REMOTE_PREPARE") \
    || die "Tidak bisa masuk SSH ke server. Cek data di ${ENV_FILE} dan pastikan SSH Access aktif di hPanel."
  printf '%s\n' "$out" | grep -q '^NOGIT' && die "Perintah git tidak tersedia di server."

  local best; best=$(printf '%s\n' "$out" | sed -n 's/^CAND: //p' | head -n 1)
  if [ -n "$best" ] && [ "$best" != "$DEPLOY_PATH" ]; then
    warn "Folder subdomain yang ada di server: ${B}${best}${N} (tersimpan: ${DEPLOY_PATH})"
    local yn; read -r -p "Pakai folder ${best}? [Y/n] " yn
    case "$yn" in n|N) ;; *) DEPLOY_PATH="$best"; save_env ;; esac
  elif [ -z "$best" ] && printf '%s\n' "$out" | grep -q '^DIR: tidak'; then
    die "Folder ${DEPLOY_PATH} tidak ada di server. Lihat folder subdomain di hPanel > Domains > Subdomains, hapus ${ENV_FILE}, lalu ./deploy.sh setup lagi."
  fi
  say "Folder aplikasi: ${DEPLOY_PATH}"
  ship
}

cmd_deploy() {
  load_env
  check_branch
  if [ -n "$(git status --porcelain)" ]; then
    warn "Ada perubahan yang belum di-commit:"
    git status --short
    local msg
    read -r -p "Pesan commit [Pembaruan $(date '+%d-%m-%Y %H:%M')]: " msg
    msg=${msg:-"Pembaruan $(date '+%d-%m-%Y %H:%M')"}
    git add -A
    git commit -q -m "$msg"
  fi
  sync_github
  ship
}

cmd_status() {
  load_env
  remote "$DEPLOY_PATH" "$SERVER_REPO" <<<"$REMOTE_STATUS"
  echo "Versi lokal     : $(git log -1 --format='%h - %s')"
}

cmd_ssh() {
  load_env
  ssh -t -p "$DEPLOY_PORT" -o ControlMaster=auto -o ControlPersist=120 -o "ControlPath=$CTL_PATH" \
      "$DEPLOY_USER@$DEPLOY_HOST" "cd $(printf '%q' "$DEPLOY_PATH") 2>/dev/null; exec \$SHELL -l"
}

case "${1:-deploy}" in
  setup)  cmd_setup ;;
  deploy) cmd_deploy ;;
  status) cmd_status ;;
  ssh)    cmd_ssh ;;
  -h|--help|help) sed -n '2,11p' "$0" | sed 's/^# \{0,1\}//' ;;
  *) die "Perintah tidak dikenal: $1 (pakai: setup, status, ssh, atau tanpa argumen)" ;;
esac
