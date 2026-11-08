#!/usr/bin/env bash
# Deploy Perpustakaan Digital ke Hostinger (SSH + git).
#
#   ./deploy.sh setup    Sekali saja: siapkan kunci deploy & ambil kode pertama kali di server
#   ./deploy.sh          Kirim perubahan ke GitHub lalu perbarui server
#   ./deploy.sh status   Lihat versi yang sedang berjalan di server
#   ./deploy.sh ssh      Buka terminal server di folder aplikasi
#
# Data SSH disimpan di .deploy.env (tidak ikut ke GitHub).
set -euo pipefail
cd "$(dirname "$0")"

ENV_FILE=".deploy.env"
BRANCH="main"
GITHUB_REPO="daratamalabs/perpustakaan"
GITHUB_ALIAS="github-perpustakaan"          # alias SSH di server, memakai kunci deploy khusus repo ini
APP_URL="https://perpustakaan.daratamalabs.com"

if [ -t 1 ]; then G=$'\033[32m'; Y=$'\033[33m'; R=$'\033[31m'; B=$'\033[1m'; D=$'\033[2m'; N=$'\033[0m'; else G=; Y=; R=; B=; D=; N=; fi
say()  { printf '%s▸%s %s\n' "$G" "$N" "$*"; }
warn() { printf '%s!%s %s\n' "$Y" "$N" "$*"; }
die()  { printf '%s✗ %s%s\n' "$R" "$*" "$N" >&2; exit 1; }

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
    echo "${B}Data SSH Hostinger${N} ${D}(hPanel → Advanced → SSH Access)${N}"
    read -r -p "  IP / host SSH               : " DEPLOY_HOST
    read -r -p "  Port SSH [65002]            : " DEPLOY_PORT
    read -r -p "  Username SSH (u123456789)   : " DEPLOY_USER
    local def="domains/daratamalabs.com/public_html/perpustakaan"
    echo "  Folder subdomain di server, relatif dari home."
    read -r -p "  [$def]: " DEPLOY_PATH
    DEPLOY_PORT=${DEPLOY_PORT:-65002}
    DEPLOY_PATH=${DEPLOY_PATH:-$def}
    save_env
    say "Tersimpan di $ENV_FILE"
  fi
  # shellcheck disable=SC1090
  . "./$ENV_FILE"
  [ -n "${DEPLOY_HOST:-}" ] && [ -n "${DEPLOY_USER:-}" ] && [ -n "${DEPLOY_PATH:-}" ] || die "Isi $ENV_FILE belum lengkap. Hapus berkasnya lalu jalankan lagi."
  DEPLOY_PORT=${DEPLOY_PORT:-65002}
}

# Jalankan skrip bash di server. Argumen setelah nama fungsi diteruskan sebagai $1, $2, ...
remote() {
  local args="" a
  for a in "$@"; do args="$args $(printf '%q' "$a")"; done
  ssh -p "$DEPLOY_PORT" -o ServerAliveInterval=20 -o ConnectTimeout=15 "$DEPLOY_USER@$DEPLOY_HOST" "bash -s --$args"
}

# Bagian bersama di server: ambil kode terbaru dari GitHub, siapkan folder storage.
REMOTE_UPDATE='
set -e
P="$1"; BRANCH="$2"
case "$P" in /*) ;; *) P="$HOME/$P" ;; esac
cd "$P"
git fetch -q --depth 1 origin "$BRANCH"
git reset -q --hard FETCH_HEAD
for d in sessions uploads backups cache; do mkdir -p "storage/$d"; done
chmod 755 storage storage/sessions storage/uploads storage/backups storage/cache
echo "VERSI: $(git log -1 --format="%h · %s · %cd" --date=format:"%d %b %Y %H:%M")"
if [ -f config.php ]; then echo "CONFIG: ada"; else echo "CONFIG: belum"; fi
'

# Sekali saat setup: jadikan folder subdomain sebuah repo git yang menunjuk ke GitHub.
REMOTE_INIT='
set -e
P="$1"; REPO="$3"
case "$P" in /*) ;; *) P="$HOME/$P" ;; esac
mkdir -p "$P" && cd "$P"
if [ ! -d .git ]; then
  rm -f default.php
  git init -q
  git remote add origin "$REPO"
else
  git remote set-url origin "$REPO"
fi
'

# Setup langkah 1: kunci deploy khusus repo ini + cek folder. $1 alias, $2 folder tersimpan, $3.. kandidat.
REMOTE_KEYS='
set -e
ALIAS="$1"; P="$2"; shift 2
KEY="$HOME/.ssh/perpustakaan_deploy"
mkdir -p "$HOME/.ssh" && chmod 700 "$HOME/.ssh"
[ -f "$KEY" ] || ssh-keygen -q -t ed25519 -N "" -C "perpustakaan-deploy" -f "$KEY"
if ! grep -q "^Host $ALIAS\$" "$HOME/.ssh/config" 2>/dev/null; then
  printf "\nHost %s\n  HostName github.com\n  User git\n  IdentityFile %s\n  IdentitiesOnly yes\n" "$ALIAS" "$KEY" >> "$HOME/.ssh/config"
fi
chmod 600 "$HOME/.ssh/config"
ssh-keygen -F github.com >/dev/null 2>&1 || ssh-keyscan -t ed25519,rsa github.com >> "$HOME/.ssh/known_hosts" 2>/dev/null || true
echo "PUBKEY: $(cat "$KEY.pub")"
case "$P" in /*) A="$P" ;; *) A="$HOME/$P" ;; esac
if [ -d "$A" ]; then echo "DIR: ada"; else echo "DIR: tidak"; fi
for c in "$@"; do if [ -d "$HOME/$c" ]; then echo "CAND: $c"; fi; done
'

# Ringkasan setelah server diperbarui. $1 = keluaran dari server.
report() {
  printf '%s\n' "$1" | sed -n 's/^VERSI: /  Versi di server: /p'
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$APP_URL/") || true
  code=${code:-000}
  case "$code" in
    200|302) say "Online: $APP_URL (HTTP $code)" ;;
    404) warn "$APP_URL menjawab 404 dari Hostinger: folder subdomain bukan $DEPLOY_PATH. Jalankan ./deploy.sh setup lagi, atau cek hPanel → Domains → Subdomains." ;;
    *) warn "$APP_URL belum bisa dibuka (HTTP $code). Cek subdomain & SSL di hPanel, atau buka langsung di browser." ;;
  esac
  if printf '%s\n' "$1" | grep -q '^CONFIG: belum'; then
    warn "Aplikasi belum diinstal. Lanjutkan di browser: ${B}$APP_URL/install${N}"
  fi
}

cmd_setup() {
  load_env
  say "Menyiapkan kunci deploy & memeriksa folder subdomain di server…"
  # Folder yang biasa dipakai Hostinger untuk subdomain, urut dari yang paling spesifik.
  local host="${APP_URL#*://}"; host="${host%%/*}"
  local sub="${host%%.*}" parent="${host#*.}"
  local keyout
  keyout=$(remote "$GITHUB_ALIAS" "$DEPLOY_PATH" "domains/$host/public_html" "domains/$parent/public_html/$sub" "public_html/$sub" <<<"$REMOTE_KEYS") \
    || die "Tidak bisa masuk SSH ke server. Cek data di $ENV_FILE dan pastikan SSH Access aktif di hPanel."

  local best; best=$(printf '%s\n' "$keyout" | sed -n 's/^CAND: //p' | head -n 1)
  if [ -n "$best" ] && [ "$best" != "$DEPLOY_PATH" ]; then
    warn "Folder subdomain yang ada di server: ${B}$best${N} (tersimpan: $DEPLOY_PATH)"
    local yn; read -r -p "Pakai folder $best? [Y/n] " yn
    case "$yn" in n|N) ;; *) DEPLOY_PATH="$best"; save_env; say "Folder deploy: $DEPLOY_PATH" ;; esac
  elif [ -z "$best" ] && printf '%s\n' "$keyout" | grep -q '^DIR: tidak'; then
    die "Folder $DEPLOY_PATH tidak ada di server. Lihat folder subdomain di hPanel → Domains → Subdomains, lalu hapus $ENV_FILE dan jalankan ./deploy.sh setup lagi."
  fi
  printf '%s\n' "$keyout" | grep '^PUBKEY:'
  echo
  echo "${B}Tambahkan kunci di atas (baris setelah PUBKEY:) ke GitHub:${N}"
  echo "  https://github.com/$GITHUB_REPO/settings/keys → Add deploy key"
  echo "  Title: Hostinger, Key: tempel kuncinya, biarkan 'Allow write access' TIDAK dicentang."
  read -r -p "Tekan Enter setelah kunci ditambahkan… " _

  say "Mengambil kode ke $DEPLOY_PATH…"
  local out
  out=$(remote "$DEPLOY_PATH" "$BRANCH" "git@$GITHUB_ALIAS:$GITHUB_REPO.git" <<<"$REMOTE_INIT
$REMOTE_UPDATE") || die "Gagal mengambil kode dari GitHub. Pastikan deploy key sudah ditambahkan, lalu jalankan ./deploy.sh setup lagi."
  report "$out"
}

cmd_deploy() {
  load_env
  git rev-parse --git-dir >/dev/null 2>&1 || die "Jalankan dari folder repo perpustakaan."
  local cur; cur=$(git rev-parse --abbrev-ref HEAD)
  [ "$cur" = "$BRANCH" ] || die "Sedang di branch '$cur'. Pindah ke '$BRANCH' dulu: git checkout $BRANCH"

  if [ -n "$(git status --porcelain)" ]; then
    warn "Ada perubahan yang belum di-commit:"
    git status --short
    local msg
    read -r -p "Pesan commit [Pembaruan $(date '+%d-%m-%Y %H:%M')]: " msg
    msg=${msg:-"Pembaruan $(date '+%d-%m-%Y %H:%M')"}
    git add -A
    git commit -q -m "$msg"
  fi

  say "Sinkron dengan GitHub…"
  git pull -q --rebase origin "$BRANCH" || die "Gagal mengambil dari GitHub. Cek koneksi atau login GitHub (gh auth login)."
  if [ -n "$(git rev-list "origin/$BRANCH..HEAD")" ]; then
    git push -q origin "$BRANCH" || die "Gagal push ke GitHub. Login dulu dengan: gh auth login (pilih HTTPS), lalu ulangi ./deploy.sh"
  fi

  say "Memperbarui server…"
  local out
  out=$(remote "$DEPLOY_PATH" "$BRANCH" <<<"$REMOTE_UPDATE") || die "Gagal memperbarui server. Sudah menjalankan ./deploy.sh setup?"
  report "$out"
}

cmd_status() {
  load_env
  remote "$DEPLOY_PATH" <<'REMOTE'
P="$1"; case "$P" in /*) ;; *) P="$HOME/$P" ;; esac
cd "$P" 2>/dev/null || { echo "Folder belum ada. Jalankan ./deploy.sh setup"; exit 1; }
git log -1 --format="Versi di server: %h · %s · %cd" --date=format:"%d %b %Y %H:%M"
[ -f config.php ] && echo "config.php: ada" || echo "config.php: belum (buka /install)"
REMOTE
  echo "Versi lokal     : $(git log -1 --format='%h · %s')"
}

cmd_ssh() {
  load_env
  ssh -t -p "$DEPLOY_PORT" "$DEPLOY_USER@$DEPLOY_HOST" "cd $(printf '%q' "$DEPLOY_PATH") 2>/dev/null; exec \$SHELL -l"
}

case "${1:-deploy}" in
  setup)  cmd_setup ;;
  deploy) cmd_deploy ;;
  status) cmd_status ;;
  ssh)    cmd_ssh ;;
  -h|--help|help) sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//' ;;
  *) die "Perintah tidak dikenal: $1 (pakai: setup, status, ssh, atau tanpa argumen)" ;;
esac
