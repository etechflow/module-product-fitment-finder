/**
 * Model dropdown: empty until a Make is picked in the same row.
 * When Make changes, options re-filter to that Make's models only.
 */
define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry',
    'underscore'
], function (Select, registry, _) {
    'use strict';

    return Select.extend({
        defaults: {
            allOptions: [],
            initialized: false
        },

        initialize: function () {
            this._super();
            console.log('[VC-MODEL] component loaded. name=', this.name, 'options=', (this.options() || []).length);

            // Snapshot all option entries (each has value, label, make_id)
            this.allOptions = (this.options() || []).slice();

            // Start empty — user must pick a Make first
            this.options([]);

            var self = this;
            // Wait for the same-row Make field to be registered
            setTimeout(function () { self._bindToMake(); }, 0);
            return this;
        },

        _bindToMake: function () {
            var self = this;
            // My name path: "...vehicle_compat_data.<idx>.model_id"
            // Sibling Make: "...vehicle_compat_data.<idx>.make_id"
            var myName = this.name || '';
            var lastDot = myName.lastIndexOf('.');
            if (lastDot < 0) return;
            var makeName = myName.substring(0, lastDot) + '.make_id';

            console.log('[VC-MODEL] looking for makeField:', makeName);
            var attempts = 0;
            (function poll() {
                var makeField = registry.get(makeName);
                if (makeField && typeof makeField.value === 'function') {
                    console.log('[VC-MODEL] found makeField. initial value=', makeField.value());
                    // Initial filter using current value
                    self._refilter(makeField.value());
                    // Subscribe to changes
                    makeField.value.subscribe(function (newVal) {
                        console.log('[VC-MODEL] make changed to:', newVal);
                        self._refilter(newVal);
                    });
                    return;
                }
                if (++attempts < 50) setTimeout(poll, 50);
                else console.warn('[VC-MODEL] giving up — could not find', makeName);
            })();
        },

        _refilter: function (makeIdRaw) {
            var makeId = parseInt(makeIdRaw, 10);
            if (!makeId) {
                this.options([]);
                return;
            }
            var filtered = _.filter(this.allOptions, function (opt) {
                return parseInt(opt.make_id, 10) === makeId;
            });
            this.options(filtered);

            // If the currently-selected model isn't in the filtered list, clear it.
            // Type-tolerant compare: option values arrive as ints from PHP but a
            // select's value() is often a string — a strict _.findWhere() would
            // fail to match a legitimately-selected model and null out model_id,
            // which then got dropped on save. Compare as ints instead.
            var cur = parseInt(this.value(), 10);
            if (cur) {
                var stillValid = _.some(filtered, function (o) {
                    return parseInt(o.value, 10) === cur;
                });
                if (!stillValid) {
                    this.value(null);
                }
            }
        }
    });
});
