// Initialize variables
let selectedCountryId = null;
let selectedCountryName = null;
let selectedVisaTypeId = null;
let selectedVisaTypeName = null;

// Modal functionality
const modals = {
    country: document.getElementById('add-country-modal'),
    visaType: document.getElementById('add-visa-type-modal'),
    document: document.getElementById('add-document-modal')
};

// Get all close buttons and add event listeners
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    for (const modalKey in modals) {
        if (event.target === modals[modalKey]) {
            modals[modalKey].style.display = 'none';
        }
    }
});

// Open add country modal
document.getElementById('add-country-card').addEventListener('click', function() {
    modals.country.style.display = 'block';
});

// Handle country form submission
document.getElementById('add-country-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax/add_country.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Country added successfully!');
            location.reload(); // Reload to update the country list
        } else {
            alert('Error adding country: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
});

// Country card click event
document.addEventListener('click', function(e) {
    const countryCard = e.target.closest('.country-card');
    if (countryCard) {
        // Deselect all country cards
        document.querySelectorAll('.country-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Select this card
        countryCard.classList.add('selected');
        
        // Update selected country info
        selectedCountryId = countryCard.dataset.id;
        selectedCountryName = countryCard.dataset.name;
        
        // Update UI
        document.getElementById('selected-country-name').textContent = selectedCountryName;
        document.getElementById('visa-types-section').style.display = 'block';
        document.getElementById('documents-section').style.display = 'none';
        
        // Load visa types for this country
        loadVisaTypes(selectedCountryId);
    }
});

// Load visa types for a country
function loadVisaTypes(countryId) {
    const container = document.getElementById('visa-types-container');
    container.innerHTML = '<div class="loading">Loading visa types...</div>';
    
    fetch('ajax/get_visa_types.php?country_id=' + countryId)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            // Add the "Add Visa Type" card first
            const addCard = document.createElement('div');
            addCard.className = 'card add-card';
            addCard.id = 'add-visa-type-card';
            addCard.innerHTML = `
                <div class="card-content">
                    <i class="fas fa-plus"></i>
                    <p>Add Visa Type</p>
                </div>
            `;
            container.appendChild(addCard);
            
            // We'll handle this event via event delegation instead of direct binding
            
            if (data.success && data.visa_types.length > 0) {
                data.visa_types.forEach(visa => {
                    const visaCard = document.createElement('div');
                    visaCard.className = 'card visa-card';
                    visaCard.dataset.id = visa.id;
                    visaCard.dataset.name = visa.name;
                    
                    let processingTime = visa.processing_time ? `<p>Processing: ${visa.processing_time}</p>` : '';
                    let validityPeriod = visa.validity_period ? `<p>Validity: ${visa.validity_period}</p>` : '';
                    
                    visaCard.innerHTML = `
                        <div class="card-content">
                            <h3>${visa.name}</h3>
                            <p class="visa-code">Code: ${visa.code || 'N/A'}</p>
                            ${processingTime}
                            ${validityPeriod}
                        </div>
                    `;
                    container.appendChild(visaCard);
                });
            } else {
                container.innerHTML += '<div class="no-items">No visa types found for this country.</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="error">Error loading visa types: ' + error + '</div>';
        });
}

// Add this event listener for the "Add Visa Type" card using event delegation
document.addEventListener('click', function(e) {
    const addVisaTypeCard = e.target.closest('#add-visa-type-card');
    if (addVisaTypeCard) {
        document.getElementById('visa-country-id').value = selectedCountryId;
        modals.visaType.style.display = 'block';
    }
});

// Visa Type card click event (delegated)
document.addEventListener('click', function(e) {
    const visaCard = e.target.closest('.visa-card');
    if (visaCard) {
        // Deselect all visa cards
        document.querySelectorAll('.visa-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Select this card
        visaCard.classList.add('selected');
        
        // Update selected visa type info
        selectedVisaTypeId = visaCard.dataset.id;
        selectedVisaTypeName = visaCard.dataset.name;
        
        // Update UI
        document.getElementById('selected-visa-type-name').textContent = selectedVisaTypeName;
        document.getElementById('documents-section').style.display = 'block';
        
        // Load documents for this visa type
        loadDocuments(selectedVisaTypeId);
    }
});

// Load documents for a visa type
function loadDocuments(visaTypeId) {
    const container = document.getElementById('documents-container');
    container.innerHTML = '<div class="loading">Loading required documents...</div>';
    
    fetch('ajax/get_required_documents.php?visa_type_id=' + visaTypeId)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            // Add "Add Document" button
            const addButton = document.createElement('button');
            addButton.className = 'btn add-document-btn';
            addButton.innerText = 'Add Document';
            addButton.addEventListener('click', function() {
                document.getElementById('doc-visa-type-id').value = selectedVisaTypeId;
                modals.document.style.display = 'block';
            });
            container.appendChild(addButton);
            
            // Create documents table
            const table = document.createElement('table');
            table.className = 'documents-table';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Document Name</th>
                        <th>Mandatory</th>
                        <th>Requirements</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="documents-tbody"></tbody>
            `;
            container.appendChild(table);
            
            const tbody = document.getElementById('documents-tbody');
            
            if (data.success && data.documents.length > 0) {
                data.documents.forEach(doc => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${doc.name}</td>
                        <td>${doc.is_mandatory ? 'Yes' : 'No'}</td>
                        <td>${doc.additional_requirements || '-'}</td>
                        <td>
                            <button class="btn-small edit-doc" data-id="${doc.id}">Edit</button>
                            <button class="btn-small btn-danger delete-doc" data-id="${doc.id}">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="4" class="no-items">No required documents found for this visa type.</td></tr>`;
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="error">Error loading documents: ' + error + '</div>';
        });
}

// Handle visa type form submission
document.getElementById('add-visa-type-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax/add_visa_type.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Visa type added successfully!');
            modals.visaType.style.display = 'none';
            loadVisaTypes(selectedCountryId);
        } else {
            alert('Error adding visa type: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
});

// Handle document form submission
document.getElementById('add-document-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax/add_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Document added successfully!');
            modals.document.style.display = 'none';
            loadDocuments(selectedVisaTypeId);
        } else {
            alert('Error adding document: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
});

// Show/hide new category input based on selection
document.getElementById('document-category').addEventListener('change', function() {
    const newCategoryGroup = document.getElementById('new-category-group');
    newCategoryGroup.style.display = this.value === 'new' ? 'block' : 'none';
    
    if (this.value === 'new') {
        document.getElementById('new-category-name').setAttribute('required', 'required');
    } else {
        document.getElementById('new-category-name').removeAttribute('required');
    }
});

// Edit and delete document functionality (delegated)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('edit-doc')) {
        const docId = e.target.dataset.id;
        alert('Edit document functionality will be implemented soon.');
    } else if (e.target.classList.contains('delete-doc')) {
        const docId = e.target.dataset.id;
        if (confirm('Are you sure you want to delete this document requirement?')) {
            alert('Delete document functionality will be implemented soon.');
        }
    }
});