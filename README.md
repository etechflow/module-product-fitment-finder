# ETechFlow_ProductFitmentFinder — Vehicle Compatibility + Part Finder for Magento 2

A complete vehicle compatibility (Make / Model / Year / Part) system for Magento 2 stores selling automotive products. **Theme-agnostic by design** — bundles its own Alpine.js loader so it works on Hyvä, Luma, and any custom theme without code changes.

- **Version:** 1.0.0
- **Package:** `etechflow/module-product-fitment-finder`
- **Magento:** 2.4.4 – 2.4.8
- **PHP:** 8.1, 8.2, 8.3
- **License:** proprietary (see `LICENSE.txt`)
- **Vendor:** ETechFlow — https://etechflow.com

---

## What's in the box

Two halves of one feature, intentionally bundled in one module:

### Half 1 — Vehicle compatibility data

- **`vehicle_compat_data`** product attribute (JSON) storing every (Make, Model, Year) combination a product fits.
- **`parts_required`** product attribute (multi-select) tagging which part-types each product covers (key blade, transponder, immobilizer chip, etc.).
- **Admin CRUD** for Makes and Models under **Catalog → Vehicles → Makes / Models**.
- **CSV import** for bulk-loading vehicle compatibility data (`bin/magento etechflow:vehiclecompat:import-parts`).
- **Product edit form modifier** — a visual Make/Model/Year picker tab on every product, so the admin doesn't hand-edit JSON.

### Half 2 — Part Finder widget

- **`/vehiclecompat/options/index`** — server-side filtered options endpoint. Each dropdown click sends `field=make|model|year|part` + current selections, server applies bidirectional filter and returns only matching options.
- **`/vehiclecompat/tree/index`** — full vehicle tree (cached, browser-cacheable for 1h).
- **`/vehiclecompat/find/index`** — find-parts results page that filters the catalog by the customer's vehicle.
- **Shareable form fragment** (`ETechFlow_ProductFitmentFinder::partfinder/form.phtml`) that drops into any layout — header modal, hero section, PDP sidebar, all use the same template.
- **Self-contained Alpine.js bootstrap** — on Hyvä stores Alpine is already loaded; on Luma / custom themes the bootstrap lazy-loads Alpine from a CDN (URL is overridable for stores that want to self-host).
- **Shared Alpine store** so multiple form instances on the same page (desktop hero + mobile hero + header modal) stay in sync.

---

## Theme compatibility

| Theme | Status | What happens |
|---|---|---|
| **Hyvä** (Tailwind + Alpine) | ✅ Native | Alpine is global. The bootstrap shim becomes a no-op. Module just works. |
| **Luma / Blank** | ✅ With bootstrap | The bootstrap shim detects no Alpine and lazy-loads it from a CDN. Module works. |
| **Custom themes** (Luma parent) | ✅ With bootstrap | Same as Luma. |
| **Custom themes** (Hyvä parent) | ✅ Native | Same as Hyvä. |
| **Air-gapped / no-CDN stores** | ⚠️ Self-host Alpine | Replace the CDN URL in `view/frontend/web/js/alpine-bootstrap.js` with your self-hosted Alpine URL, or pre-install Alpine in your theme. |
| **Headless / PWA Studio** | ⚠️ Use API only | Use `/vehiclecompat/options/index` directly from your headless storefront. The PHP-rendered form fragment is skipped in headless. |

See `COMPATIBILITY.md` for the full audit.

---

## Quick start

```bash
# 1. Extract into your Magento root
unzip etechflow-module-vehicle-compat-1.0.0.zip -d <magento-root>/

# 2. Enable + migrate (creates DB tables + product attributes)
cd <magento-root>
bin/magento module:enable ETechFlow_ProductFitmentFinder
bin/magento setup:upgrade
bin/magento setup:di:compile      # production-mode only
bin/magento setup:static-content:deploy -f
bin/magento cache:flush

# 3. Visit the admin
open https://your-store.example.com/admin/etechflow_vehicle/make/index
# (Admin sidebar → Catalog → Vehicles → Makes / Models)

# 4. Embed the Part Finder on your homepage / header
#    Reference: USAGE.md → "Embedding the Part Finder form"
```

---

## Documentation index

| File | Purpose |
|---|---|
| `README.md` | Overview, features, theme matrix (this file) |
| `INSTALL.md` | Manual + Composer install + verification + troubleshooting |
| `USAGE.md` | Admin walk-through (Make/Model CRUD, product editor, CSV import) + how to embed the Part Finder widget |
| `CONFIGURATION.md` | Alpine bootstrap CDN URL, server-side filter behavior, caching |
| `COMPATIBILITY.md` | Theme + Magento + PHP matrix and the design choices that keep it portable |
| `CHANGELOG.md` | Version history |
| `UNINSTALL.md` | Clean removal |
| `LICENSE.txt` | proprietary license |

---

## Support

- Email: support@etechflow.com
- Include: Magento version, PHP version, active theme, sample product with vehicle data, screenshot.

---

## License

proprietary — see `LICENSE.txt`.
