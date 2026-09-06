/**
 * Location Management
 */

'use strict';

// Use global jQuery to ensure compatibility with plugins (DataTable, Select2) loaded via script tags
const $ = window.jQuery;
const baseUrl = window.baseUrl ? (window.baseUrl.endsWith('/') ? window.baseUrl : window.baseUrl + '/') : '/';

$(function () {
    var dt_location_table = $('.datatables-locations'),
        dt_district_table = $('.datatables-districts'),
        dt_state_table = $('.datatables-states');

    // =========================================================================
    // 1. VILLAGES DATATABLE
    // =========================================================================
    if (dt_location_table.length) {
        var dt_location = dt_location_table.DataTable({
            processing: true,
            serverSide: true,
            ajax: { url: baseUrl + 'location-management/data', type: 'GET' },
            columns: [
                { data: 's_no' },
                { data: 'name' },
                { data: 'city' },
                { data: 'state' },
                { data: 'pincode' },
                { data: null }
            ],
            columnDefs: [
                {
                    targets: -1,
                    title: 'Actions',
                    searchable: false,
                    orderable: false,
                    render: function (data, type, full, meta) {
                        return (
                            '<div class="d-flex align-items-center justify-content-center gap-2">' +
                            '<button class="btn btn-sm btn-icon btn-text-primary edit-village" data-id="' + full['id'] + '">' +
                            '<i class="ri-edit-box-line ri-20px"></i>' +
                            '</button>' +
                            '<button class="btn btn-sm btn-icon btn-text-danger waves-effect delete-record" data-type="village" data-id="' + full['id'] + '" data-name="' + full['name'] + '">' +
                            '<i class="ri-delete-bin-7-line ri-20px"></i>' +
                            '</button>' +
                            '</div>'
                        );
                    }
                }
            ],
            order: [[0, 'asc']],
            dom: '<"row mx-1"<"col-sm-12 col-md-3" l><"col-sm-12 col-md-9" f>>t<"row mx-1"<"col-sm-12 col-md-6" i><"col-sm-12 col-md-6" p>>',
            language: { sLengthMenu: 'Show _MENU_', search: '', searchPlaceholder: 'Search Zone' }
        });
    }

    // =========================================================================
    // 2. DISTRICTS DATATABLE
    // =========================================================================
    if (dt_district_table.length) {
        var dt_district = dt_district_table.DataTable({
            processing: true,
            serverSide: true,
            ajax: { url: baseUrl + 'location-management/districts/data', type: 'GET' },
            columns: [
                { data: 's_no' },
                { data: 'name' },
                { data: 'state' },
                { data: null }
            ],
            columnDefs: [
                {
                    targets: -1,
                    title: 'Actions',
                    className: 'text-center',
                    render: function (data, type, full, meta) {
                        return (
                            '<div class="d-flex align-items-center justify-content-center gap-2">' +
                            '<button class="btn btn-sm btn-icon btn-text-primary edit-district" data-id="' + full['id'] + '" data-name="' + full['name'] + '" data-state-id="' + full['state_id'] + '">' +
                            '<i class="ri-edit-box-line ri-20px"></i>' +
                            '</button>' +
                            '<button class="btn btn-sm btn-icon btn-text-danger delete-record" data-type="district" data-id="' + full['id'] + '" data-name="' + full['name'] + '">' +
                            '<i class="ri-delete-bin-7-line ri-20px"></i>' +
                            '</button>' +
                            '</div>'
                        );
                    }
                }
            ],
            order: [[0, 'asc']],
            dom: '<"row mx-1"<"col-sm-12 col-md-3" l><"col-sm-12 col-md-9" f>>t<"row mx-1"<"col-sm-12 col-md-6" i><"col-sm-12 col-md-6" p>>',
            language: { sLengthMenu: 'Show _MENU_', search: '', searchPlaceholder: 'Search District' }
        });
    }

    // =========================================================================
    // 3. STATES DATATABLE
    // =========================================================================
    if (dt_state_table.length) {
        var dt_state = dt_state_table.DataTable({
            processing: true,
            serverSide: true,
            ajax: { url: baseUrl + 'location-management/states/data', type: 'GET' },
            columns: [
                { data: 's_no' },
                { data: 'name' },
                { data: null }
            ],
            columnDefs: [
                {
                    targets: -1,
                    title: 'Actions',
                    className: 'text-center',
                    render: function (data, type, full, meta) {
                        return (
                            '<div class="d-flex align-items-center justify-content-center gap-2">' +
                            '<button class="btn btn-sm btn-icon btn-text-primary edit-state" data-id="' + full['id'] + '" data-name="' + full['name'] + '">' +
                            '<i class="ri-edit-box-line ri-20px"></i>' +
                            '</button>' +
                            '<button class="btn btn-sm btn-icon btn-text-danger delete-record" data-type="state" data-id="' + full['id'] + '" data-name="' + full['name'] + '">' +
                            '<i class="ri-delete-bin-7-line ri-20px"></i>' +
                            '</button>' +
                            '</div>'
                        );
                    }
                }
            ],
            order: [[0, 'asc']],
            dom: '<"row mx-1"<"col-sm-12 col-md-3" l><"col-sm-12 col-md-9" f>>t<"row mx-1"<"col-sm-12 col-md-6" i><"col-sm-12 col-md-6" p>>',
            language: { sLengthMenu: 'Show _MENU_', search: '', searchPlaceholder: 'Search State' }
        });
    }

    // =========================================================================
    // DELETE HANDLER (Shared)
    // =========================================================================
    $('body').on('click', '.delete-record', function () {
        const id = $(this).data('id'),
              name = $(this).data('name'),
              type = $(this).data('type');

        Swal.fire({
            title: 'Delete ' + type + '?',
            text: "Are you sure you want to delete '" + name + "'?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: baseUrl + 'location-management/' + type + '/' + id,
                    type: 'DELETE',
                    data: { _token: $('meta[name="csrf-token"]').attr('content') },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ 
                                icon: 'success', 
                                title: 'Deleted!', 
                                text: response.message, 
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: { confirmButton: 'btn btn-success' } 
                            }).then(() => {
                                try {
                                    // Dynamic Reload using selectors for robustness
                                    if (type === 'village') $('.datatables-locations').DataTable().ajax.reload(null, false);
                                    if (type === 'district') $('.datatables-districts').DataTable().ajax.reload(null, false);
                                    if (type === 'state') $('.datatables-states').DataTable().ajax.reload(null, false);
                                } catch (e) {
                                    console.error('DataTable reload failed:', e);
                                }
                            });
                        } else {
                            Swal.fire({ title: 'Error!', text: response.message, icon: 'error' });
                        }
                    }
                });
            }
        });
    });

    // =========================================================================
    // FORM SUBMISSIONS
    // =========================================================================

    // Add State
    const stateForm = document.getElementById('addStateForm');
    if (stateForm) {
        stateForm.addEventListener('submit', function (e) {
            e.preventDefault();
            ajaxSubmit(this, baseUrl + 'location-management/states/store', '#addStateModal', '.datatables-states', '#btnStateSave');
        });
    }

    // Add District
    const districtForm = document.getElementById('addDistrictForm');
    if (districtForm) {
        districtForm.addEventListener('submit', function (e) {
            e.preventDefault();
            ajaxSubmit(this, baseUrl + 'location-management/districts/store', '#addDistrictModal', '.datatables-districts', '#btnDistrictSave');
        });
    }

    // Add Village (Manual)
    const villageForm = document.getElementById('manualAddLocationForm');
    if (villageForm) {
        villageForm.addEventListener('submit', function (e) {
            e.preventDefault();
            ajaxSubmit(this, baseUrl + 'location-management/store', '#manualAddLocationModal', '.datatables-locations', '#btnManualSave');
        });
    }

    // Edit State
    const editStateForm = document.getElementById('editStateForm');
    if (editStateForm) {
        editStateForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = $('#editStateId').val();
            ajaxSubmit(this, baseUrl + 'location-management/states/' + id + '/update', '#editStateModal', '.datatables-states', '#btnStateUpdate');
        });
    }

    // Edit District
    const editDistrictForm = document.getElementById('editDistrictForm');
    if (editDistrictForm) {
        editDistrictForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = $('#editDistrictId').val();
            ajaxSubmit(this, baseUrl + 'location-management/districts/' + id + '/update', '#editDistrictModal', '.datatables-districts', '#btnDistrictUpdate');
        });
    }

    // Edit Village
    const editVillageForm = document.getElementById('editVillageForm');
    if (editVillageForm) {
        editVillageForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = $('#editVillageId').val();
            ajaxSubmit(this, baseUrl + 'location-management/villages/' + id + '/update', '#editVillageModal', '.datatables-locations', '#btnVillageUpdate');
        });
    }

    // Populate Edit State Modal
    $('body').on('click', '.edit-state', function () {
        const id = $(this).data('id'),
              name = $(this).data('name');
        $('#editStateId').val(id);
        $('#editStateName').val(name);
        $('#editStateModal').modal('show');
    });

    // Populate Edit District Modal
    $('body').on('click', '.edit-district', function () {
        const id = $(this).data('id'),
              name = $(this).data('name'),
              stateId = $(this).data('state-id');
        $('#editDistrictId').val(id);
        $('#editDistrictName').val(name);
        $('#editDistrictStateId').val(stateId).trigger('change');
        $('#editDistrictModal').modal('show');
    });

    // Populate Edit Village Modal
    $('body').on('click', '.edit-village', function () {
        const id = $(this).data('id');
        const $stateSelect = $('#editVillageState');
        const $distSelect = $('#editVillageDistrict');
        
        // Show loading state or clear previous values
        $('#editVillageName').val('Loading...');
        
        $.get(baseUrl + 'location-management/villages/' + id + '/data', function (response) {
            if (response.success) {
                const v = response.village;
                $('#editVillageId').val(v.id);
                $('#editVillageName').val(v.name);
                
                // Clear and wait for districts
                $distSelect.html('<option value="" disabled selected>Loading districts...</option>').prop('disabled', true);
                
                // Set state and trigger change
                $stateSelect.val(v.state_id).trigger('change');
                
                // Use a namespace for the event to avoid conflicts
                $(document).off('districtsLoaded.editVillage').on('districtsLoaded.editVillage', function() {
                    $distSelect.val(v.district_id).trigger('change.select2');
                    $(document).off('districtsLoaded.editVillage');
                });
                
                $('#editVillageModal').modal('show');
            }
        });
    });

    // Edit Village Modal: State Change -> Load Local Districts
    $('#editVillageState').on('change', function () {
        const stateId = $(this).val(),
              $distSelect = $('#editVillageDistrict');
        
        if (!stateId) return;
        
        $distSelect.html('<option value="" disabled selected>Loading...</option>').prop('disabled', true);
        $.get(baseUrl + 'location-management/districts/local/' + stateId, function (response) {
            if (response.success && response.districts) {
                let options = '<option value="" disabled selected>Select District</option>';
                response.districts.forEach(d => { options += `<option value="${d.id}">${d.name}</option>`; });
                $distSelect.html(options).prop('disabled', false);
                $(document).trigger('districtsLoaded');
            }
        });
    });

    // Fetch API Logic
    const fetchForm = document.getElementById('fetchLocationForm');
    if (fetchForm) {
        fetchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnFetch'),
                  orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

            $.ajax({
                url: baseUrl + 'location-management/fetch',
                type: 'POST',
                data: $(this).serialize() + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function (response) {
                $('#fetchLocationModal').modal('hide');
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Synced!', 
                    text: response.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    try {
                        $('.datatables-locations').DataTable().ajax.reload(null, false);
                        $('.datatables-districts').DataTable().ajax.reload(null, false);
                        $('.datatables-states').DataTable().ajax.reload(null, false);
                    } catch (e) {
                        console.error('DataTable reload failed:', e);
                    }
                });
            },
            error: function (xhr) {
                Swal.fire({ title: 'Fetch Error', text: xhr.responseJSON?.message || 'Check logs', icon: 'error' });
            },
            complete: function () { btn.disabled = false; btn.innerHTML = orig; }
        });
    });
}

// Helper for AJAX Submits
function ajaxSubmit(form, url, modalId, tableSelector, btnId) {
    const btn = $(btnId), orig = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

    $.ajax({
        url: url,
        type: 'POST',
        data: $(form).serialize() + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
        success: function (response) {
            $(modalId).modal('hide');
            form.reset();
            
            // Trigger custom event for other components to react
            $(document).trigger('customAjaxSuccess', { url: url, response: response, modalId: modalId });

            Swal.fire({ 
                icon: 'success', 
                title: 'Saved!', 
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
                customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
                try {
                    if (tableSelector && $(tableSelector).length) {
                        $(tableSelector).DataTable().ajax.reload(null, false);
                    }
                } catch (e) {
                    console.error('DataTable reload failed for ' + tableSelector, e);
                }
            });
        },
        error: function (xhr) {
            Swal.fire({ title: 'Error', text: xhr.responseJSON?.message || 'Check inputs', icon: 'error' });
        },
        complete: function () { btn.prop('disabled', false).html(orig); }
    });
}

    // =========================================================================
    // DYNAMIC DROPDOWNS & SELECT2
    // =========================================================================
 
    $('#manualAddLocationModal .select2-local').select2({ dropdownParent: $('#manualAddLocationModal') });
    $('#addDistrictModal select[name="state_id"]').select2({ dropdownParent: $('#addDistrictModal') });
    $('#editDistrictModal .select2-local').select2({ dropdownParent: $('#editDistrictModal') });
    $('#editVillageModal .form-select').select2({ dropdownParent: $('#editVillageModal') });
    $('.select2-api').select2({ dropdownParent: $('#fetchLocationModal') });

    // Local State Change -> Load Local Districts
    $('#manualStateSelect').on('change', function () {
        const stateId = $(this).val(),
              $distSelect = $('#manualDistrictSelect');
        
        if (!stateId) {
            $distSelect.html('<option value="" disabled selected>Select State First</option>').prop('disabled', true);
            return;
        }
        
        $distSelect.html('<option value="" disabled selected>Loading...</option>').prop('disabled', true);
        $.get(baseUrl + 'location-management/districts/local/' + stateId, function (response) {
            if (response.success && response.districts && response.districts.length > 0) {
                let options = '<option value="" disabled selected>Select District</option>';
                response.districts.forEach(d => { options += `<option value="${d.id}">${d.name}</option>`; });
                $distSelect.html(options).prop('disabled', false).trigger('change.select2');
                $(document).trigger('districtsLoaded');
            } else {
                $distSelect.html('<option value="" disabled selected>No districts found</option>').prop('disabled', true);
            }
        }).fail(function (xhr) {
            console.error('Error loading districts:', xhr);
            $distSelect.html('<option value="" disabled selected>Error loading districts</option>').prop('disabled', true);
        });
    });

    // Sync Modal: Init States from API on first open
    $('#fetchLocationModal').on('shown.bs.modal', function () {
        const $stateSelect = $('#fetchState');
        $stateSelect.html('<option value="" disabled selected>Loading API states...</option>');
        $.get(baseUrl + 'location-management/states/api', function (res) {
            const states = Array.isArray(res?.states) ? res.states : [];
            if (res.success && states.length > 0) {
                let options = '<option value="" disabled selected>Select State</option>';
                states.forEach(s => {
                    const name = typeof s === 'string' ? s : (s.name || s.state_name || s.state);
                    if (name) options += `<option value="${name}">${name}</option>`;
                });
                $stateSelect.html(options).trigger('change');
            } else {
                $stateSelect.html('<option value="" disabled selected>No states found from API</option>');
            }
        }).fail(function () {
            $stateSelect.html('<option value="" disabled selected>Failed to load states</option>');
        });
    });

    // Sync Modal: State Change -> API Districts
    $('#fetchState').on('change', function () {
        const stateName = $(this).val(),
              $distSelect = $('#fetchDistrict');
        $distSelect.html('<option value="" disabled selected>Loading districts...</option>').prop('disabled', true);
        $.ajax({
            url: baseUrl + 'location-management/districts/api',
            type: 'POST',
            data: { state: stateName, _token: $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                let options = '<option value="" disabled selected>Select District</option>';
                const districts = Array.isArray(res?.districts) ? res.districts : [];
                districts.forEach(d => {
                    const districtName = typeof d === 'string' ? d : (d.name || d.district_name);
                    if (districtName) options += `<option value="${districtName}">${districtName}</option>`;
                });
                $distSelect.html(options).prop('disabled', districts.length === 0).trigger('change');
            },
            error: function () {
                $distSelect.html('<option value="" disabled selected>Failed to load districts</option>').prop('disabled', true);
            }
        });
    });

    // Listen for new district additions to refresh dropdowns
    $(document).on('customAjaxSuccess', function(e, data) {
        if (data.url.includes('districts/store') && data.response.district) {
            const newDistrict = data.response.district;
            
            // If we added a district, refresh the state-district cascading
            const addStateId = $('#manualStateSelect').val();
            if (addStateId == newDistrict.state_id) {
                $(document).one('districtsLoaded', function() {
                    $('#manualDistrictSelect').val(newDistrict.id).trigger('change.select2');
                });
                $('#manualStateSelect').trigger('change');
            }
            
            const editStateId = $('#editVillageState').val();
            if (editStateId == newDistrict.state_id) {
                $(document).one('districtsLoaded', function() {
                    $('#editVillageDistrict').val(newDistrict.id).trigger('change.select2');
                });
                $('#editVillageState').trigger('change');
            }
        }
    });
});
