/**
 * SlimRack - Machine Management JavaScript
 */

(function($) {
    'use strict';

    let dataTable;
    let currentMachineId = null;
    let deleteCallback = null;

    // Initialize on document ready
    $(document).ready(function() {
        initDataTable();
        initMachineForm();
        initEventHandlers();
        populateDropdowns();
    });

    // Initialize DataTable
    function initDataTable() {
        dataTable = $('#machinesTable').DataTable({
            pageLength: SlimRack.pageLength || 25,
            order: [[6, 'asc']], // Sort by due date
            columnDefs: [
                { orderable: false, targets: [0, 7] },
                { className: 'text-center', targets: [0, 7] }
            ],
            language: {
                emptyTable: 'No machines found',
                zeroRecords: 'No matching machines found'
            }
        });
    }

    // Initialize machine form
    function initMachineForm() {
        $('#machineForm').on('submit', function(e) {
            e.preventDefault();
            saveMachine();
        });

        // Initialize datepicker
        $('#machineDueDate').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });

        // Status toggle label update
        $('#machineIsActive').on('change', function() {
            const isActive = $(this).prop('checked');
            $('#statusLabel').text(isActive ? 'Active' : 'Inactive');
            $('.status-toggle-container')
                .toggleClass('bg-light', isActive)
                .toggleClass('bg-warning-subtle', !isActive);
        });
    }

    // Initialize event handlers
    function initEventHandlers() {
        // Add new machine button
        $('#btnAddMachine').on('click', function() {
            resetMachineForm();
            $('#machineModalTitle').text('Add Machine');
            currentMachineId = null;
        });

        // Edit machine button
        $('#machinesTable').on('click', '.btn-edit', function() {
            const row = $(this).closest('tr');
            const id = row.data('id');
            editMachine(id);
        });

        // Delete machine button
        $('#machinesTable').on('click', '.btn-delete', function() {
            const row = $(this).closest('tr');
            const id = row.data('id');
            const label = row.find('td:eq(1) strong').text();
            showDeleteConfirm(id, label);
        });

        // Renew button
        $('#machinesTable').on('click', '.btn-renew', function() {
            const row = $(this).closest('tr');
            const id = row.data('id');
            renewMachine(id, row);
        });

        // Toggle status button (active/inactive)
        $('#machinesTable').on('click', '.btn-toggle-status', function() {
            const row = $(this).closest('tr');
            const id = row.data('id');
            toggleStatus(id, row);
        });

        // Select all checkbox
        $('#selectAll').on('change', function() {
            $('.machine-checkbox').prop('checked', this.checked);
            updateDeleteButton();
        });

        // Individual checkboxes
        $('#machinesTable').on('change', '.machine-checkbox', function() {
            updateDeleteButton();
        });

        // Delete selected button
        $('#btnDeleteSelected').on('click', function() {
            const ids = $('.machine-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            showBatchDeleteConfirm(ids);
        });

        // Show inactive toggle
        $('#showInactive').on('change', function() {
            const showInactive = $(this).prop('checked');
            dataTable.rows().every(function() {
                const row = this.node();
                if ($(row).hasClass('table-secondary')) {
                    $(row).toggle(showInactive);
                }
            });
        });

        // Confirm delete button
        $('#confirmDelete').on('click', function() {
            if (deleteCallback) {
                deleteCallback();
            }
        });
    }

    // Populate dropdown options
    function populateDropdowns() {
        // Providers
        const $provider = $('#machineProvider');
        $provider.empty().append('<option value="">Select...</option>');
        SlimRack.providers.forEach(p => {
            $provider.append(`<option value="${p.provider_id}">${p.name}</option>`);
        });

        // Countries
        const $country = $('#machineCountry');
        $country.empty().append('<option value="">Select...</option>');
        SlimRack.countries.forEach(c => {
            $country.append(`<option value="${c.country_code}">${c.country_name}</option>`);
        });

        // Currencies
        const $currency = $('#machineCurrency');
        $currency.empty();
        SlimRack.currencies.forEach(c => {
            $currency.append(`<option value="${c.currency_code}">${c.currency_code}</option>`);
        });

        // Payment cycles
        const $cycle = $('#machinePaymentCycle');
        $cycle.empty().append('<option value="">Select...</option>');
        SlimRack.paymentCycles.forEach(pc => {
            $cycle.append(`<option value="${pc.payment_cycle_id}">${pc.name}</option>`);
        });
    }

    // Reset machine form
    function resetMachineForm() {
        $('#machineForm')[0].reset();
        $('#machineId').val('');
        $('#machineCurrency').val('USD');
        // Reset status toggle to Active
        $('#machineIsActive').prop('checked', true).trigger('change');
    }

    // Edit machine
    function editMachine(id) {
        SlimRack.ajax({
            url: `/ajax/machines/${id}`,
            method: 'GET'
        }).done(function(response) {
            if (response.success) {
                const m = response.data;
                currentMachineId = m.machine_id;
                $('#machineModalTitle').text('Edit Machine');

                // Populate form
                $('#machineId').val(m.machine_id);
                $('#machineLabel').val(m.label);
                $('#machineVirtualization').val(m.virtualization);
                $('#machineProvider').val(m.provider_id);
                $('#machineCountry').val(m.country_code);
                $('#machineCity').val(m.city_name);
                $('#machineCpuCore').val(m.cpu_core);
                $('#machineCpuSpeed').val(m.cpu_speed);
                $('#machineMemory').val(m.memory);
                $('#machineSwap').val(m.swap);
                $('#machineDiskType').val(m.disk_type);
                $('#machineDiskSpace').val(m.disk_space);
                $('#machineBandwidth').val(m.bandwidth);
                $('#machineIpAddress').val(m.ip_address);
                $('#machineIsNat').prop('checked', m.is_nat == 1);
                $('#machinePrice').val(m.price / 100);
                $('#machineCurrency').val(m.currency_code);
                $('#machinePaymentCycle').val(m.payment_cycle_id);
                $('#machineDueDate').val(m.due_date);
                $('#machineNotes').val(m.notes);
                // Status toggle: is_hidden=1 means Inactive, so we invert for Active toggle
                $('#machineIsActive').prop('checked', m.is_hidden != 1).trigger('change');

                $('#machineModal').modal('show');
            }
        }).fail(function() {
            SlimRack.toast('Failed to load machine', 'danger');
        });
    }

    // Save machine
    function saveMachine() {
        const $btn = $('#machineSubmit');
        const $spinner = $btn.find('.spinner-border');
        const $text = $btn.find('.btn-text');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $text.text('Saving...');

        const formData = new FormData($('#machineForm')[0]);
        const data = Object.fromEntries(formData.entries());
        // Convert status toggle to is_hidden: Active=checked means is_hidden=0
        data.is_hidden = $('#machineIsActive').prop('checked') ? '0' : '1';

        const isEdit = currentMachineId !== null;
        const url = isEdit ? `/ajax/machines/${currentMachineId}` : '/ajax/machines';
        const method = isEdit ? 'PUT' : 'POST';

        SlimRack.ajax({
            url: url,
            method: method,
            data: JSON.stringify(data),
            contentType: 'application/json'
        }).done(function(response) {
            if (response.success) {
                SlimRack.toast(isEdit ? 'Machine updated successfully' : 'Machine created successfully');
                $('#machineModal').modal('hide');
                location.reload(); // Refresh to show updated data
            } else {
                showFormErrors(response.errors);
            }
        }).fail(function(xhr) {
            const response = xhr.responseJSON;
            if (response?.errors) {
                showFormErrors(response.errors);
            } else {
                SlimRack.toast('Failed to save machine', 'danger');
            }
        }).always(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $text.text('Save Machine');
        });
    }

    // Show form errors
    function showFormErrors(errors) {
        // Clear previous errors
        $('#machineForm .is-invalid').removeClass('is-invalid');
        $('#machineForm .invalid-feedback').remove();

        for (const [field, message] of Object.entries(errors)) {
            const $input = $(`#machine${field.charAt(0).toUpperCase() + field.slice(1)}`);
            if ($input.length) {
                $input.addClass('is-invalid');
                $input.after(`<div class="invalid-feedback">${message}</div>`);
            }
        }
    }

    // Show delete confirmation
    function showDeleteConfirm(id, label) {
        $('#deleteMessage').text(`Are you sure you want to delete "${label}"?`);
        deleteCallback = function() {
            deleteMachine(id);
        };
        $('#deleteModal').modal('show');
    }

    // Show batch delete confirmation
    function showBatchDeleteConfirm(ids) {
        $('#deleteMessage').text(`Are you sure you want to delete ${ids.length} machine(s)?`);
        deleteCallback = function() {
            batchDeleteMachines(ids);
        };
        $('#deleteModal').modal('show');
    }

    // Delete machine
    function deleteMachine(id) {
        SlimRack.ajax({
            url: `/ajax/machines/${id}`,
            method: 'DELETE'
        }).done(function(response) {
            if (response.success) {
                SlimRack.toast('Machine deleted successfully');
                $('#deleteModal').modal('hide');
                $(`#machinesTable tr[data-id="${id}"]`).remove();
                dataTable.row($(`#machinesTable tr[data-id="${id}"]`)).remove().draw();
            }
        }).fail(function() {
            SlimRack.toast('Failed to delete machine', 'danger');
        });
    }

    // Batch delete machines
    function batchDeleteMachines(ids) {
        SlimRack.ajax({
            url: '/ajax/machines/batch',
            method: 'DELETE',
            data: JSON.stringify({ ids: ids }),
            contentType: 'application/json'
        }).done(function(response) {
            if (response.success) {
                SlimRack.toast(`${response.data.deleted} machine(s) deleted`);
                $('#deleteModal').modal('hide');
                location.reload();
            }
        }).fail(function() {
            SlimRack.toast('Failed to delete machines', 'danger');
        });
    }

    // Renew machine
    function renewMachine(id, row) {
        SlimRack.ajax({
            url: `/ajax/machines/${id}/renew`,
            method: 'POST'
        }).done(function(response) {
            if (response.success) {
                SlimRack.toast('Due date renewed successfully');
                // Update the row
                row.find('td:eq(6) .badge').text(response.data.due_date);
            }
        }).fail(function() {
            SlimRack.toast('Failed to renew machine', 'danger');
        });
    }

    // Toggle status (active/inactive)
    function toggleStatus(id, row) {
        SlimRack.ajax({
            url: `/ajax/machines/${id}/toggle-hidden`,
            method: 'POST'
        }).done(function(response) {
            if (response.success) {
                const isInactive = response.data.is_hidden;
                row.toggleClass('table-secondary', isInactive);
                row.find('.btn-toggle-status')
                    .attr('title', isInactive ? 'Activate' : 'Deactivate');
                row.find('.btn-toggle-status i')
                    .removeClass('bi-toggle-on bi-toggle-off')
                    .addClass(isInactive ? 'bi-toggle-off' : 'bi-toggle-on');
                SlimRack.toast(isInactive ? 'Machine marked as inactive' : 'Machine activated');

                // Hide row if "Show Inactive" is not checked
                if (isInactive && !$('#showInactive').prop('checked')) {
                    row.hide();
                }
            }
        }).fail(function() {
            SlimRack.toast('Failed to toggle status', 'danger');
        });
    }

    // Update delete button state
    function updateDeleteButton() {
        const count = $('.machine-checkbox:checked').length;
        $('#btnDeleteSelected').prop('disabled', count === 0);
    }

})(jQuery);
