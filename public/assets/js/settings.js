/**
 * SlimRack - Settings JavaScript
 */

(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        // Load settings when modal opens
        $('#settingsModal').on('show.bs.modal', loadSettings);

        // Theme change
        $('input[name="theme"]').on('change', function() {
            updateTheme(this.value);
        });

        // Page length change
        $('#settingsPageLength').on('change', function() {
            updatePageLength(parseInt(this.value));
        });

        // Add currency button
        $('#btnAddCurrency').on('click', function() {
            $('#addCurrencyModal').modal('show');
        });

        // Add currency form
        $('#addCurrencyForm').on('submit', function(e) {
            e.preventDefault();
            addCurrency();
        });
    });

    // Load settings
    function loadSettings() {
        // Set current theme
        const theme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        $(`input[name="theme"][value="${theme}"]`).prop('checked', true);

        // Set page length
        $('#settingsPageLength').val(SlimRack.pageLength || 25);

        // Load currencies
        renderCurrencyRates();
    }

    // Update theme
    function updateTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);

        SlimRack.ajax({
            url: '/ajax/settings',
            method: 'POST',
            data: JSON.stringify({ theme: theme }),
            contentType: 'application/json'
        }).done(function(response) {
            if (response.success) {
                SlimRack.toast('Theme updated');
            }
        });
    }

    // Update page length
    function updatePageLength(length) {
        SlimRack.pageLength = length;

        SlimRack.ajax({
            url: '/ajax/settings',
            method: 'POST',
            data: JSON.stringify({ page_length: length }),
            contentType: 'application/json'
        }).done(function(response) {
            if (response.success) {
                // Update DataTable
                if ($.fn.DataTable && $('#machinesTable').length) {
                    $('#machinesTable').DataTable().page.len(length).draw();
                }
                SlimRack.toast('Page length updated');
            }
        });
    }

    // Render currency rates list
    function renderCurrencyRates() {
        const $container = $('#currencyRatesList');
        $container.empty();

        SlimRack.currencies.forEach(function(c) {
            const isUsd = c.currency_code === 'USD';
            const rate = (c.rate / 10000).toFixed(4);

            const item = `
                <div class="d-flex align-items-center mb-2" data-code="${c.currency_code}">
                    <span class="fw-bold me-2" style="width: 50px;">${c.currency_code}</span>
                    <input type="number" class="form-control form-control-sm me-2 currency-rate-input"
                           value="${rate}" step="0.0001" min="0.0001" ${isUsd ? 'disabled' : ''}>
                    ${isUsd ? '<span class="text-muted">(Base)</span>' :
                      '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-currency"><i class="bi bi-x"></i></button>'}
                </div>
            `;
            $container.append(item);
        });

        // Bind rate change
        $container.find('.currency-rate-input').on('change', function() {
            const code = $(this).closest('[data-code]').data('code');
            const rate = parseFloat(this.value);
            updateCurrencyRate(code, rate);
        });

        // Bind delete buttons
        $container.find('.btn-delete-currency').on('click', function() {
            const code = $(this).closest('[data-code]').data('code');
            if (confirm(`Delete currency ${code}?`)) {
                deleteCurrency(code);
            }
        });
    }

    // Update currency rate
    function updateCurrencyRate(code, rate) {
        SlimRack.ajax({
            url: `/ajax/currencies/${code}`,
            method: 'PUT',
            data: JSON.stringify({ rate: rate }),
            contentType: 'application/json'
        }).done(function(response) {
            if (response.success) {
                // Update local data
                const currency = SlimRack.currencies.find(c => c.currency_code === code);
                if (currency) {
                    currency.rate = Math.round(rate * 10000);
                }
                SlimRack.toast('Rate updated');
            }
        }).fail(function() {
            SlimRack.toast('Failed to update rate', 'danger');
        });
    }

    // Add currency
    function addCurrency() {
        const code = $('#newCurrencyCode').val().toUpperCase();
        const rate = parseFloat($('#newCurrencyRate').val());

        SlimRack.ajax({
            url: '/ajax/currencies',
            method: 'POST',
            data: JSON.stringify({
                currency_code: code,
                rate: rate
            }),
            contentType: 'application/json'
        }).done(function(response) {
            if (response.success) {
                // Add to local data
                SlimRack.currencies.push({
                    currency_code: code,
                    rate: Math.round(rate * 10000)
                });

                // Update dropdowns
                $('#machineCurrency').append(`<option value="${code}">${code}</option>`);

                $('#addCurrencyModal').modal('hide');
                $('#addCurrencyForm')[0].reset();
                renderCurrencyRates();
                SlimRack.toast('Currency added');
            } else {
                SlimRack.toast(response.errors?.currency_code || 'Failed to add currency', 'danger');
            }
        }).fail(function(xhr) {
            const response = xhr.responseJSON;
            SlimRack.toast(response?.errors?.currency_code || 'Failed to add currency', 'danger');
        });
    }

    // Delete currency
    function deleteCurrency(code) {
        SlimRack.ajax({
            url: `/ajax/currencies/${code}`,
            method: 'DELETE'
        }).done(function(response) {
            if (response.success) {
                // Remove from local data
                SlimRack.currencies = SlimRack.currencies.filter(c => c.currency_code !== code);

                // Remove from dropdown
                $(`#machineCurrency option[value="${code}"]`).remove();

                renderCurrencyRates();
                SlimRack.toast('Currency deleted');
            }
        }).fail(function() {
            SlimRack.toast('Failed to delete currency', 'danger');
        });
    }

})(jQuery);
