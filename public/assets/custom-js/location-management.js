/**
 * Location Management
 */

'use strict';

// Datatable (jquery)
// Datatable (jquery)
$(function () {
    var dt_location_table = $('.datatables-locations'),
        baseUrl = document.documentElement.getAttribute('data-base-url') + '/';

    // Users List Datatable
    if (dt_location_table.length) {
        var dt_location = dt_location_table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + 'location-management/data',
                type: 'GET'
            },
            columns: [
                // columns according to JSON
                { data: 'fake_id' },
                { data: 'name' },
                { data: 'city' },
                { data: 'state' },
                { data: 'pincode' },
                { data: '' }
            ],
            columnDefs: [
                {
                    // Actions
                    targets: -1,
                    title: 'Actions',
                    searchable: false,
                    orderable: false,
                    render: function (data, type, full, meta) {
                        return (
                            '<div class="d-flex align-items-center gap-2">' +
                            '<button class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect delete-record" data-id="' + full['id'] + '" data-name="' + full['name'] + '">' +
                            '<i class="icon-base ri ri-delete-bin-7-line icon-20px"></i>' +
                            '</button>' +
                            '</div>'
                        );
                    }
                },
                {
                    targets: 4,
                    render: function (data, type, full, meta) {
                        if (data === null || data === 'N/A') {
                            return '<span class="badge bg-label-secondary">N/A</span>';
                        }
                        return data;
                    }
                }
            ],
            order: [[1, 'asc']], // Default order by Name
            dom:
                '<"row mx-1"' +
                '<"col-sm-12 col-md-3" l>' +
                '<"col-sm-12 col-md-9" f>' +
                '>t' +
                '<"row mx-1"' +
                '<"col-sm-12 col-md-6" i>' +
                '<"col-sm-12 col-md-6" p>' +
                '>',
            language: {
                sLengthMenu: 'Show _MENU_',
                search: 'Search',
                searchPlaceholder: 'Search Location'
            },
        });
    }

    // Delete Record with Confirmation
    var currentDeleteId = null;
    var currentLocationName = null;

    $('.datatables-locations tbody').on('click', '.delete-record', function () {
        currentDeleteId = $(this).data('id');
        currentLocationName = $(this).data('name');

        // Show confirmation modal
        $('#deleteLocationName').text(currentLocationName);
        $('#deleteLocationModal').modal('show');
    });

    // Handle delete confirmation
    $('#confirmDeleteBtn').off('click').on('click', function () {
        if (!currentDeleteId) return;

        $.ajax({
            url: baseUrl + 'location-management/' + currentDeleteId,
            type: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                $('#deleteLocationModal').modal('hide');

                if (response.success) {
                    // Show toast/alert
                    alert('Location deleted successfully'); // Replace with nice toast if available
                    dt_location.ajax.reload(null, false);
                } else {
                    alert(response.message || 'Failed to delete location');
                }

                currentDeleteId = null;
            },
            error: function (xhr) {
                $('#deleteLocationModal').modal('hide');
                const message = xhr.responseJSON?.message || 'Failed to delete location';
                alert(message);
                currentDeleteId = null;
            }
        });
    });

    // Handle Fetch Form Submission
    const fetchForm = document.getElementById('fetchLocationForm');
    if (fetchForm) {
        fetchForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = document.getElementById('btnFetch');
            const originalText = btn.innerHTML;

            // Disable button and show spinner
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Fetching...';

            const formData = new FormData(fetchForm);

            $.ajax({
                url: baseUrl + 'location-management/fetch',
                type: 'POST',
                data: {
                    state: formData.get('state'),
                    district: formData.get('district'),
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    $('#fetchLocationModal').modal('hide');
                    fetchForm.reset();
                    alert(response.message); // Success message
                    dt_location.ajax.reload(null, false);
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.message || 'Fetch failed';
                    alert(message);
                },
                complete: function () {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
        });
    }
});
