# Deploying to Ubuntu 24.04 with Apache

One server running everything: Apache 2.4 with PHP 8.4-FPM, MySQL 8, Redis, a Supervisor-managed queue worker and the cron scheduler. HTTPS comes from Let's Encrypt.

> **Status:** written from the code and standard Ubuntu 24.04 packages. It has **not yet been run end to end on a live server.** Run it on a staging box first, and fix anything that differs here.

Placeholders used throughout:

| Placeholder | Meaning |
|---|---|
| `example.co.ke` | The player site's domain (`APP_URL`). |
| `admin.example.co.ke` | The admin panel's domain (`ADMIN_DOMAIN`). |
| `deploy` | The Linux user that owns the code and runs deploys. |
| `/var/www/sportsbook` | Where the app lives. |
| `git@github.com:OWNER/REPO.git` | The repository. |

Contents:

1. [Server and DNS](#1-server-and-dns)
2. [Base system](#2-base-system)
3. [PHP 8.4, Apache, MySQL, Redis, Node](#3-php-84-apache-mysql-redis-node)
4. [Database](#4-database)
5. [Get the code](#5-get-the-code)
6. [The .env file](#6-the-env-file)
7. [Install, migrate and seed](#7-install-migrate-and-seed)
8. [Apache virtual hosts](#8-apache-virtual-hosts)
9. [HTTPS](#9-https)
10. [Queue worker (Supervisor)](#10-queue-worker-supervisor)
11. [Scheduler (cron)](#11-scheduler-cron)
12. [PHP and OPcache tuning](#12-php-and-opcache-tuning)
13. [M-Pesa go-live](#13-m-pesa-go-live)
14. [Odds feed and first data](#14-odds-feed-and-first-data)
15. [First admin](#15-first-admin)
16. [Go-live checklist](#16-go-live-checklist)
17. [Deploying updates](#17-deploying-updates)
18. [Backups](#18-backups)
19. [Monitoring and troubleshooting](#19-monitoring-and-troubleshooting)
20. [Known gaps](#20-known-gaps)

---

## 1. Server and DNS

- **Size:** start with 2 vCPU, 4 GB RAM and 40 GB SSD. Building the frontend needs about 1 GB of memory; the swap step below covers smaller boxes.
- **Location:** a Nairobi or nearby region keeps M-Pesa callbacks and players' requests fast.
- **DNS:** point A (and AAAA, if you have IPv6) records for `example.co.ke` and `admin.example.co.ke` at the server's IP. Do this first, because certbot needs them resolving.

## 2. Base system

As root, or a sudo user, on a fresh Ubuntu 24.04:

```bash
apt update && apt upgrade -y
apt install -y software-properties-common curl git unzip acl ufw unattended-upgrades

# Keep the server clock in UTC (the app stores UTC and shows EAT itself).
timedatectl set-timezone UTC

# The deploy user, with your SSH key.
adduser --disabled-password --gecos "" deploy
usermod -aG www-data deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh

# Firewall: SSH and web only.
ufw allow OpenSSH
ufw allow "Apache Full"   # this profile exists once Apache is installed (step 3); run it then if it errors now
ufw --force enable

# Security updates install themselves.
dpkg-reconfigure -plow unattended-upgrades
```

Turn off SSH password login: set `PasswordAuthentication no` in `/etc/ssh/sshd_config`, then `systemctl reload ssh`.

On a box with less than 4 GB RAM, add swap so `npm run build` doesn't run out of memory:

```bash
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

## 3. PHP 8.4, Apache, MySQL, Redis, Node

Ubuntu 24.04 ships PHP 8.3. The app is developed and tested on 8.4, so install 8.4 from the ondrej PPA:

```bash
add-apt-repository -y ppa:ondrej/php
apt update

apt install -y apache2 \
  php8.4-fpm php8.4-cli php8.4-common php8.4-opcache php8.4-readline \
  php8.4-mysql php8.4-redis php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-intl php8.4-bcmath php8.4-gd \
  mysql-server redis-server supervisor \
  certbot python3-certbot-apache

# Apache talks to PHP-FPM; no mod_php.
a2dismod mpm_prefork 2>/dev/null || true
a2enmod mpm_event proxy_fcgi setenvif rewrite headers ssl http2
a2enconf php8.4-fpm
systemctl restart apache2

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node 22 LTS (Vite 8 needs Node 20.19+ or 22.12+)
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
```

Check what you got:

```bash
php -v          # 8.4.x
php -m | grep -E 'intl|redis|zip|pdo_mysql|mbstring|opcache'
node -v         # v22.x
mysql --version # 8.0.x
redis-cli ping  # PONG
```

Redis listens only on localhost by default (`bind 127.0.0.1 -::1` in `/etc/redis/redis.conf`). Keep it that way. Also set `supervised systemd` there, then `systemctl restart redis-server`.

## 4. Database

```bash
mysql_secure_installation   # remove anonymous users and the test database; root keeps socket auth
mysql
```

```sql
CREATE DATABASE sportsbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sportsbook'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON sportsbook.* TO 'sportsbook'@'localhost';
FLUSH PRIVILEGES;
```

For a 4 GB server, give InnoDB about a quarter of memory. In `/etc/mysql/mysql.conf.d/mysqld.cnf`, under `[mysqld]`:

```ini
innodb_buffer_pool_size = 1G
```

Then `systemctl restart mysql`.

## 5. Get the code

```bash
mkdir -p /var/www/sportsbook
chown deploy:www-data /var/www/sportsbook

sudo -iu deploy
ssh-keygen -t ed25519 -C "deploy@example.co.ke"   # add ~/.ssh/id_ed25519.pub to the repo as a read-only deploy key
git clone git@github.com:OWNER/REPO.git /var/www/sportsbook
cd /var/www/sportsbook
```

Let the web server write to `storage` and `bootstrap/cache`, and let the deploy user keep writing there too (as root):

```bash
cd /var/www/sportsbook
setfacl -R  -m u:www-data:rwX -m u:deploy:rwX storage bootstrap/cache
setfacl -dR -m u:www-data:rwX -m u:deploy:rwX storage bootstrap/cache
```

PHP-FPM, the queue worker and the scheduler all run as `www-data`. Files they create (logs, exports, caches) stay writable by both users because of the default ACLs above.

## 6. The .env file

```bash
cp .env.example .env
chmod 640 .env && chown deploy:www-data .env
```

Fill it in. Every key the app reads, grouped:

### App

```dotenv
APP_NAME="Your Brand"
APP_ENV=production
APP_KEY=                       # filled by `php artisan key:generate` in step 7
APP_DEBUG=false                # never true in production: it shows secrets on error pages
APP_URL=https://example.co.ke  # exact public URL; passkeys and M-Pesa callback URLs are built from it

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=warning
```

### Database, cache, sessions, queue

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sportsbook
DB_USERNAME=sportsbook
DB_PASSWORD=a-long-random-password
# DB_REPORTS_HOST=             # only with a read replica for admin reports; leave unset on one server

CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=null            # keep null so the site and admin panel get separate cookies
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
```

Why these choices:

- **Cache in Redis.** Rate limits, unique-job and `onOneServer` locks, and other cached values are read on most requests. Redis keeps that load off MySQL.
- **Queue in the database.** Withdrawal payouts go through the queue and are never retried, because a retry could pay twice. A database queue survives a Redis restart. On one server, the extra MySQL load is small.
- **Sessions in the database.** A Redis restart doesn't log everyone out, and the `sessions` table already exists.

### Brand, compliance, admin

```dotenv
ADMIN_DOMAIN=admin.example.co.ke
SPORTSBOOK_LICENCE_TEXT="Licensed by the BCLB, licence no. ..."   # must match the licence exactly; shown in the footer
```

### SMS (TextSMS)

```dotenv
SMS_DRIVER=textsms             # `log` is refused in production
TEXTSMS_API_KEY=
TEXTSMS_PARTNER_ID=
TEXTSMS_SHORTCODE=
```

### Odds feed (The Odds API)

```dotenv
ODDS_API_KEY=                  # keep out of git
ODDS_API_DRIVER=live           # `replay` serves recorded responses: local development only
ODDS_API_REGION=uk
ODDS_API_MARKETS=h2h
ODDS_API_MONTHLY_CREDIT_CAP=500  # match your plan; the sync stops before this
ODDS_API_CREDIT_RESERVE=50       # kept back for settlement's score calls
ODDS_API_LOOKAHEAD_DAYS=7
```

### M-Pesa (Daraja)

```dotenv
MPESA_ENV=production
MPESA_CALLBACK_SECRET=         # `openssl rand -hex 32`; part of every callback URL
MPESA_CALLBACK_BASE_URL=       # leave empty: APP_URL is public
MPESA_ALLOWED_IPS=             # Safaricom's callback IPs, comma-separated (ask Safaricom for the current list)

# Paybill / STK push app
MPESA_C2B_CONSUMER_KEY=
MPESA_C2B_CONSUMER_SECRET=
MPESA_C2B_SHORTCODE=
MPESA_LNMO_PASSKEY=
MPESA_C2B_INITIATOR_NAME=
MPESA_C2B_SECURITY_CREDENTIAL=

# B2C (payouts) app
MPESA_B2C_CONSUMER_KEY=
MPESA_B2C_CONSUMER_SECRET=
MPESA_B2C_SHORTCODE=
MPESA_B2C_INITIATOR_NAME=
MPESA_B2C_SECURITY_CREDENTIAL=
```

The security credentials are the initiator passwords encrypted with Safaricom's **production** certificate. Generate them on the Daraja portal, or with the certificate and `openssl`.

### Business limits (optional overrides)

The defaults are in `config/sportsbook.php`. Set these only to change them:

```dotenv
# SPORTSBOOK_MIN_DEPOSIT=10
# SPORTSBOOK_MAX_DEPOSIT=150000
# SPORTSBOOK_MIN_WITHDRAWAL=50
# SPORTSBOOK_MAX_WITHDRAWAL=150000
# SPORTSBOOK_WITHDRAWAL_APPROVAL_THRESHOLD=20000
# SPORTSBOOK_MIN_STAKE=10
# SPORTSBOOK_MAX_STAKE=20000
# SPORTSBOOK_MAX_PAYOUT=1000000
# SPORTSBOOK_MAX_SELECTIONS=30
# SPORTSBOOK_ODDS_MARGIN=0.06
# SPORTSBOOK_DERIVED_MARGIN=0.10
# SPORTSBOOK_MINIMUM_BOOKMAKERS=3
# SPORTSBOOK_ODDS_STALE_AFTER_HOURS=26
# SPORTSBOOK_DEPOSIT_TAX_RATE=0     # e.g. 0.05 once confirmed with the tax adviser
# SPORTSBOOK_WINNINGS_TAX_RATE=0    # e.g. 0.20 once confirmed
# REFERRALS_ENABLED=true
# EXPORTS_DISK=local
```

### Mail

Nothing in the app sends email today (password resets and codes go by SMS). Leave `MAIL_MAILER=log`.

## 7. Install, migrate and seed

As `deploy`, in `/var/www/sportsbook`:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan key:generate --force
php artisan migrate --force
php artisan storage:link            # promotion banners are served from storage/app/public

# Roles and permissions for the admin panel. Safe to re-run; re-run after every deploy.
php artisan db:seed --class=AdminRoleSeeder --force
# Switches syncing on for the default competitions (EPL, La Liga, Serie A, Bundesliga, Ligue 1, UCL). Safe to re-run.
php artisan db:seed --class=CompetitionSeeder --force
# Optional: the three example promotion pages (multibet bonus, jackpot, invites). Review the wording first.
# php artisan db:seed --class=PromotionSeeder --force

php artisan optimize               # caches config, routes, events and views
php artisan filament:optimize      # caches Filament components and icons
```

**Never run a plain `php artisan db:seed` in production.** `DatabaseSeeder` creates a test user, and `LocalTestUsersSeeder` creates accounts with a known password.

Destructive commands (`migrate:fresh`, `migrate:reset`, `db:wipe`) are blocked when `APP_ENV=production`.

## 8. Apache virtual hosts

Both domains serve the same `public/` directory. Laravel routes `ADMIN_DOMAIN` to the Filament panel and everything else to the player site.

`/etc/apache2/sites-available/sportsbook.conf`:

```apache
<VirtualHost *:80>
    ServerName example.co.ke
    DocumentRoot /var/www/sportsbook/public

    <Directory /var/www/sportsbook/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/sportsbook-error.log
    CustomLog ${APACHE_LOG_DIR}/sportsbook-access.log combined
</VirtualHost>
```

`/etc/apache2/sites-available/sportsbook-admin.conf`:

```apache
<VirtualHost *:80>
    ServerName admin.example.co.ke
    DocumentRoot /var/www/sportsbook/public

    <Directory /var/www/sportsbook/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        # Optional but recommended: only office/VPN IPs may open the admin panel.
        # Require ip 203.0.113.10 198.51.100.0/24
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/sportsbook-admin-error.log
    CustomLog ${APACHE_LOG_DIR}/sportsbook-admin-access.log combined
</VirtualHost>
```

```bash
a2ensite sportsbook sportsbook-admin
a2dissite 000-default
apachectl configtest && systemctl reload apache2
```

`public/.htaccess` (shipped with Laravel) does the URL rewriting and passes the `Authorization` header through for the mobile API. That's why `AllowOverride All` is needed.

**www:** if you also want `www.example.co.ke`, add it as a `ServerAlias` and redirect it to the bare domain. Passkeys only work on the `APP_URL` host, so players must end up on one host.

## 9. HTTPS

```bash
certbot --apache -d example.co.ke -d admin.example.co.ke --redirect -m ops@example.co.ke --agree-tos
certbot renew --dry-run     # renewal runs from a systemd timer installed with certbot
```

Certbot writes `*-le-ssl.conf` copies of both vhosts on port 443 and makes port 80 redirect to HTTPS.

Once HTTPS works, add HSTS inside both `*-le-ssl.conf` vhosts:

```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

Then `systemctl reload apache2`.

**Behind Cloudflare or another proxy?** Laravel would then see the proxy's IP. That breaks per-IP rate limits and the M-Pesa IP allow-list. It needs a code change: `$middleware->trustProxies(...)` in `bootstrap/app.php`. This guide assumes Apache faces the internet directly.

## 10. Queue worker (Supervisor)

Queued work includes withdrawal payouts, fixture and odds syncs per competition, invite reward checks, and admin exports. Without a worker, none of it runs.

`/etc/supervisor/conf.d/sportsbook-worker.conf`:

```ini
[program:sportsbook-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/sportsbook/artisan queue:work database --sleep=3 --max-time=3600
directory=/var/www/sportsbook
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/www/sportsbook/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

```bash
supervisorctl reread && supervisorctl update
supervisorctl status          # both sportsbook-worker processes RUNNING
```

There's no `--tries` flag on purpose. Each job sets its own:

- payouts and odds purchases run once (`$tries = 1`), because a retry could pay twice or spend credits twice;
- the others retry up to 3 times.

`--max-time=3600` restarts each worker hourly, which keeps memory in check.

## 11. Scheduler (cron)

`/etc/cron.d/sportsbook`:

```cron
* * * * * www-data cd /var/www/sportsbook && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

What it runs (`routes/console.php`):

| Command | When | Cost |
|---|---|---|
| `sportsbook:sync-fixtures` | Hourly | Free Odds API endpoints |
| `sportsbook:sync-odds` | Daily 09:00 EAT | Credits: 1 per synced competition per market per region |
| `sportsbook:settle` | Hourly | Postponement voids free; scores 2 credits per competition, only with bets waiting, at most every 12 h each |
| `sportsbook:suspend-stale` | Every 5 minutes | Free |
| `wallet:reconcile-deposits` | Every minute | Asks M-Pesa about STK deposits with no callback |
| `sanctum:prune-expired --hours=24` | Daily | Deletes expired mobile tokens |
| `exports:prune` | Daily | Deletes staff exports after 7 days |

Check it with `php artisan schedule:list`. Every task is `onOneServer` and `withoutOverlapping`, using the Redis cache for locks.

## 12. PHP and OPcache tuning

`/etc/php/8.4/fpm/conf.d/99-sportsbook.ini`:

```ini
expose_php = Off
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 60

opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0    ; code changes need `systemctl reload php8.4-fpm` (the deploy script does it)
```

In `/etc/php/8.4/fpm/pool.d/www.conf`, for 4 GB RAM (each worker uses roughly 60–100 MB):

```ini
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 4
pm.max_spare_servers = 8
pm.max_requests = 500
```

Apply with `systemctl restart php8.4-fpm`.

Let `deploy` reload PHP-FPM without a password, for the deploy script. Run `visudo -f /etc/sudoers.d/deploy` and add:

```
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
```

## 13. M-Pesa go-live

Callbacks arrive at `https://example.co.ke/api/mpesa/{MPESA_CALLBACK_SECRET}/…`:

| Path | Used by |
|---|---|
| `stk` | STK push results (sent with each request; nothing to register) |
| `c2b/validation`, `c2b/confirmation` | Paybill payments (registered once, below) |
| `b2c/result` | Payout results |
| `b2c/status/result` | Payout status checks |
| `balance/result` | Account balance checks |
| `{b2c,b2c/status,balance,reversal}/timeout` | Safaricom queue timeouts |

The URLs must be public HTTPS. A wrong secret gets a 404, and so does any request when the secret is shorter than 32 characters, so callbacks fail until it is set. When `MPESA_ALLOWED_IPS` is set, requests from other IPs also get a 404.

**Register the Paybill URLs once** (per shortcode and environment), after HTTPS works:

```bash
php artisan mpesa:register-c2b-urls
```

It prints the confirmation URL (with the secret masked) and Safaricom's reply.

- **In production, Safaricom normally lets a shortcode register its URLs only once.** Changing them later (a new domain or a new callback secret) means asking Safaricom to clear the registration. So settle `APP_URL` and `MPESA_CALLBACK_SECRET` before you run this.
- **Unverified risk:** Daraja's documentation says callback URLs must not contain words like "M-PESA" or "Safaricom". Our paths contain `/api/mpesa/`. If registration is refused with an invalid-URL error, the route prefix in `routes/api.php` has to be renamed (a code change), and the app redeployed before you try again.

**Smoke test with real money:**

- Deposit KES 10 by STK push.
- Deposit KES 10 by Paybill, using your phone number as the account number.
- Withdraw KES 50, which is under the approval threshold, so it pays out automatically.
- Then open Payments → Reconciliation and press **Check B2C balance**.

## 14. Odds feed and first data

Fixture syncs are free; odds cost credits. On a fresh install:

```bash
php artisan sportsbook:sync-fixtures --sync   # competitions and fixtures, free
php artisan sportsbook:sync-odds              # spends credits: about 1 per synced competition
```

After that the scheduler takes over. In the admin panel, Trading → Competitions decides which competitions are synced. Every synced competition adds to the daily credit spend. The dashboard's **Odds API credits** card shows the month's spend against `ODDS_API_MONTHLY_CREDIT_CAP`.

## 15. First admin

```bash
php artisan admin:create --name="Jane Owner" --email=owner@example.co.ke --role=super-admin
```

It asks for a password. In production that means 12+ characters with upper and lower case letters, a number and a symbol, not found in known breaches.

Sign in at `https://admin.example.co.ke` and set up the authenticator app; it is required. Create other staff from Access → Admins in the panel.

## 16. Go-live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` is the HTTPS URL.
- [ ] `https://example.co.ke/up` returns 200.
- [ ] Sign up with a real phone; the SMS code arrives.
- [ ] Admin sign-in works with 2FA. Each role sees only its menu ([admin guide](../guides/admin.md)).
- [ ] `supervisorctl status` shows the workers running. `php artisan queue:failed` is empty.
- [ ] `php artisan schedule:list` shows the tasks. After a few minutes, `storage/logs/laravel-*.log` shows no errors.
- [ ] Odds show on the home page. The Odds API credits card shows `live`.
- [ ] M-Pesa smoke test passed (section 13). Reconciliation shows the deposits on both sides.
- [ ] `SPORTSBOOK_LICENCE_TEXT` matches the BCLB licence. Terms, Privacy and Responsible Gambling pages have the real legal text (`resources/views/sportsbook/legal.blade.php` is a placeholder).
- [ ] Tax rates are confirmed and set, or deliberately left at 0.
- [ ] Backups run and a restore has been tested (section 18).
- [ ] Optional: the mobile API spec is re-exported with the production `APP_URL` (`docs/api/`).

## 17. Deploying updates

`/var/www/sportsbook/deploy.sh` (owned by `deploy`, `chmod 750`; keep it out of git or add it to `.gitignore`):

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/sportsbook

php artisan down --retry=30 --refresh=15

git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan db:seed --class=AdminRoleSeeder --force

php artisan optimize:clear
php artisan optimize
php artisan filament:optimize

sudo /usr/bin/systemctl reload php8.4-fpm
php artisan queue:restart

php artisan up
echo "Deployed $(git rev-parse --short HEAD)"
```

Run it as `deploy`: `./deploy.sh`.

- `queue:restart` makes the workers finish their current job and restart on the new code.
- Supervisor brings them back up.

> **Maintenance mode blocks M-Pesa callbacks.** While the site is down (usually under a minute), Safaricom's callbacks get a 503. Here's what that costs:
>
> - **STK deposits:** recovered automatically. The every-minute reconcile asks M-Pesa about them.
> - **Payouts:** stay *Processing*. Use **Check status** on the withdrawal.
> - **Paybill confirmations:** may be lost, and Safaricom doesn't reliably resend them.
>
> Deploy at quiet hours, then check Payments → Reconciliation. See [Known gaps](#20-known-gaps) for the fix.

**Rolling back:** `git checkout <previous-commit>` and run the same steps without `migrate`. If a migration must be undone, run `php artisan migrate:rollback --step=1` before checking out. Take a database backup before any deploy with migrations.

## 18. Backups

The database is the business: the wallet ledger, bets and payments. Back it up off the server.

`/usr/local/bin/sportsbook-backup` (root, `chmod 700`):

```bash
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date -u +%Y%m%d-%H%M)
DIR=/var/backups/sportsbook
mkdir -p "$DIR"

mysqldump --single-transaction --quick --routines --triggers sportsbook | gzip > "$DIR/db-$STAMP.sql.gz"
tar -czf "$DIR/files-$STAMP.tar.gz" -C /var/www/sportsbook storage/app/public .env

find "$DIR" -type f -mtime +14 -delete
# Copy off the server, e.g. with rclone to S3/Backblaze/Google Drive:
# rclone copy "$DIR" remote:sportsbook-backups --max-age 25h
```

The dump runs as root through socket authentication.

`/etc/cron.d/sportsbook-backup`:

```cron
30 0 * * * root /usr/local/bin/sportsbook-backup
```

That's 00:30 UTC, or 03:30 EAT.

- The tarball contains `.env`, which holds M-Pesa and API secrets. Encrypt the off-site copy, or keep `.env` in a password manager instead.
- **Test a restore** into a scratch database at least once:

  ```bash
  gunzip < db-….sql.gz | mysql sportsbook_restore_test
  ```

## 19. Monitoring and troubleshooting

| Look at | For |
|---|---|
| `storage/logs/laravel-YYYY-MM-DD.log` | App errors |
| `storage/logs/worker.log`, `php artisan queue:failed` | Failed jobs (`queue:retry <id>`, but **never** retry a payout job; resolve the withdrawal in the panel instead) |
| `/var/log/apache2/sportsbook-*.log` | Requests, 500s, M-Pesa callback hits |
| `journalctl -u php8.4-fpm` | PHP-FPM crashes and pool exhaustion ("server reached pm.max_children") |
| Admin → Reconciliation | M-Pesa vs ledger differences |
| Admin → Dashboard → Odds API credits | Credit spend |
| `https://example.co.ke/up` | Point an uptime monitor here |

Common problems:

- **500 after deploy, "Unable to locate file in Vite manifest":** `npm run build` didn't run or failed. Run it again.
- **Changes don't show:** OPcache has `validate_timestamps=0`. Run `sudo systemctl reload php8.4-fpm`.
- **Config changes in `.env` ignored:** config is cached. Run `php artisan optimize`.
- **Admin domain shows the player site:** `ADMIN_DOMAIN` doesn't match the host exactly. Fix it and run `php artisan optimize`.
- **Passkeys fail:** the site is being reached on a host other than `APP_URL`'s.
- **Odds stop updating:** check the credits card. At the cap, the sync stops until next month.
- **Permission denied on storage:** re-run the `setfacl` commands in step 5.

## 20. Known gaps

Found while writing this guide. These are code changes, not server settings:

1. **Maintenance mode returns 503 to M-Pesa callbacks** (section 17). Fix: exempt `api/mpesa/*` from maintenance mode in `bootstrap/app.php`.
2. **A payout job lost before it runs** leaves the withdrawal *Queued* with the money held, and there's no admin button for that state. It's unlikely with the database queue, but possible if the server dies right after the request. Fix: a scheduled check, or a panel action, for withdrawals queued too long.
3. **The callback path contains "mpesa"**, which Daraja may refuse at Paybill registration (section 13). It hasn't been tested against production Daraja.
4. **No proxy trust configured**, which matters only behind Cloudflare or a load balancer (section 9).
