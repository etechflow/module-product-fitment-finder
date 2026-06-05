# Uninstall — ETechFlow_ProductFitmentFinder

> ⚠️ Back up your database before running the removal SQL.

---

## Option A — Disable only (preserve data)

```bash
bin/magento module:disable ETechFlow_ProductFitmentFinder
bin/magento cache:flush
```

DB tables, product attribute values, and the cached vehicle tree stay intact. Re-enable any time.

---

## Option B — Full removal

### 1. Disable

```bash
bin/magento module:disable ETechFlow_ProductFitmentFinder
bin/magento cache:flush
```

### 2. Drop the vehicle tables

```sql
DROP TABLE IF EXISTS etechflow_vehicle_model;
DROP TABLE IF EXISTS etechflow_vehicle_make;
DELETE FROM setup_module WHERE module = 'ETechFlow_ProductFitmentFinder';
```

### 3. Remove the product attributes (optional but recommended for clean uninstall)

```bash
# Via bin/magento (safer — uses the EAV API):
bin/magento config:show eav/attribute/vehicle_compat_data >/dev/null 2>&1 && \
  bin/magento dev:di:info "Magento\\Eav\\Setup\\EavSetupFactory" >/dev/null

# Or directly via SQL (database backup first):
```

```sql
DELETE FROM catalog_product_entity_text WHERE attribute_id IN (
    SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'vehicle_compat_data'
);
DELETE FROM eav_attribute WHERE attribute_code IN ('vehicle_compat_data', 'parts_required');
```

### 4. Remove the module code

```bash
rm -rf <magento-root>/app/code/ETechFlow/ProductFitmentFinder
rmdir <magento-root>/app/code/Etechflow 2>/dev/null   # only if empty
```

Or for Composer-installed:

```bash
composer remove etechflow/module-product-fitment-finder
```

### 5. Rebuild

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile           # production-mode only
bin/magento cache:flush
```

### 6. Verify

```bash
bin/magento module:status ETechFlow_ProductFitmentFinder
mysql -e "SHOW TABLES LIKE 'etechflow_vehicle%'"
curl -I https://your-store.example.com/kvc/options/index?field=make
# Expected: 404
```

---

## Removing only the Part Finder widget but keeping the data

If you want to keep the Make/Model data and product attributes but disable just the storefront widget:

In your theme, add `app/design/frontend/<Vendor>/<theme>/ETechFlow_ProductFitmentFinder/layout/default.xml`:

```xml
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <remove src="ETechFlow_ProductFitmentFinder::js/alpine-bootstrap.js"/>
        <remove src="ETechFlow_ProductFitmentFinder::js/kvc-part-finder.js"/>
    </head>
</page>
```

The admin Make/Model CRUD + product editor tab + REST endpoint continue to work — just no widget loads on the storefront.
