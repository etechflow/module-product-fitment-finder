# Changelog — ETechFlow_ProductFitmentFinder

## [Unreleased] — Security: portal-only licensing (removes forgeable key path)

- Removed the HMAC signing secret that shipped inside `LicenseValidator`
  (`SECRET_FRAGMENTS` / `BUNDLE_SECRET_FRAGMENTS`) along with `computeKey()`
  and `computeBundleKey()`. Anyone with the module could compute a valid key
  for their own domain and paste it into admin — no code edit needed.
- Licensing is now portal-only: only portal-issued `SP-` keys are honoured,
  validated live against the eTechFlow portal. The module ships no secret.
- Offline grace derives solely from a cached genuine portal success; it can
  no longer be fabricated from admin-settable config.
- Hardened `isProductionEnvironment()` to always return `true`, closing the
  `production_environment = No` bypass, and added an explicit portal `revoked`
  short-circuit.
- Rewrote the unit suite as a portal-only suite including a hard test proving a
  forged `SP-` key with attacker-controlled config and no portal is rejected.

## [1.2.1] — 2026-06-03 — Storefront copy polish + customer-facing tooltips + theme accent colour

Final polish release. v1.1.1 / v1.2.0 made the big strings
configurable; v1.2.1 sweeps up the remaining ~12 minor strings,
adds 3 optional customer-facing tooltips, and replaces the hardcoded
inline accent colour with a CSS custom property so themes can override.

### Added — 12 new copy fields under new admin group "Storefront Copy Polish + Tooltips"

Every remaining customer-visible string now configurable:

1. **"No Results" Title** — when filters return zero products (default `No products match all your filters.`)
2. **"No Results" Hint** — secondary line (`Try removing one or two filters…`)
3. **"Use the Form" Prompt** — secondary line in empty-state (`Use the form above to start.`)
4. **Dropdown "No Matches"** — when a typed search yields no items (`No matches`)
5. **Dropdown Search Placeholder** — each dropdown's search input (`Search…`)
6. **PDP Fitment Badge Overflow** — template with `{count}` placeholder (`and {count} more`)
7. **Garage Widget Title** — default `My Garage`. Watch shop: `My Watches`. Phone shop: `My Phones`.
8. **Garage Clear-All Button** — `Clear Garage`
9. **Garage Remove Label** — `Remove` (aria-label + title for × button)
10. **"Saved!" Confirmation Text** — the 1.5s post-save microinteraction
11. **Sidebar "No Filters" Message** — `No filters active.`
12. **OEM Search Submit Button** — `Search`

### Added — 3 customer-facing tooltips (all optional)

Rendered as standard HTML `title=""` attributes. Blank value → no tooltip.

13. **PDP Fitment Badge Tooltip** — suggested:
    > "This list shows the vehicles this part fits. Match your car's Make, Model and Year to confirm."
14. **My Garage Tooltip** — suggested:
    > "Vehicles you've saved for quick reload. Click any vehicle to filter the catalog by that fit."
15. **OEM Search Tooltip** — suggested:
    > "Paste a part number (OEM, MPN, or your store's SKU) to find the matching product directly."

Each tooltip default is **empty** — opt in by typing a message in admin.
The PDP fitment badge gains `tabindex="0"` when a tooltip is set so
keyboard users get the hover help.

### Added — Theme accent colour

16. **Accent Colour (hex)** — six-digit hex, default `#0535F5`
    (eTechFlow blue). Validated against `/^#?[0-9a-f]{6}$/i`; anything
    malformed falls back silently. Drives:
    - OEM Search submit button (via `var(--etechflow-vc-accent, #0535F5)`)
    - Find page CSS custom property `--etechflow-vc-accent` scoped to the
      `.vc-find-page` wrapper. Your theme CSS can also override it
      globally for full design-system integration.

Common values: `#16A34A` green / `#DC2626` red / `#7C3AED` purple.

### Added — supporting infrastructure

- `Setup/Patch/Data/V121ReleaseMarker.php` — always-a-patch discipline.
  Depends on `V120ReleaseMarker`.
- **16 new Config getters** covering all of the above. Accent colour
  getter validates input shape to defend against malformed values.

### Changed

- `Block/FindResults`: 7 new getters.
- `Block/PartFinderData`: 4 new getters for the form template.
- `Block/Garage`: 5 new getters.
- `Block/Product/FitmentBadge::getRenderData()`: now includes
  `more_text` (configurable overflow) and `tooltip` (optional title).

### Templates updated

- `product/fitment-badge.phtml`: tooltip + configurable overflow
- `garage/widget.phtml`: tooltip + title/clear/remove all configurable
- `find/results.phtml`: no-results / use-form copy + OEM tooltip +
  OEM button text + CSS variable for accent
- `find/chips.phtml`: use-form prompt configurable
- `find/sidebar.phtml`: no-filters message configurable

### Not changed

- No schema changes — drop-in from 1.2.0.
- All 16 new fields opt-in; defaults preserve v1.2.0 behaviour exactly.
- No URL or API breakage.

### Migration

```bash
composer require etechflow/module-product-fitment-finder:^1.2.1
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Verified

- PHP lint: 63/63 clean. XML: 25/25 clean.
- Local Docker: `setup:upgrade` advanced 1.2.0 → 1.2.1, `V121ReleaseMarker`
  landed (id=218), all 13 new Config getters return correct defaults.
- Accent colour input validation: malformed values (`rubbish-not-hex`)
  correctly fall back to `#0535F5`; valid hex (`#16A34A`) returned as-is.
- Fitment overflow placeholder substitution: `getFitmentOverflow(5)`
  → `and 5 more`.

---

## [1.2.0] — 2026-06-03 — Customer-attribute garage sync + OEM/part-number search (final Amasty parity)

Closes the last two competitive gaps vs Amasty Product Parts Finder.
After v1.2.0 the module is at full feature parity except for
"multiple finders per store" (a v1.3.0 candidate that almost no
merchant actually needs).

### Added — Customer-attribute garage sync

Guest customers always used localStorage only. v1.2.0 adds a parallel
**customer EAV attribute** that backs the garage for logged-in users —
so they see their saved vehicles on every device they log into.

- New customer attribute `etechflow_vc_garage` (varchar / JSON),
  created via `Setup/Patch/Data/AddCustomerGarageAttribute.php`.
  Global scope, no admin UI; managed entirely through the storefront
  sync endpoint. Idempotent re-runs.
- New AJAX endpoint **`/vehiclecompat/garage/sync`**
  (`Controller/Garage/Sync.php`):
  - `GET` — load the customer's saved vehicles (JSON).
  - `POST { action: "save", vehicles: [...] }` — merge into the
    customer attribute, de-dupe by label, cap to `garage_max_entries`.
  - `POST { action: "clear" }` — wipe the customer attribute.
  - Guest → 401 (JS falls back to localStorage).
  - Module/garage disabled → 404 (doesn't leak module on/off state).
  - Sanitised inputs: capped fields and string lengths prevent
    attribute bloat.

### Added — OEM / part-number search

New admin-opt-in search box on the Find page. Customer pastes an
OEM/MPN code, the catalog filters to products whose configured
attribute LIKE-matches the term — in addition to the
Make/Model/Year/Part cascade.

- 4 new admin fields under *OEM / Part-Number Search*:
  1. **Enable OEM Search** (Yes/No, default **No**)
  2. **Attribute Codes to Search** (default `sku`, accepts
     comma-separated codes like `sku, mpn`)
  3. **Search Box Label** (default "Or search by part number")
  4. **Search Box Placeholder** (default "Type part number…")
- 4 new Config getters with input-sanitising defences:
  - `getOemAttributeCodes()` validates each code against `[a-z0-9_]`,
    defending against attribute-name injection.
  - Term sanitised to `[a-z0-9\-_./]` (common part-number characters)
    in `FindResults::getOemTerm()`, max 64 chars.
- `Block/FindResults`: `hasAnyFilter()` now returns true when an OEM
  term is present; `getProductCollection()` OR-filters across
  configured attribute codes via `addAttributeToFilter([...])`.
- Find page template: renders the search form when OEM is enabled.
  Preserves any vehicle filters via hidden inputs so OEM composes
  with the cascade. Uses GET for shareable + cacheable URLs.

### Hardening

- `Setup/Patch/Data/V120ReleaseMarker.php` — always-a-patch discipline.
  Depends on `AddCustomerGarageAttribute` so the customer attribute is
  in place before this release is considered applied.

### Not changed

- No breaking changes. Both features opt-in (default off).
- No URL or API breakage.
- v1.1.x behaviour fully preserved for installs that don't enable
  the new features.

### Migration

```bash
composer require etechflow/module-product-fitment-finder:^1.2.0
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Pre-flight check:
```sql
SELECT module, schema_version, data_version FROM setup_module
WHERE module='ETechFlow_ProductFitmentFinder';
```
Both should read `1.2.0`. Also verify the customer attribute:
```sql
SELECT attribute_id, attribute_code FROM eav_attribute
WHERE attribute_code='etechflow_vc_garage';
```
Should return one row.

### Final Amasty parity scorecard (post v1.2.0)

| Feature | Amasty PPF (~$399) | This module (v1.2.0) |
|---|---|---|
| Universal fitment | ✅ | ✅ |
| Configurable labels | ✅ | ✅ |
| Configurable chrome (button / title / empty state) | ✅ | ✅ |
| PDP fitment badge | ✅ | ✅ |
| SEO URLs | ✅ | ✅ |
| Saved garage (localStorage) | ✅ | ✅ |
| **Customer-attribute garage sync** (cross-device) | ✅ | ✅ (this release) |
| **OEM/part-number search** | ✅ Pro | ✅ (this release) |
| Save Selection button | ✅ | ✅ |
| Garage empty state | ✅ | ✅ |
| CSV import | ✅ | ✅ |
| Multiple finders per store | ✅ | ❌ (v1.3.0 candidate) |
| Auto-feed data import | ✅ Pro | ❌ (v2.0 candidate) |

**11/13 features at parity.** The remaining 2 are niche enough that
most merchants don't actually use them.

### Verified

- PHP lint: 62/62 clean. XML: 25/25 clean.
- Local Docker: `setup:upgrade` advanced 1.1.1 → 1.2.0, both new
  patches landed (`AddCustomerGarageAttribute` id=216,
  `V120ReleaseMarker` id=217), customer EAV attribute
  `etechflow_vc_garage` created (`attribute_id=157`). All 4 new OEM
  Config getters return correct defaults.

---

## [1.1.1] — 2026-06-03 — Truly universal frontend copy + close the v1.1.0 garage UX gaps

v1.0.2 made the dropdown LABELS configurable so non-vehicle merchants
could rebrand "Make / Model / Year / Part" to "Brand / Phone /
Generation / Style". v1.1.1 finally makes the **surrounding chrome**
configurable too — the button text, page title, empty-state messages
were still hardcoded automotive copy in v1.1.0, leaking the vehicle
vibe into phone-case / watch-strap / appliance merchant shops.

Also closes the v1.1.0 Customer Garage UX gaps that left the feature
practically invisible to first-time customers.

### Added (universal customer-facing copy)

Five new admin fields under *Stores → Configuration → eTechFlow →
Vehicle Compatibility → **Customer-Facing Copy (v1.1.1)***:

1. **Find Button Text** — default `Find Parts`. Replaces v1.1.0's
   all-caps `FIND PARTS` shouting button. Phone-case shops:
   `Find Cases`. Watch shops: `Find Straps`. Filter shops: `Find Filters`.
2. **Find Page Title** — default `Find Your Parts`. The `<h1>` on the
   search results page. Customisable per merchant domain.
3. **Empty State Message** — default
   `Pick a {make}, {model}, {year} or {part} to see matching products.`
   **Supports four placeholders that auto-expand to the merchant's
   configured field labels**:
   - `{make}` → expands to whatever Make Label is set
   - `{model}` → Model Label
   - `{year}` → Year Label
   - `{part}` → Part Label
   So if the merchant renamed Make → Brand, the empty state
   automatically reads "Pick a Brand, Model, Year or Part…" with zero
   extra edits.
4. **Save Selection Button Text** — default `Save Selection`. Text on
   the new save-to-garage button.
5. **Garage Empty-State Prompt** — default `Save a selection here for
   one-click reload later.` Friendly nudge shown in the My Garage
   widget when nothing is saved.

### Added (close v1.1.0 garage UX gaps)

- **"💾 Save Selection" button on the Part Finder form**, right next
  to the Find button. Visible only when:
  - the Customer Garage is enabled in admin, AND
  - the customer has picked at least a Make (button uses `x-show="selectedMake"`)
  Clicking it calls the existing `window.etechflowGarageSave()`
  helper, persists to localStorage, and flips a brief "✓ Saved!"
  micro-interaction (~1.5s) so the customer gets feedback.
- **My Garage empty state** — instead of rendering nothing when no
  vehicles are saved, the widget now shows a small bookmark icon +
  the configurable prompt. Customers discover the feature on first
  visit instead of never knowing it exists.

### Changed

- **`view/frontend/templates/partfinder/form.phtml`**:
  - The Find button now uses `$block->getFindButtonText()` instead of
    hardcoded `__('FIND PARTS')`. Mixed-case by default; merchants
    can SHOUT_CASE if they want to.
  - Wrapped Find button + new Save button in a `.vc-actions` flex
    container so they sit side-by-side.
- **`view/frontend/templates/find/results.phtml`**:
  - `<h1>` now reads `$block->getFindPageTitle()`.
  - Empty-state copy now uses `$block->getEmptyStateMessage()` with
    label substitution.
- **`view/frontend/templates/find/chips.phtml`**: same empty-state
  treatment.
- **`view/frontend/templates/garage/widget.phtml`**: empty-state UI
  block + supporting CSS.
- **`view/frontend/web/js/part-finder.js`**: new transient
  `savedFeedback` Alpine state for the Save button confirmation.
- **`Model/Config.php`**: 5 new getters
  (`getFindButtonText`, `getFindPageTitle`, `getEmptyStateMessage`,
  `getSaveButtonText`, `getGarageEmptyPrompt`). The empty-state
  getter applies placeholder substitution from the label getters.
- **`Block/PartFinderData.php`**: exposes the new getters to templates.
- **`Block/Garage.php`**: same.
- **`Block/FindResults.php`**: now injects `Model\Config` to expose
  `getFindPageTitle()` and `getEmptyStateMessage()`.

### Hardening

- `Setup/Patch/Data/V111ReleaseMarker.php` — always-a-patch discipline.

### Not changed

- No schema changes — drop-in upgrade from 1.1.0.
- No URL changes.
- No API changes.
- Default behaviour for installs that don't touch the new copy fields
  is functionally equivalent to v1.1.0 (with cosmetic improvements:
  mixed-case button, garage empty state visible).

### Migration

```bash
composer require etechflow/module-product-fitment-finder:^1.1.1
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Pre-flight check:
```sql
SELECT module, schema_version, data_version FROM setup_module
WHERE module='ETechFlow_ProductFitmentFinder';
```
Both should read `1.1.1`. If `data_version` is stale, re-run
`setup:upgrade` — do NOT flush cache yet.

To opt in to universal copy:
- Visit *Stores → Configuration → eTechFlow → Vehicle Compatibility →
  Customer-Facing Copy*
- Set merchant-specific button/title/empty-state text
- For non-vehicle stores, **also** set the field labels under General
  Settings (Make → Brand, etc.) and the placeholders in your empty-
  state message will auto-expand to match

### Verified

- PHP lint: 59/59 clean
- XML: 25/25 clean
- Local Docker: `setup:upgrade` advanced 1.1.0 → 1.1.1, V111ReleaseMarker
  landed in patch_list (id 215), all 5 new Config getters return
  correct defaults, empty-state placeholder substitution confirmed
  ("Make → Brand" relabel → "Pick a Brand, Model, Year or Style…").

---

## [1.1.0] — 2026-06-03 — Amasty-competitor feature set: PDP fitment badge, SEO URLs, customer garage, universal positioning

Four major additions that turn this from "Vehicle Compatibility v1.0"
into a credible competitor to Amasty Product Parts Finder (~$399).
All features are opt-in via admin config — defaults preserve v1.0.x
behaviour exactly, so existing installs see no change unless they
intentionally enable one.

### Added

#### 1. PDP fitment badge
Renders a coloured "Fits: BMW 3 Series 2018-2023" block under the price
on every product detail page where the product has vehicle compatibility
data assigned. The most-requested Amasty-parity feature — signals
"yes this fits your car" right at the purchase-decision moment.

- **New block**: `Block/Product/FitmentBadge` — resolves the product's
  `vehicle_compat_data` JSON attribute against the Make/Model tables,
  formats human-readable strings ("BMW 3 Series 2018-2023" — year
  ranges collapsed when contiguous, listed individually otherwise),
  and de-dupes across `parts_required` entries.
- **New template**: `view/frontend/templates/product/fitment-badge.phtml`
  — inline-styled HTML that survives email clients and theme overrides.
- **New layout**: `view/frontend/layout/catalog_product_view.xml` —
  injects the badge into `product.info.main` after `product.info.price`.
- **Admin config** under *Stores → Configuration → eTechFlow → Vehicle
  Compatibility → PDP Fitment Badge*:
  - Show Fitment Badge on Product Page (Yes/No, default **No**)
  - Badge Prefix Text (default "Fits:" — set to "Compatible with:" or
    "Made for:" for different tones)
  - Badge Style (success/info/warning/neutral — colour treatment)
- **Inline limit**: max 3 vehicles per badge; surplus shown as
  "and N more". Keeps PDP layouts clean for parts that fit dozens of
  vehicles.

#### 2. SEO-friendly URLs
Maps `/parts/bmw/3-series/2020/brake-pads` to the Part Finder Find
action. Massive SEO improvement over query-string URLs — Google ranks
slug-based URLs significantly better, social-share previews look
clean, link sharing is human-readable.

- **New router**: `Controller/Router/FitmentRouter` — implements
  `Magento\Framework\App\RouterInterface`. Matches the configured
  prefix + Make/Model/Year/Part slugs, resolves slugs back to IDs via
  case-insensitive name lookup, forwards to `vehiclecompat/find/index`
  with proper params.
- **New DI**: `etc/frontend/di.xml` — registers the router with
  sortOrder 30 (before CMS router but after standard).
- **Backward-compatible**: when enabled, BOTH old query-string URLs
  AND new path-based URLs work — old shared links don't break.
- **Slug-tolerant**: "3-series" matches "3 Series", "land-rover"
  matches "Land Rover" (case-insensitive, space → dash normalisation).
- **Admin config** under *Stores → Configuration → eTechFlow → Vehicle
  Compatibility → SEO-Friendly URLs*:
  - Enable SEO URLs (Yes/No, default **No**)
  - URL Prefix (default "parts" — use "fitment" / "for" / "compatibility"
    for different vibes; lowercase alphanumeric + dash only, anything
    else stripped, invalid values fall back to "parts")

#### 3. Customer "My Garage" widget
Customers save their vehicle for one-click reload across sessions.
Top-3 conversion driver in parts e-commerce per Amasty's own marketing.

- **New block**: `Block/Garage` — renders the widget when enabled.
- **New template**: `view/frontend/templates/garage/widget.phtml` —
  Alpine.js-driven, reads from `localStorage`, shows saved vehicles
  with one-click reload + individual remove + clear-all.
- **v1.1.0 MVP**: localStorage-based. Guest + logged-in customer get
  the same experience. v1.2.0+ will add customer attribute storage
  for logged-in users so the garage syncs across devices.
- **Merchant placement**: any layout XML reference or CMS block — the
  README documents the standard placement patterns (header, sidebar,
  hero, account page).
- **Auto-saves on Part Finder use**: the existing Alpine store
  `vehicleCompatSel` integrates with the garage automatically — no
  extra clicks for the customer.
- **Per-store-view scoped**: storage key includes the store ID so
  different stores don't share garages (different catalogs, different
  vehicle IDs).
- **Admin config** under *Stores → Configuration → eTechFlow → Vehicle
  Compatibility → Customer Garage*:
  - Enable Customer Garage (Yes/No, default **No**)
  - Maximum Vehicles per Customer (default **3** — clamped 1-10;
    sweet spot for "my car, my wife's car, my work van")

#### 4. Universal positioning
The `composer.json` description now leads with "Universal Product
Fitment Finder for Magento 2" instead of "Vehicle Compatibility".
The same code that already works for any fitment domain via the
v1.0.2 configurable labels is now positioned for it. Sells to:

- Automotive (still the primary)
- Motorcycle / marine / RV / ATV / bicycle parts (already worked)
- Phone cases (Make→Brand, hide Year, Earliest Year=2007)
- Watch straps (Brand/Watch/<hide year>/Strap Size)
- Printer cartridges, appliance parts, industrial fittings —
  anywhere the customer asks "will this fit my X?"

### Added (supporting infrastructure)

- `Model/Source/BadgeStyle.php` — source model for the PDP badge
  style dropdown (success / info / warning / neutral).
- Eight new `Config` getters: `isShowFitmentBadgeOnPdp()`,
  `getFitmentBadgePrefix()`, `getFitmentBadgeStyle()`,
  `isSeoUrlsEnabled()`, `getSeoUrlPrefix()`, `isSavedGarageEnabled()`,
  `getGarageMaxEntries()`, and the BADGE_STYLES whitelist for
  clamping.
- `Setup/Patch/Data/V110ReleaseMarker.php` — continues the always-a-
  patch discipline.

### Not changed

- **No schema changes** — drop-in upgrade from 1.0.3.
- **No breaking changes** — every new feature is opt-in (default off).
  Existing v1.0.x installs that don't touch the new config groups see
  zero behaviour change.
- **No API changes** — public block + service methods unchanged.

### Migration

```bash
composer require etechflow/module-product-fitment-finder:^1.1.0
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Pre-flight check:
```sql
SELECT module, schema_version, data_version FROM setup_module
WHERE module='ETechFlow_ProductFitmentFinder';
```
Both should read `1.1.0`. If `data_version` is stale, re-run
`setup:upgrade` — do NOT flush cache yet.

To opt in to v1.1.0 features:
- **PDP badge**: *Stores → Configuration → eTechFlow → Vehicle
  Compatibility → PDP Fitment Badge → Enable* = Yes
- **SEO URLs**: *...SEO-Friendly URLs → Enable* = Yes (and decide on
  a prefix — "parts" is a safe default)
- **Garage**: *...Customer Garage → Enable* = Yes (and place the
  widget in your theme's layout XML or a CMS block)

### Competitive positioning

| Feature | Amasty PPF (~$399) | This module (v1.1.0) |
|---|---|---|
| Universal fitment | ✅ | ✅ (since v1.0.2) |
| Configurable labels | ✅ | ✅ (since v1.0.2) |
| Multi-axis (2-5 levels) | ✅ | ⚠️ Fixed 4 axes (Make/Model/Year/Part) |
| PDP fitment badge | ✅ | ✅ |
| SEO URLs | ✅ | ✅ |
| Saved garage | ✅ | ✅ (localStorage MVP) |
| Customer-attribute garage sync | ✅ | v1.2.0 |
| CSV import | ✅ | ✅ |
| OEM/part-number search | ✅ Pro | v1.3.0 |
| Multiple finders per store | ✅ | v1.3.0 |

Credible alternative at a fraction of the price.

---

## [1.0.3] — 2026-06-03 — Restore docs accidentally pruned during v1.0.2 publish-repo sync

The v1.0.2 release shipped clean code but the publish-repo rsync
accidentally deleted the top-level documentation files
(INSTALL.md, USAGE.md, CONFIGURATION.md, COMPATIBILITY.md,
UNINSTALL.md) that ship at the repo root alongside README and
CHANGELOG. This release restores them.

No code change. No behaviour change. Pure documentation file
restoration plus V103ReleaseMarker for always-a-patch discipline.

If you installed 1.0.2 you're functionally fine — composer doesn't
care about INSTALL.md vs not. But the GitHub repo page was missing
those docs and 1.0.3 puts them back.

### Migration

```bash
composer require etechflow/module-product-fitment-finder:^1.0.3
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## [1.0.2] — 2026-06-03 — Universal fitment: admin-configurable labels, Year bounds, optional Year field

Same module, three new admin knobs that make it work for any
product-fitment domain — not just vehicles. Drop-in upgrade from
1.0.1, no schema change, no breaking change. Default behaviour
identical to 1.0.1 (Year field visible, "Make/Model/Year/Parts"
labels, year range 1990 – current).

### Added

Three new admin fields under *Stores → Configuration → eTechFlow →
Vehicle Compatibility → General Settings*:

1. **Earliest Year** — text field, default `1990`. The oldest year
   that appears in the Year dropdown. Set to `1950` for vintage car
   parts shops (classic Mustangs, Series Land Rovers). Set to `2007`
   for smartphone-fitment shops where there's no point listing
   pre-iPhone years. Anything below 1900 or above the current year
   gets clamped to safe bounds.

2. **Show Year Field** — Yes/No, default Yes. When No, the Year
   dropdown disappears from the Part Finder form. The form becomes
   Make → Model → Parts, which is what phone case shops, watch strap
   shops, printer cartridge shops, and appliance parts shops actually
   need.

3. **Field Labels** — 4 separate text fields for the customer-facing
   labels:
   - **Make Field Label** (default "Make") — set to "Brand" for
     phone cases / watches / appliances
   - **Model Field Label** (default "Model") — set to "Phone" /
     "Watch" / "Appliance Model"
   - **Year Field Label** (default "Year") — set to "Generation"
     for phones, "Year of Manufacture" for older fitments
   - **Parts Field Label** (default "Parts Required") — set to
     "Type" / "Style" / "Strap Size" / "Component"

   When the labels are configured, the Part Finder dropdowns render
   the merchant's wording instead of "Select Make / Select Model /
   etc." A blank label falls back to the default.

### Changed

- **`Model/Source/Year.php`** — `MIN_YEAR` constant is now
  `@deprecated`; the year source reads from `Config::getEarliestYear()`
  instead. Constant kept on disk so any third-party code referencing
  it doesn't immediately fatal.

- **`Block/PartFinderData.php`** — gains 5 public getters:
  `getMakeLabel()`, `getModelLabel()`, `getYearLabel()`,
  `getPartLabel()`, `isYearFieldEnabled()`. Templates and any
  custom integration can read the configured values.

- **`view/frontend/templates/partfinder/form.phtml`** — uses the
  configured labels for placeholder texts; wraps the Year field
  block with `if ($block->isYearFieldEnabled())` so it can disappear
  entirely when not relevant.

- **`Setup/Patch/Data/V102ReleaseMarker.php`** — no-op release
  marker patch, depends on V101.

### Why this matters

This release transforms the module from "Vehicle Compatibility" into
a "Universal Product Fitment Finder". The same code now sells to:

- **Auto parts** (as before — `Make/Model/Year/Parts` works perfectly)
- **Motorcycle / marine / RV / ATV / bicycle parts** (same labels work)
- **Vintage car parts** (set Earliest Year to 1950)
- **Phone cases** (set labels to `Brand/Phone/Generation/Style`, hide
  Year, set Earliest Year to 2007)
- **Watch straps** (`Brand/Watch/<hide year>/Strap Size`)
- **Printer cartridges** (`Brand/Printer/Year/Cartridge Type`)
- **Appliance parts** (`Brand/Appliance Model/Year/Part Type`)
- **Any product fitment problem** the merchant can map to 2-4 axes

Competing against Amasty Product Parts Finder (~$399) at a fraction
of the price.

### Not changed

- No schema changes — drop-in upgrade from 1.0.1
- No URL changes — existing `/vehiclecompat/find/index` URLs keep working
- No API changes — public block + service methods unchanged
- Default behaviour identical to 1.0.1 — merchants who don't touch
  the new fields see no change at all

### Migration

```bash
composer require etechflow/module-product-fitment-finder:^1.0.2
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Pre-flight check:
```sql
SELECT module, schema_version, data_version FROM setup_module
WHERE module='ETechFlow_ProductFitmentFinder';
```
Both should read `1.0.2`. If `data_version` is stale, re-run
`setup:upgrade` — do NOT flush cache yet.

To opt in to the universal-fitment positioning:
1. Set your merchant's label preferences in
   *Stores → Configuration → eTechFlow → Vehicle Compatibility →
   General Settings*.
2. If your fitment domain doesn't use year, set Show Year Field to No.
3. Save → flush cache → reload the Part Finder.

---

## [1.0.1] — 2026-06-03 — Brand de-leak: rename Keystation-derived routes, files, and CSS classes

Cosmetic but important release. Renames every customer-visible and
admin-visible identifier that still carried the original developer's
"Keystation Vehicle Compatibility" (kvc) / "Keystation" branding, so the
module ships as a generic eTechFlow product any merchant can install
without seeing another shop's name in their URLs or DevTools.

### Changed (customer-facing)

- **URL prefix renamed**: `frontName="kvc"` → `frontName="vehiclecompat"`.
  Part Finder page is now at `/vehiclecompat/find/index` instead of
  `/kvc/find/index`. The Options + Tree AJAX endpoints follow:
  `/vehiclecompat/options/index`, `/vehiclecompat/tree.json`.
- **Frontend JS file renamed**: `view/frontend/web/js/kvc-part-finder.js`
  → `part-finder.js`. The Alpine.js function inside is now
  `vehicleCompatPartFinder()` (was `kvcPartFinder()`), and its store key
  is `'vehicleCompatSel'` (was `'kvcSel'`).
- **CSS class prefix**: `kvc-*` → `vc-*` across all templates (`vc-row`,
  `vc-ico-left`, `vc-trigger`, `vc-side`, `vc-find-page`, `vc-pager`,
  `vc-cat-chips`, etc.). Keeps the prefix short while removing the
  Keystation branding.
- **Frontend layout file**: `view/frontend/layout/kvc_find_index.xml` →
  `vehiclecompat_find_index.xml`.
- **Block names** in layout XML: `kvc.sidebar.summary` /
  `kvc.category.filter.chips` → `vehiclecompat.sidebar.summary` /
  `vehiclecompat.category.filter.chips`.

### Changed (admin-facing)

- **11 admin layout + UI component files** renamed from
  `keystation_vehicle_*` to `etechflow_vehicle_*` so they match the
  module's existing admin route id (`etechflow_vehicle`). Previously
  they were dead-code on disk (route id and file name didn't match;
  Magento auto-loads layout by URL pattern). Renaming gets them back
  on the auto-load path under the canonical eTechFlow naming.

### Added

- **`Setup/Patch/Data/V101ReleaseMarker.php`** — no-op release marker
  patch. Continues the always-a-patch discipline. Depends on the three
  v1.0.0 data patches so patches run in version order.

### Breaking changes ⚠

Anyone who installed v1.0.0 in the ~1 hour between v1.0.0 and v1.0.1
publication will see the Part Finder URL change. No real customers
were installed at the time of this release. Bookmarks pointing at
`/kvc/find/index` will 404 — clients should update to
`/vehiclecompat/find/index`.

If you've embedded the Part Finder form in CMS blocks or themes via
JavaScript, the Alpine.js function call needs renaming:
`kvcPartFinder()` → `vehicleCompatPartFinder()`. Same for any
`Alpine.store('kvcSel')` references.

### Why this exists

The original developer of this module built it first for the Keystation
brand and then handed the code to eTechFlow. The brand prefixes
(`kvc/`, `keystation_vehicle_*`) survived the rebadge. v1.0.0 shipped
with that leak. v1.0.1 cleans it up so the module sells generically.

### Migration

```bash
composer require etechflow/module-product-fitment-finder:^1.0.1
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Pre-flight check after upgrade:
```sql
SELECT module, schema_version, data_version FROM setup_module
WHERE module='ETechFlow_ProductFitmentFinder';
```
Both columns should read `1.0.1`. If `data_version` is stale, re-run
`setup:upgrade` — do NOT flush cache yet.

After upgrade, the Part Finder page is at `/vehiclecompat/find/index`.
A merchant who wants to preserve the old `/kvc/*` URLs can ship a
custom URL rewrite from `/kvc/*` to `/vehiclecompat/*` in their web
server config — but a fresh install no longer publishes any `/kvc/*`
URLs at all.

---

## [1.0.0] — 2026-05-20

First public release as a standalone, theme-agnostic Magento 2 module.

### Added

- **Vehicle compatibility data**
  - `vehicle_compat_data` product attribute (JSON) — Make / Model / Year tuples per product
  - `parts_required` multi-select product attribute
  - Admin Makes CRUD under Catalog → Vehicles → Makes
  - Admin Models CRUD under Catalog → Vehicles → Models
  - Product editor tab with visual Make/Model/Year picker (no hand-edited JSON)
  - CSV import command: `bin/magento etechflow:vehiclecompat:import-parts`
- **Part Finder widget**
  - Reusable form fragment (`ETechFlow_ProductFitmentFinder::partfinder/form.phtml`) — embed anywhere
  - Server-side filtered options endpoint `/vehiclecompat/options/index` (bidirectional)
  - Full vehicle tree endpoint `/vehiclecompat/tree/index` (cached, browser-cacheable)
  - Find-parts results page `/vehiclecompat/find/index` with category chips
  - Shared Alpine store keeps multiple form instances in sync (header modal + hero + sidebar)
- **Theme-agnostic JS bootstrap**
  - `alpine-bootstrap.js` detects Alpine, lazy-loads it from CDN if absent
  - `part-finder.js` factory function — loaded once via layout XML
  - Both loaded on every storefront page via `view/frontend/layout/default.xml`
- **Scoped namespaced CSS**
  - `.vc-*` class prefix prevents theme collisions
  - Inline `<style>` block in `partfinder/styles.phtml`
- **Catalog filter integration**
  - `Plugin\Catalog\Layer\FilterByVehicle` narrows product collections by `?make_id=&model_id=&year=&part_id=` URL params
  - `Plugin\Catalog\Block\HideLayeredNav` hides the layered nav on `/vehiclecompat/find/index` pages
- **Documentation bundle**
  - README, INSTALL, USAGE, CONFIGURATION, COMPATIBILITY, CHANGELOG, UNINSTALL, LICENSE

### Compatibility

- Magento 2.4.4 – 2.4.8
- PHP 8.1, 8.2, 8.3
- Hyvä Theme (native — Alpine global)
- Luma / Blank / custom themes (Alpine auto-loaded from CDN)
- Adobe Commerce + Magento Open Source + Mage-OS

---

[1.0.0]: https://github.com/etechflow/module-product-fitment-finder/releases/tag/v1.0.0
