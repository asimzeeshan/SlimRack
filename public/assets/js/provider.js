/**
 * SlimRack - Provider Management JavaScript
 */

(function($) {
    'use strict';

    let currentProviderId = null;

    // Initialize on document ready
    $(document).ready(function() {
        // Load providers when modal opens
        $('#providerModal').on('show.bs.modal', loadProviders);

        // Form submission
        $('#providerForm').on('submit', function(e) {
            e.preventDefault();
            saveProvider();
        });

        // Cancel edit button
        $('#cancelProviderEdit').on('click', resetProviderForm);
    });

    // Load providers list
    function loadProviders() {
        SlimRack.ajax({
            url: '/ajax/providers',
            method: 'GET'
        }).done(function(response) {
            if (response.success) {
                renderProviderList(response.data);
            }
        });
    }

    // Render provider list
    function renderProviderList(providers) {
        const $tbody = $('#providerTableBody');
        $tbody.empty();

        if (providers.length === 0) {
            $tbody.append('<tr><td colspan="5" class="text-center text-muted">No providers yet</td></tr>');
            return;
        }

        providers.forEach(function(p) {
            const row = `
                <tr data-id="${p.provider_id}">
                    <td><strong>${escapeHtml(p.name)}</strong></td>
                    <td>
                        ${p.website ? `<a href="${escapeHtml(p.website)}" target="_blank" class="text-decoration-none">
                            <i class="bi bi-box-arrow-up-right me-1"></i>${escapeHtml(p.website)}
                        </a>` : '<span class="text-muted">-</span>'}
                    </td>
                    <td>
                        ${p.control_panel_url ? `<a href="${escapeHtml(p.control_panel_url)}" target="_blank" class="text-decoration-none">
                            ${escapeHtml(p.control_panel_name || 'Control Panel')}
                        </a>` : (p.control_panel_name || '<span class="text-muted">-</span>')}
                    </td>
                    <td><span class="badge bg-secondary">${p.machine_count}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-provider" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-provider" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });

        // Bind edit buttons
        $tbody.find('.btn-edit-provider').on('click', function() {
            const row = $(this).closest('tr');
            const id = row.data('id');
            editProvider(id);
        });

        // Bind delete buttons
        $tbody.find('.btn-delete-provider').on('click', function() {
            const row = $(this).closest('tr');
            const id = row.data('id');
            const name = row.find('td:first strong').text();
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                deleteProvider(id);
            }
        });
    }

    // Edit provider
    function editProvider(id) {
        SlimRack.ajax({
            url: `/ajax/providers/${id}`,
            method: 'GET'
        }).done(function(response) {
            if (response.success) {
                const p = response.data;
                currentProviderId = p.provider_id;

                $('#providerId').val(p.provider_id);
                $('#providerName').val(p.name);
                $('#providerWebsite').val(p.website);
                $('#providerCpName').val(p.control_panel_name);
                $('#providerCpUrl').val(p.control_panel_url);

                $('#providerFormTitle').text('Edit Provider');
                $('#providerSubmit .btn-text').text('Update Provider');
                $('#cancelProviderEdit').show();
            }
        });
    }

    // Save provider
    function saveProvider() {
        const $btn = $('#providerSubmit');
        const $spinner = $btn.find('.spinner-border');
        const $text = $btn.find('.btn-text');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');

        const data = {
            name: $('#providerName').val(),
            website: $('#providerWebsite').val(),
            control_panel_name: $('#providerCpName').val(),
            control_panel_url: $('#providerCpUrl').val()
        };

        const isEdit = currentProviderId !== null;
        const url = isEdit ? `/ajax/providers/${currentProviderId}` : '/ajax/providers';
        const method = isEdit ? 'PUT' : 'POST';

        SlimRack.ajax({
            url: url,
            method: method,
            data: JSON.stringify(data),
            contentType: 'application/json'
        }).done(function(response) {
            if (response.success) {
                SlimRack.toast(isEdit ? 'Provider updated' : 'Provider created');
                resetProviderForm();
                loadProviders();

                // Update global providers list
                SlimRack.ajax({
                    url: '/ajax/providers',
                    method: 'GET'
                }).done(function(res) {
                    if (res.success) {
                        SlimRack.providers = res.data;
                        // Refresh machine form dropdowns
                        const $provider = $('#machineProvider');
                        $provider.empty().append('<option value="">Select...</option>');
                        res.data.forEach(p => {
                            $provider.append(`<option value="${p.provider_id}">${p.name}</option>`);
                        });
                    }
                });
            } else {
                showProviderErrors(response.errors);
            }
        }).fail(function(xhr) {
            const response = xhr.responseJSON;
            if (response?.errors) {
                showProviderErrors(response.errors);
            } else {
                SlimRack.toast('Failed to save provider', 'danger');
            }
        }).always(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        });
    }

    // Delete provider
    function deleteProvider(id) {
        SlimRack.ajax({
            url: `/ajax/providers/${id}`,
            method: 'DELETE'
        }).done(function(response) {
            if (response.success) {
                SlimRack.toast('Provider deleted');
                loadProviders();
            }
        }).fail(function() {
            SlimRack.toast('Failed to delete provider', 'danger');
        });
    }

    // Reset provider form
    function resetProviderForm() {
        currentProviderId = null;
        $('#providerForm')[0].reset();
        $('#providerId').val('');
        $('#providerFormTitle').text('Add New Provider');
        $('#providerSubmit .btn-text').text('Add Provider');
        $('#cancelProviderEdit').hide();

        // Clear errors
        $('#providerForm .is-invalid').removeClass('is-invalid');
        $('#providerForm .invalid-feedback').remove();
    }

    // Show form errors
    function showProviderErrors(errors) {
        $('#providerForm .is-invalid').removeClass('is-invalid');
        $('#providerForm .invalid-feedback').remove();

        for (const [field, message] of Object.entries(errors)) {
            const $input = $(`#provider${field.charAt(0).toUpperCase() + field.slice(1)}`);
            if ($input.length) {
                $input.addClass('is-invalid');
                $input.after(`<div class="invalid-feedback">${message}</div>`);
            }
        }
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
