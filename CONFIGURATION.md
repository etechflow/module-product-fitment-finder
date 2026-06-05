# Configuration — ETechFlow_ProductFitmentFinder

This module is admin-data-driven (Makes, Models, product vehicle assignments). It deliberately has very few Stores → Configuration options — the things that *are* configurable are documented here.

---

## Alpine.js bootstrap CDN URL

The widget needs Alpine.js. On non-Hyvä themes the bootstrap fetches it from a CDN.

**File:** `app/code/ETechFlow/ProductFitmentFinder/view/frontend/web/js/alpine-bootstrap.js`

```js
var CDN_URL = 'https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js';
```

### To self-host Alpine

1. Download Alpine v3.13.5 from the URL above.
2. Save it under `pub/static/_etechflow/alpine.min.js` (or any static path).
3. Change `CDN_URL` to your self-hosted path.
4. Re-deploy static content: `bin/magento setup:static-content:deploy -f`

### To use a different CDN

Same — just point `CDN_URL` wherever you trust. The bootstrap doesn't enforce a specific provider; it just injects a `<script src="…">` tag with `defer` + `crossorigin=anonymous`.

### To upgrade Alpine versions

Bump the version in the URL. Alpine has been stable since v3.0 — minor version bumps within 3.x are safe.

---

## Cache key + invalidation

The vehicle tree (`/kvc/tree/index`) is cached server-side under block-cache key `etechflow_vehicle_compat_tree_v2`, tagged with `CatalogProduct::CACHE_TAG`. Any product save invalidates it.

### Forcing a manual refresh

```bash
bin/magento cache:flush block_html
```

Or invalidate just our cache tag:

```bash
bin/magento cache:clean catalog_product
```

### Per-request `/kvc/options/index`

The options endpoint **does not** cache its response server-side. Each click computes fresh from the tree cache. To enable HTTP caching (e.g. via Varnish / Fastly):

Edit `Controller/Options/Index.php` and change:

```php
$result->setHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0', true);
```

to something like:

```php
$result->setHeader('Cache-Control', 'public, max-age=300', true);
```

5-minute cache is safe — the tree itself invalidates on product save.

---

## Customizing the Part Finder form layout

The form fragment uses two args via Magento's block data API:

```php
$block->getLayout()
    ->createBlock(\Magento\Framework\View\Element\Template::class)
    ->setTemplate('ETechFlow_ProductFitmentFinder::partfinder/form.phtml')
    ->setData('kvc_size', 'sm');   // 'sm' or 'md' (default 'md')
```

`kvc_size`:
- `md` (default) — 38 px row height, 13.5 px font (header modal, hero forms)
- `sm` — 32 px row height, 12 px font (compact sidebars)

To customize further, copy the form fragment into your theme:

```
cp app/code/ETechFlow/ProductFitmentFinder/view/frontend/templates/partfinder/form.phtml \
   app/design/frontend/<Vendor>/<theme>/ETechFlow_ProductFitmentFinder/templates/partfinder/form.phtml
```

Magento's fallback will use yours.

---

## Disabling Alpine bootstrap

If you've already added Alpine in your theme and don't want the module to try loading it again:

In your theme, add `app/design/frontend/<Vendor>/<theme>/ETechFlow_ProductFitmentFinder/layout/default.xml`:

```xml
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <remove src="ETechFlow_ProductFitmentFinder::js/alpine-bootstrap.js"/>
    </head>
</page>
```

The Part Finder factory (`kvc-part-finder.js`) will still load, and assume your theme provides Alpine.

---

## Programmatic API

For stores that want to read vehicle compatibility from custom code:

```php
use ETechFlow\ProductFitmentFinder\Block\PartFinderData;

$block = $om->get(PartFinderData::class);
$tree = $block->getTree();
// [
//   'makes' => [
//     ['id' => 2, 'name' => 'Audi', 'models' => [
//       ['id' => 27, 'name' => 'A4', 'years' => [2020, 2021, 2022], 'parts' => [1135]]
//     ]]
//   ],
//   'parts' => [['id' => 1135, 'name' => 'Key Blade']]
// ]
```

Or call the options endpoint internally:

```php
use Magento\Framework\HTTP\Client\Curl;

$curl = $om->get(Curl::class);
$curl->get('http://localhost/kvc/options/index?field=make&year=2020');
$response = json_decode($curl->getBody(), true);
$makes = $response['options'];
```
