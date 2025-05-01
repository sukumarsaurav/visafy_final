// Modal handling script
document.addEventListener('DOMContentLoaded', function() {
    // Get modal and button references
    const serviceModal = document.getElementById('service-modal');
    const addServiceBtn = document.getElementById('add-service-btn');
    const closeButtons = document.querySelectorAll('.btn-close, [data-bs-dismiss="modal"]');
    const saveServiceBtn = document.getElementById('save-service-btn');
    
    // Initialize DataTable if the function exists
    if ($.fn.DataTable) {
        const servicesTable = $('#services-table').DataTable({
            ajax: {
                url: 'ajax/get_services.php',
                dataSrc: ''
            },
            columns: [
                { data: 'country_name' },
                { data: 'visa_type_name' },
                { data: 'service_type_name' },
                { data: 'consultation_mode_name' },
                { 
                    data: 'price',
                    render: function(data) {
                        return `$${parseFloat(data).toFixed(2)}`;
                    }
                },
                {
                    data: 'is_active',
                    render: function(data) {
                        return data == 1 ? 
                            '<span class="badge bg-success">Active</span>' : 
                            '<span class="badge bg-danger">Inactive</span>';
                    }
                },
                {
                    data: null,
                    render: function(data) {
                        return `
                            <button class="btn btn-sm btn-primary edit-btn" data-id="${data.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${data.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                    }
                }
            ],
            order: [[0, 'asc']],
            responsive: true
        });
    } else {
        console.error('DataTable function is not available. Make sure jQuery and DataTables are properly loaded.');
    }
    
    // Show modal when clicking "Add New Service" button
    addServiceBtn.addEventListener('click', function() {
        // Reset the form
        document.getElementById('service-form').reset();
        document.getElementById('config-id').value = '';
        document.getElementById('visa-type').disabled = true;
        
        // Display the modal
        serviceModal.style.display = 'block';
        serviceModal.classList.add('show');
    });
    
    // Close modal when clicking close buttons
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            serviceModal.style.display = 'none';
            serviceModal.classList.remove('show');
        });
    });
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === serviceModal) {
            serviceModal.style.display = 'none';
            serviceModal.classList.remove('show');
        }
    });
    
    // Handle country change
    $('#country').change(function() {
        const countryId = $(this).val();
        const visaTypeSelect = $('#visa-type');
        
        visaTypeSelect.prop('disabled', true).empty()
            .append('<option value="">Select Visa Type</option>');
        
        if (countryId) {
            $.get('ajax/get_visa_types.php', { country_id: countryId })
                .done(function(data) {
                    data.forEach(function(type) {
                        visaTypeSelect.append(
                            `<option value="${type.id}">${type.name}</option>`
                        );
                    });
                    visaTypeSelect.prop('disabled', false);
                });
        }
    });
    
    // Handle edit button click
    $(document).on('click', '.edit-btn', function() {
        const id = $(this).data('id');
        
        $.get('ajax/get_service.php', { id: id })
            .done(function(data) {
                $('#config-id').val(data.id);
                $('#country').val(data.country_id).trigger('change');
                
                // Wait for visa types to load
                setTimeout(() => {
                    $('#visa-type').val(data.visa_type_id);
                }, 500);
                
                $('#service-type').val(data.service_type_id);
                $('#consultation-mode').val(data.consultation_mode_id);
                $('#price').val(data.price);
                $('#is-active').prop('checked', data.is_active == 1);
                
                // Show the modal
                serviceModal.style.display = 'block';
                serviceModal.classList.add('show');
            });
    });
    
    // Handle save button click
    saveServiceBtn.addEventListener('click', function() {
        const form = document.getElementById('service-form');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const data = {
            id: document.getElementById('config-id').value,
            visa_type_id: document.getElementById('visa-type').value,
            service_type_id: document.getElementById('service-type').value,
            consultation_mode_id: document.getElementById('consultation-mode').value,
            price: document.getElementById('price').value,
            is_active: document.getElementById('is-active').checked ? 1 : 0
        };
        
        $.post('ajax/save_service.php', data)
            .done(function(response) {
                if (response.success) {
                    serviceModal.style.display = 'none';
                    serviceModal.classList.remove('show');
                    
                    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#services-table')) {
                        $('#services-table').DataTable().ajax.reload();
                    }
                    
                    toastr.success('Service configuration saved successfully');
                } else {
                    toastr.error(response.message || 'Failed to save service configuration');
                }
            })
            .fail(function() {
                toastr.error('An error occurred while saving');
            });
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this service configuration?')) {
            $.post('ajax/delete_service.php', { id: id })
                .done(function(response) {
                    if (response.success) {
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#services-table')) {
                            $('#services-table').DataTable().ajax.reload();
                        }
                        toastr.success('Service configuration deleted successfully');
                    } else {
                        toastr.error(response.message || 'Failed to delete service configuration');
                    }
                })
                .fail(function() {
                    toastr.error('An error occurred while deleting');
                });
        }
    });
});