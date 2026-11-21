# Production web server (nginx) and post-pull checklist

**Procedure status:** Verified on production on 2026-11-21 (incident fix below). The vhost excerpts are the shape of the live file; the live file on the server is the source of truth.

Production runs on a CloudPanel server. The app lives in `/home/central_control/htdocs/central-control.turbosoftspace.com` and its vhost is `/etc/nginx/sites-enabled/central-control.turbosoftspace.com.conf`. nginx serves tenant images straight from disk, so PHP and Varnish never see image requests. This page explains how that works, the 2026-11-21 incident where every tenant image returned 404, and what to do after every `git pull` so it does not happen again.

## How tenant images are served

Uploads live per tenant under `storage/tenant<id>/app/...` (stancl/tenancy filesystem suffix, see `filesystem` in [config/tenancy.php](../config/tenancy.php)). The repository tracks a symlink `public/tenant-storage` that must point at `../storage` on the server, so the files are reachable from the document root. A second, untracked `tenant-storage` symlink exists at the project root on the server.

The vhost turns the request host into a tenant key, then looks the file up on disk:

| Request | File nginx tries, in order |
| --- | --- |
| `/images/<path>` | `public/tenant-storage/tenant<key>/app/images/<path>`, then `public/images/<path>`, then 404 |
| `/storage/images/<path>` | rewritten to `/images/<path>` |
| `/storage/<path>` | `.../tenant<key>/app/<path>`, then `.../tenant<key>/app/public/<path>`, then `public/storage/<path>`, then 404 |
| `/tenant-storage/...` | `internal` only: never served to the public directly |

On the 8080 backend block the last fallback is `/index.php?$args` instead of 404.

### Tenant key: map the host, do not cut the subdomain

The first version of the vhost took the first label of the host (`kofe2` from `kofe2.turbosoftspace.com`). That breaks for a store on its own domain: `kofe-coffeeshop.com` gives `kofe-coffeeshop`, and there is no `storage/tenantkofe-coffeeshop`. The vhost now uses a `map` (it must sit outside the `server` blocks, at the top of the vhost file, because `map` is only allowed in the `http` context):

```nginx
map $host $tenant_key {
    default "";
    # <subdomain>.turbosoftspace.com -> tenant id = subdomain
    ~^(?<sub>[^.]+)\.turbosoftspace\.com$  $sub;
    # custom store domains -> their tenant id
    kofe-coffeeshop.com      kofe2;
    www.kofe-coffeeshop.com  kofe2;
}
```

**Every time a store gets its own domain, add a line for it here** (and for `www.` if used), in addition to adding the domain in `/central` ([Adding a store domain](deployment.md#adding-a-store-domain)). Without the line the storefront loads but every product image is a 404 on that domain.

### Image locations in the vhost

The same four locations exist in the 443 block and in the 8080 backend block. `disable_symlinks off;` is the incident fix (see below) and must stay on all four `/images/` and `/storage/` locations:

```nginx
location ^~ /tenant-storage {
    internal;
}

location ^~ /images/ {
    disable_symlinks off;
    add_header Access-Control-Allow-Origin "*";
    expires max;
    access_log off;
    try_files /tenant-storage/tenant$tenant_key/app$uri $uri =404;       # 8080 block: ... $uri /index.php?$args;
}

location ^~ /storage/images/ {
    rewrite ^/storage/images/(.*)$ /images/$1 last;
}

location ^~ /storage/ {
    disable_symlinks off;
    add_header Access-Control-Allow-Origin "*";
    expires max;
    access_log off;
    try_files /tenant-storage/tenant$tenant_key/app$uri /tenant-storage/tenant$tenant_key/app/public$uri $uri =404;
}
```

The 443 block also adds the `alt-svc` header on these locations; the 8080 block does not.

### CloudPanel can overwrite manual edits

The vhost file contains CloudPanel template placeholders (root, certificates, logs, PHP-FPM port). The fix was made directly in `/etc/nginx/sites-enabled/...`. If anyone saves the vhost from the CloudPanel UI, CloudPanel writes its own copy over the file and the `map` and `disable_symlinks off;` lines can disappear. Keep the CloudPanel vhost editor in sync with the file (paste the same changes there), and after any change made in the panel run the [post-pull checks](#after-every-git-pull-on-production) below.

### Stray commas in server names

`server_name` takes names separated by spaces only. A comma is read as a host name of its own:

```nginx
server_name central-control.turbosoftspace.com kofe2.turbosoftspace.com , kofe-coffeeshop.com;   # wrong: "," is a name
server_name central-control.turbosoftspace.com kofe2.turbosoftspace.com kofe-coffeeshop.com;     # right
```

`/etc/nginx/sites-enabled/central.turbosoftspace.com.conf` still has `server_name central.turbosoftspace.com , shawrma-moods.com , ...`, which prints `conflicting server name ","` on every `nginx -t`. It is harmless today but hides real warnings; remove the commas in both server blocks (and in CloudPanel's copy).

## Incident 2026-11-21: every tenant image returned 404

**Symptom.** Product and site images returned 404 on `kofe-coffeeshop.com`. `kofe2.turbosoftspace.com` looked fine, but only because the browser had the images cached; it was broken too.

**What it was not.** The `map $host $tenant_key` was already correct: `kofe-coffeeshop.com` mapped to `kofe2`.

**Root cause.** The nginx error log showed:

```text
[crit] openat() ".../public/tenant-storage/tenantkofe2/app/images/..." failed (40: Too many levels of symbolic links)
```

The global nginx config has `disable_symlinks if_not_owner from=/home/;`. With that setting nginx only follows a symlink when the link and its target have the same owner. After the previous deploy the project files, including the `public/tenant-storage -> ../storage` symlink, were owned by `central_control`, while `storage/` and everything in it is owned by `turbosoftspace-central-control` (the web user the app writes uploads as). nginx refused to follow the link, so every `try_files` candidate under `tenant-storage` failed and every image became 404 on every domain.

**Fix.**

1. Immediate: give the symlinks the same owner as the storage directory (`-h` changes the link itself, not its target):
   ```bash
   cd /home/central_control/htdocs/central-control.turbosoftspace.com
   sudo chown -h turbosoftspace-central-control:turbosoftspace-central-control public/tenant-storage tenant-storage
   ```
2. Durable: added `disable_symlinks off;` to the four tenant-storage locations (`/images/` and `/storage/`, in both the 443 and 8080 blocks). A deploy that recreates `public/tenant-storage` under another owner no longer breaks images. Backup of the previous vhost: `central-control.turbosoftspace.com.conf.bak-20261121173736`, next to the live file.

**Verification done.** `nginx -t` passed and nginx was reloaded. `kofe2.turbosoftspace.com` and `kofe-coffeeshop.com` returned `200 image/webp` on 443 and on the 8080 backend, for both site images and product images; `/storage/images/...` returned 200; a missing image returned a clean 404. The symlink ownership was then broken again on purpose to simulate a redeploy, and images still returned 200. A public request through real DNS (`https://kofe-coffeeshop.com/images/...`) returned `200 image/webp`. No `Too many levels of symbolic links` errors after the reload.

## After every git pull on production

Run these in order, from the project directory. Steps 1 to 5 protect images; steps 6 to 9 are the normal release steps from [Deployment › Release procedure](deployment.md#release-procedure).

Two users matter, and on 2026-11-21 they were different:

| Role | User seen on 2026-11-21 | Owns | Check with |
| --- | --- | --- | --- |
| Code owner: runs `git pull` | `central_control` | the project directory and the code | `stat -c %U .` |
| Web user: PHP-FPM, writes uploads, cache and logs | `turbosoftspace-central-control` | `storage/` and everything in it | `stat -c %U storage` |

```bash
APP_USER=$(stat -c %U .)          # code owner
WEB_USER=$(stat -c %U storage)    # web user
```

Run git as the code owner and artisan as the web user, so files artisan creates under `storage/` and `bootstrap/cache/` stay writable by the web app. If the two ever become the same user, even better: use it for everything.

### 1. Pull as the site user, never as root

```bash
cd /home/central_control/htdocs/central-control.turbosoftspace.com
git status --short          # expect nothing, or only public/tenant-storage (see step 2)
sudo -u "$APP_USER" git pull origin <branch>
```

Files git writes get the owner of whoever runs it. Pulling as `root` leaves root-owned files that PHP-FPM cannot write and that nginx's `if_not_owner` rule rejects. Always use the code owner.

### 2. Check the `public/tenant-storage` symlink

The repository tracks `public/tenant-storage` as a symlink, and in git it points at a developer's Windows path (`E:/turbo_tenant/storage`). On the server it must point at `../storage`:

```bash
readlink public/tenant-storage        # must print ../storage
```

If it prints anything else (a pull or checkout rewrote it), recreate it:

```bash
sudo -u "$APP_USER" ln -sfn ../storage public/tenant-storage
```

Do not commit the server's version of this symlink back to the repository.

### 3. Fix symlink ownership if the link was recreated

```bash
stat -c '%U %N' public/tenant-storage tenant-storage storage
sudo chown -h "$WEB_USER:$WEB_USER" public/tenant-storage tenant-storage
```

With `disable_symlinks off;` in the vhost this is no longer strictly required, but it keeps the server correct even if the vhost is regenerated by CloudPanel. Never `chown -R` `storage/` to the code owner: PHP-FPM writes uploads there as the web user.

### 4. Test and reload nginx (only if the vhost changed)

```bash
sudo nginx -t && sudo systemctl reload nginx
```

`nginx -t` must say `syntax is ok` and `test is successful`. A `conflicting server name ","` warning means a stray comma in a `server_name` ([see above](#stray-commas-in-server-names)).

### 5. Verify one image per domain

Pick one real image path per store and check every domain of that store, on the public port and on the backend:

```bash
IMG=/images/products/<a real file>.webp
for host in kofe2.turbosoftspace.com kofe-coffeeshop.com; do
  curl -sk -o /dev/null -w "$host 443:  %{http_code} %{content_type}\n" "https://$host$IMG"
  curl -s  -o /dev/null -w "$host 8080: %{http_code} %{content_type}\n" -H "Host: $host" "http://127.0.0.1:8080$IMG"
done
sudo grep -c "Too many levels of symbolic links" /home/central_control/logs/nginx/error.log
```

Every line must be `200 image/...`. Use `-H 'Cache-Control: no-cache'` or a private browser window when checking in a browser: a cached image hides a 404. The error-log path is CloudPanel's default; adjust if the vhost's log directive points elsewhere. The count must not grow after the deploy.

### 6. Migrations (all three scopes)

```bash
sudo -u "$WEB_USER" php artisan migrate --force
sudo -u "$WEB_USER" php artisan tenants:migrate --force
sudo -u "$WEB_USER" php artisan tenants:plugin:migrate --force
```

`tenants:plugin:migrate` is easy to forget and is required: on 2026-11-21 the POS job failed with `MissingAttributeException` on `branches.pos_api_key` because the PosIntegration plugin migration had not run. `config/essentials.php` makes models strict in every environment, so a missing column throws instead of returning null.

### 7. Plugin manifest and caches

```bash
sudo -u "$WEB_USER" php artisan plugin:discover
sudo -u "$WEB_USER" php artisan optimize:clear
sudo -u "$WEB_USER" php artisan optimize
```

### 8. Restart queue workers

```bash
sudo -u "$WEB_USER" php artisan queue:restart
```

Workers keep running old code until they get this signal ([Queue, workers and scheduler](queue-and-workers.md)).

### 9. Smoke test

`GET https://<central>/up` returns 200, one storefront loads with images, and `php artisan queue:failed` shows nothing new. The full list is in [Deployment › Release procedure](deployment.md#release-procedure).
