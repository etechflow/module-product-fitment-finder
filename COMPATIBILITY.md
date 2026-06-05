# Compatibility — ETechFlow_ProductFitmentFinder

How and why this module works across Magento versions and themes.

---

## Magento + PHP matrix

| Magento | PHP 8.1 | PHP 8.2 | PHP 8.3 |
|---|---|---|---|
| 2.4.4 | ✅ | ✅ | ❌ (Magento limit) |
| 2.4.5 | ✅ | ✅ | ❌ (Magento limit) |
| 2.4.6 | ✅ | ✅ | ✅ |
| 2.4.7 | ✅ | ✅ | ✅ |
| 2.4.8 | ✅ | ✅ | ✅ |
| Mage-OS forks | ✅ | ✅ | ✅ |
| Adobe Commerce | ✅ | ✅ | ✅ |

---

## Theme matrix

| Theme | Status | Why |
|---|---|---|
| **Hyvä Theme** (any child theme) | ✅ Native | Alpine is loaded globally by Hyvä. Bootstrap shim no-ops. |
| **Magento Luma / Blank** | ✅ With bootstrap | Bootstrap shim lazy-loads Alpine v3.13.5 from `cdn.jsdelivr.net` on first render. |
| **Custom themes (Luma parent)** | ✅ With bootstrap | Same as Luma. |
| **Custom themes (Hyvä parent)** | ✅ Native | Same as Hyvä. |
| **Breeze / Snowdog Alpaca** | ✅ With bootstrap | These themes don't ship Alpine. Bootstrap loads it. |
| **Mage-OS adminhtml** | ✅ | Admin uses Magento Ui Components — no theme-specific assumptions. |
| **Air-gapped / strict CSP** | ⚠️ Self-host required | Override the CDN URL in `alpine-bootstrap.js` to point at a self-hosted Alpine. See `INSTALL.md`. |
| **Headless / PWA Studio** | ⚠️ API only | Storefront templates are bypassed in headless mode. Use the `/kvc/options/index` REST-style endpoint directly. |

---

## Design choices that keep it portable

### 1. Self-bootstrapping Alpine.js

The part-finder widget is built on Alpine. Two facts:

- Hyvä loads Alpine on every page.
- Luma does not.

To work on both without forcing a theme-level dependency, the module ships `view/frontend/web/js/alpine-bootstrap.js`. It runs once per page, checks `window.Alpine`, and:

- If present → exits silently. Zero performance impact for Hyvä.
- If absent → injects a `<script>` tag pointing at the Alpine CDN.

The bootstrap and the Part Finder factory (`kvc-part-finder.js`) are both registered in `view/frontend/layout/default.xml`, so they ship to every storefront page. Magento's static-content pipeline handles versioning, gzip, and CDN-fallback automatically.

### 2. Server-side bidirectional filtering

The bidirectional filter logic (selecting any field narrows all others) is **server-side**. Each dropdown click is one small AJAX call to `/kvc/options/index`, which walks the cached vehicle tree, applies the filter, and returns only the matching options.

This matters for compatibility because:

- The browser never has to parse a 250 KB tree.
- The filter logic is one canonical implementation, not duplicated client + server.
- The wire payload per click is ~1–5 KB (just the options for one field).
- The endpoint is cacheable per-query via Varnish / Fastly using normal HTTP caching.

### 3. Form template uses namespaced CSS

The form fragment (`partfinder/form.phtml`) and `styles.phtml` use `.kvc-*` namespaced classes plus inline `<style>` blocks. No Tailwind utilities, no theme-class collisions. The form looks identical on Hyvä, Luma, and any custom theme.

### 4. Standard Magento layout XML

- `default.xml` adds `<script src=…>` tags via the `<head>` block — works on every theme.
- `catalog_category_view.xml` and `kvc_find_index.xml` use standard `referenceContainer` against universal containers like `content`.
- No `hyva_*` handles. No Knockout binding handlers. No theme-conditional XML.

### 5. Module dependencies — Magento core only

`composer.json`:

```
magento/framework
magento/module-catalog
magento/module-backend
magento/module-ui
magento/module-store
```

No `hyva-themes/*`, no `amasty/*`, no commercial deps.

### 6. Admin UI is Magento Ui Components

The Make/Model CRUD grids, the product editor's Vehicle Compatibility tab — all use stock `Magento_Ui` XML components. Same behavior on Luma admin, Hyvä admin (where applicable), Mage-OS, and Adobe Commerce.

The Make/Model picker in the product editor uses some inline JavaScript with `define()` (RequireJS) — but **that's only loaded in the admin context**, where RequireJS is always available regardless of frontend theme.

---

## Upgrading themes

When you switch storefront themes (e.g., Luma → Hyvä, or one Hyvä child theme → another), verify:

- [ ] Part Finder form renders on home page / wherever you've embedded it
- [ ] Dropdowns open on click without console errors
- [ ] Picking a field re-filters the others
- [ ] **Find Parts** button navigates with the right query string
- [ ] Header modal (if used) shares state with hero form
- [ ] No CSS conflicts — `.kvc-*` classes still render correctly
- [ ] Admin Vehicles → Makes / Models grid still loads
- [ ] Product editor's Vehicle Compatibility tab still works

If any of these break, send a screenshot + the active theme name to support@etechflow.com.

---

## Known non-issues

- **No Tailwind classes in template** — by design. Adding Tailwind would break the module on Luma.
- **No bundled Alpine.js file** — by design. Stores that already have Alpine (Hyvä) get zero extra bytes. Stores that don't (Luma) get the latest stable Alpine from CDN, transparently.
- **No vanilla-JS fallback** — by design. The widget logic (combobox, search-as-you-type, shared state) maps cleanly onto Alpine. A vanilla rewrite would be ~3× the code without buying anything Hyvä stores don't already have.
- **`kvc-*` CSS naming** — by design. Namespaced to prevent theme class collisions.
