#!/usr/bin/env bash
set -euo pipefail
base=/var/www/vietnamskilled.org
name=vietnamskilled.org
source_dir=$(cd -- "$(dirname -- "$0")/.." && pwd)
if [ "$#" -ne 1 ]; then echo "Usage: sudo bash deploy/install.sh /run/php/phpX.Y-fpm.sock"; exit 1; fi
socket=$1
if [ "$(id -u)" -ne 0 ] || [[ ! "$socket" =~ ^/(var/)?run/php/php[0-9]+\.[0-9]+-fpm\.sock$ ]] || [ ! -S "$socket" ]; then
    echo "Run as root with an existing PHP-FPM socket." >&2
    exit 1
fi
config=/etc/nginx/sites-available/$name.conf
enabled=/etc/nginx/sites-enabled/$name.conf
for path in "$base" "$config" "$enabled"; do
    if [ -e "$path" ] || [ -L "$path" ]; then
        echo "Installation paths already exist. Follow the restore instructions." >&2
        exit 1
    fi
done
command -v nginx >/dev/null
getent passwd www-data >/dev/null
for ext in curl dom fileinfo iconv json mbstring openssl; do
    php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$ext" || {
        printf 'Missing PHP extension: %s\n' "$ext" >&2
        exit 1
    }
done
install -d -m 0755 "$base"
cp -a "$source_dir"/{public_html,cms,vendor,cms-data} "$base/"
install -d -o www-data -g www-data -m 0750 "$base/cms-data/media" "$base/vs_backups"
chown -R www-data:www-data "$base/cms-data"
chmod 0750 "$base/cms-data"
chmod 0640 "$base/cms-data/content.json"
install -o root -g www-data -m 0640 "$source_dir/config.example.php" "$base/config.php"
for file in index.html dich-vu/index.html ve-chung-toi/index.html lien-he/index.html; do
    chown www-data:www-data "$base/public_html/$file"
done
sed "s|/var/run/php/php7.4-fpm.sock|$socket|g" "$source_dir/deploy/nginx-http.conf" > "$config"
ln -s "$config" "$enabled"
if ! nginx -t; then
    unlink "$enabled"
    echo "Nginx test failed. Site configuration was disabled." >&2
    exit 1
fi
systemctl reload nginx
echo "HTTP site installed. Finish TLS and configuration as described in README.md."

