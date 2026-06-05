# Installation Guide — ETechFlow_ProductFitmentFinder

Two install paths: **manual ZIP** (easiest) or **Composer** (recommended for production).

---

## Prerequisites

- Magento 2.4.4 or newer
- PHP 8.1, 8.2, or 8.3
- SSH access to your Magento root
- For non-Hyvä themes: internet access to load Alpine.js from CDN (or self-host — see below)

---

## Option A — Manual ZIP install

```bash
bin/magento maintenance:enable

# Drop the module into place
unzip etechflow-module-vehicle-compat-1.0.0.zip -d <magento-root>/
#   Layout: <magento-root>/app/code/ETechFlow/ProductFitmentFinder/

# Enable + migrate
bin/magento module:enable ETechFlow_ProductFitmentFinder
bin/magento setup:upgrade

# Production stores
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f

# Clear caches
bin/magento cache:flush

bin/magento maintenance:disable
```

---

## Option B — Composer install

```bash
composer require etechflow/module-product-fitment-finder:^1.0
bin/magento module:enable ETechFlow_ProductFitmentFinder
bin/magento setup:upgrade
bin/magento setup:di:compile        # production-mode only
bin/magento cache:flush
```

---

## Verify the install

```bash
# 1. Module enabled?
bin/magento module:status ETechFlow_ProductFitmentFinder

# 2. DB tables created?
mysql -e "SHOW TABLES LIKE 'etechflow_vehicle%'"
# Expected:
#   etechflow_vehicle_make
#   etechflow_vehicle_model

# 3. Product attributes created?
bin/magento config:show admin/security/admin_force_password_change >/dev/null
# Open admin → Catalog → Products → edit any product → look for a
# "Vehicle Compatibility" section in the product form

# 4. Storefront endpoints respond?
curl -s 'https://your-store.example.com/kvc/options/index?field=make' | head -c 200
# Expected: JSON like {"options":[]}  (empty until you create vehicles)
```

---

## Loading Alpine.js on non-Hyvä themes

The Part Finder widget needs Alpine.js. Three scenarios:

| Your theme | What the module does |
|---|---|
| **Hyvä-based** (any child theme of Hyvä) | Alpine is loaded globally by Hyvä on every page. The module's bootstrap shim detects it and exits — no extra requests. |
| **Luma / Blank / custom** | The bootstrap shim loads Alpine v3.13.5 from `cdn.jsdelivr.net` on the first page where the Part Finder appears. |
| **Air-gapped / strict CSP** | See "Self-hosting Alpine" below. |

### Self-hosting Alpine (no CDN)

If your store can't reach jsdelivr.net (CSP, firewall, air-gapped install):

1. Download Alpine v3.13.5: `https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js`
2. Save it as `<magento-root>/pub/static/_etechflow/alpine.min.js` (or any path under `pub/static`)
3. Edit `<magento-root>/app/code/ETechFlow/ProductFitmentFinder/view/frontend/web/js/alpine-bootstrap.js`:
   ```js
   var CDN_URL = '/static/_etechflow/alpine.min.js';   // your self-hosted path
   ```
4. Re-deploy static content: `bin/magento setup:static-content:deploy -f`

Or alternatively, install Alpine via your theme so this module's shim never has to load it.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Module 'ETechFlow_ProductFitmentFinder' has been already disabled` | `bin/magento module:enable ETechFlow_ProductFitmentFinder` |
| Part Finder dropdowns don't open | Browser console: do you see "Alpine Expression Error"? Means Alpine isn't loaded. Check the `alpine-bootstrap.js` request in Network tab. If your store has CSP, allow `cdn.jsdelivr.net` or self-host (see above). |
| Empty dropdowns | Tree cache is stale. Run `bin/magento cache:flush full_page block_html` |
| `404 Not Found` on `/kvc/options/index` | Run `bin/magento setup:di:compile` — the controller needs interceptor classes generated |
| `Cannot redeclare class …` | Stale generated/code. `rm -rf generated/code generated/metadata && bin/magento setup:di:compile` |
| Product editor doesn't show the Vehicle Compatibility tab | Re-log into admin, then `bin/magento cache:flush layout` |

If something else breaks, send `var/log/exception.log` to support@etechflow.com.
