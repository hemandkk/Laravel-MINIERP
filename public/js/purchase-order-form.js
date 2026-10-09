/**
 * Purchase-order form: dynamic line items with live subtotal / grand-total
 * estimates. All arithmetic is done in integer cents to avoid floating-point
 * rounding errors. These totals are for display only - the server always
 * recalculates the authoritative totals from the submitted line items.
 */
(function () {
    'use strict';

    var form = document.getElementById('purchase-order-form');
    var template = document.getElementById('line-item-template');

    if (!form || !template) {
        return;
    }

    var tbody = form.querySelector('[data-line-items]');
    var grandTotalCell = form.querySelector('[data-grand-total]');
    var addButton = form.querySelector('[data-add-line]');
    var nextIndex = parseInt(tbody.getAttribute('data-next-index'), 10) || tbody.children.length;

    function toCents(value) {
        var text = String(value).trim();

        if (!/^\d+(\.\d{0,2})?$/.test(text)) {
            return null;
        }

        var parts = text.split('.');
        var fraction = ((parts[1] || '') + '00').slice(0, 2);

        return parseInt(parts[0], 10) * 100 + parseInt(fraction, 10);
    }

    function formatCents(cents) {
        var whole = Math.floor(cents / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var fraction = String(cents % 100).padStart(2, '0');

        return whole + '.' + fraction;
    }

    function rows() {
        return Array.prototype.slice.call(tbody.querySelectorAll('[data-line-item]'));
    }

    function flagDuplicateProducts() {
        var seen = {};

        rows().forEach(function (row) {
            var select = row.querySelector('[data-product]');
            var value = select.value;
            var duplicate = value !== '' && Object.prototype.hasOwnProperty.call(seen, value);

            select.classList.toggle('border-warning', duplicate);
            select.title = duplicate ? 'This product is already on another line.' : '';
            seen[value] = true;
        });
    }

    function recalculate() {
        var total = 0;
        var allRows = rows();

        allRows.forEach(function (row) {
            var quantity = parseInt(row.querySelector('[data-quantity]').value, 10);
            var priceCents = toCents(row.querySelector('[data-unit-price]').value);
            var subtotal = (Number.isInteger(quantity) && quantity > 0 && priceCents !== null) ? quantity * priceCents : 0;

            row.querySelector('[data-subtotal]').textContent = formatCents(subtotal);
            total += subtotal;
        });

        grandTotalCell.textContent = formatCents(total);

        allRows.forEach(function (row) {
            row.querySelector('[data-remove-line]').disabled = allRows.length === 1;
        });

        flagDuplicateProducts();
    }

    function addLine() {
        tbody.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(nextIndex)));
        nextIndex += 1;

        var newRow = tbody.lastElementChild;
        recalculate();
        newRow.querySelector('[data-product]').focus();
    }

    tbody.addEventListener('change', function (event) {
        if (event.target.matches('[data-product]')) {
            var row = event.target.closest('[data-line-item]');
            var priceInput = row.querySelector('[data-unit-price]');
            var option = event.target.selectedOptions[0];

            // Pre-fill the product's baseline cost unless the user has typed their own price.
            if (option && option.getAttribute('data-price') && (priceInput.value === '' || priceInput.dataset.autofilled === '1')) {
                priceInput.value = option.getAttribute('data-price');
                priceInput.dataset.autofilled = '1';
            }
        }

        recalculate();
    });

    tbody.addEventListener('input', function (event) {
        if (event.target.matches('[data-unit-price]')) {
            event.target.dataset.autofilled = '0';
        }

        recalculate();
    });

    tbody.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-line]');

        if (!button || rows().length <= 1) {
            return;
        }

        button.closest('[data-line-item]').remove();
        recalculate();
    });

    addButton.addEventListener('click', addLine);

    if (rows().length === 0) {
        addLine();
    } else {
        recalculate();
    }
})();
