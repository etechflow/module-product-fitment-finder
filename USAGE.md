# Usage Guide — ETechFlow_ProductFitmentFinder

How to manage vehicles, tag products, and embed the Part Finder widget.

---

## Admin location

```
Catalog → Vehicles ▾
  ├─ Makes              — manage car brands
  └─ Models             — manage models per make
```

ACL: `ETechFlow_ProductFitmentFinder::vehicles`

---

## Managing Makes

**Admin → Catalog → Vehicles → Makes**

Add a make (Audi, BMW, Volvo, …). Fields:

| Field | Required | Notes |
|---|---|---|
| **Name** | Yes | Customer-facing brand name |
| **Sort Order** | No | Lower numbers appear first in dropdowns |
| **Active** | Yes | If disabled, hidden from frontend |

The Make ID is auto-generated and persisted — never reused. If you delete a make and re-create it with the same name, it gets a new ID.

---

## Managing Models

**Admin → Catalog → Vehicles → Models**

Each model belongs to one make. Fields:

| Field | Required | Notes |
|---|---|---|
| **Make** | Yes | Pick from the makes you've created |
| **Name** | Yes | E.g. "A4", "X3", "Phaeton" |
| **Sort Order** | No | Within the make |
| **Active** | Yes | Disabled = hidden frontend |

---

## Tagging a product with vehicle compatibility

**Admin → Catalog → Products → edit any product**

Scroll to **Vehicle Compatibility** section. You'll see a visual editor that lets you:

1. Pick a Make from the dropdown.
2. Pick a Model (filtered to that make).
3. Tick the year range that this product fits.
4. Click **Add row** to repeat for another (Make, Model, Year) combination.

The form stores everything as JSON in the `vehicle_compat_data` product attribute. You never hand-edit the JSON — but if you're curious, the format is:

```json
[
  {"make_id": 2, "make_name": "Audi", "model_id": 27, "model_name": "A4", "years": [2020, 2021, 2022]},
  {"make_id": 2, "make_name": "Audi", "model_id": 28, "model_name": "A6", "years": [2018, 2019]}
]
```

There's also a **Parts Required** multi-select for tagging which part types this product covers (key blade, transponder, etc.). Use this for cross-product filtering on the Part Finder.

---

## Bulk import via CSV

For loading hundreds of vehicles + product assignments at once:

```bash
# CSV format: sku, make_name, model_name, year_start, year_end, parts_csv
# Example:
#   KEY-AUDI-A4-2020,Audi,A4,2020,2022,key-blade;transponder
#   KEY-BMW-X3-2018,BMW,X3,2018,2020,key-blade

bin/magento etechflow:vehiclecompat:import-parts \
    --file=/var/imports/vehicles.csv \
    --create-missing-makes \
    --create-missing-models
```

Options:
- `--dry-run` — parse and validate without saving
- `--create-missing-makes` — auto-create unknown makes
- `--create-missing-models` — auto-create unknown models
- `--product-id=N` — restrict to a single product (for testing)

---

## Embedding the Part Finder form

The Part Finder form fragment lives at `ETechFlow_ProductFitmentFinder::partfinder/form.phtml`. Embed it from any phtml template:

```php
<?php
/** @var \Magento\Framework\View\Element\Template $block */
$baseUrl = $block->getUrl('car-keys-parts');    // where "Find Parts" button navigates to
?>
<?php
$styles = $block->getLayout()
    ->createBlock(\Magento\Framework\View\Element\Template::class)
    ->setTemplate('ETechFlow_ProductFitmentFinder::partfinder/styles.phtml');
$form = $block->getLayout()
    ->createBlock(\Magento\Framework\View\Element\Template::class)
    ->setTemplate('ETechFlow_ProductFitmentFinder::partfinder/form.phtml');
?>

<?= /* @noEscape */ $styles->toHtml() ?>

<div x-data="kvcPartFinder('<?= $escaper->escapeJs($baseUrl) ?>')">
    <?= /* @noEscape */ $form->toHtml() ?>
</div>
```

The wrapping `<div x-data="kvcPartFinder(…)">` is essential — it instantiates the Alpine factory. The single argument is the URL the **Find Parts** button should navigate to when the customer submits.

### Where you might embed it

| Location | Effect |
|---|---|
| **Home page** (CMS block) | Customers find parts immediately from the landing page |
| **Header modal** | Always-available finder triggered by a button |
| **PDP sidebar** | Quick re-search from any product detail page |
| **Listing page header** | Refine the catalog right above the product grid |

All instances on the same page share state via an Alpine store, so selecting Make on the desktop hero pre-fills the same Make in the header modal.

---

## How the bidirectional filter works

Click any dropdown → the JS fires `GET /kvc/options/index?field=…&make_id=…&model_id=…&year=…&part_id=…`. The server walks the cached vehicle tree, applies every selection **except** the field being requested, and returns only the matching options.

Examples:

| User clicks… | Request | Response |
|---|---|---|
| Make dropdown (nothing selected yet) | `?field=make` | All makes |
| Make dropdown (year=2020 already picked) | `?field=make&year=2020` | Only makes with a model that has year 2020 |
| Year dropdown (Make=Audi already picked) | `?field=year&make_id=2` | Only years Audi has |
| Parts dropdown (Make=Audi + Year=2020) | `?field=part&make_id=2&year=2020` | Only parts compatible with Audi 2020 |

After each pick, the other three cached lists are invalidated — so the next dropdown open re-fetches with the new filter combination.

---

## Find Parts results page

Clicking **Find Parts** in the widget navigates to `/<your-base-url>?make_id=…&model_id=…&year=…&part_id=…`. The base URL is whatever you passed to `kvcPartFinder('<base>')`.

The module's `/kvc/find/index` controller can serve as that target — it shows a filtered catalog grid with the selected vehicle as a "chip" the customer can clear to broaden the search. If you'd rather use your own catalog landing page (e.g., `car-keys-parts` CMS page filtered server-side), point the base URL at it instead.
