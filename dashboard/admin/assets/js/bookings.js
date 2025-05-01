document.addEventListener('DOMContentLoaded', function() {
    // Close alerts
    const closeButtons = document.querySelectorAll('.alert .close-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
    
    // Toggle filter section
    const filterToggleBtn = document.getElementById('filter-toggle-btn');
    const filterSection = document.getElementById('filter-section');
    
    if (filterToggleBtn && filterSection) {
        filterToggleBtn.addEventListener('click', function() {
            filterSection.classList.toggle('show');
        });
    }
    
    // Confirm booking
    const confirmBookingBtns = document.querySelectorAll('.confirm-booking-btn');
    confirmBookingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'confirmed';
            document.getElementById('status-modal-title').textContent = 'Confirm Booking';
            document.getElementById('status-message').innerHTML = 'Are you sure you want to confirm this booking? This will notify the client that their appointment is confirmed.';
            document.getElementById('update-status-btn').textContent = 'Confirm Booking';
            document.getElementById('update-status-modal').classList.add('show');
        });
    });
    
    // Cancel booking
    const cancelBookingBtns = document.querySelectorAll('.cancel-booking-btn');
    cancelBookingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'cancelled';
            document.getElementById('status-modal-title').textContent = 'Cancel Booking';
            document.getElementById('status-message').innerHTML = '<strong class="text-danger">Warning: This will cancel the booking.</strong> Please provide a reason for the cancellation:';
            document.getElementById('update-status-btn').textContent = 'Cancel Booking';
            document.getElementById('update-status-modal').classList.add('show');
        });
    });
    
    // Reschedule booking
    const rescheduleBookingBtns = document.querySelectorAll('.reschedule-booking-btn');
    rescheduleBookingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('reschedule-booking-id').value = bookingId;
            document.getElementById('reschedule-modal').classList.add('show');
        });
    });
    
    // Assign team member
    const assignTeamBtns = document.querySelectorAll('.assign-team-btn');
    assignTeamBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('assign-booking-id').value = bookingId;
            document.getElementById('assign-team-modal').classList.add('show');
        });
    });
    
    // View booking details
    const viewBookingBtns = document.querySelectorAll('.view-booking-btn');
    viewBookingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            const detailsContainer = document.getElementById('booking-details-container');
            
            // Show loading state
            detailsContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading booking details...</div>';
            document.getElementById('view-booking-modal').classList.add('show');
            
            // Fetch booking details
            fetch(`ajax/get_booking.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayBookingDetails(data.booking);
                    } else {
                        detailsContainer.innerHTML = `<div class="error-message">${data.error || 'Error loading booking details'}</div>`;
                    }
                })
                .catch(error => {
                    detailsContainer.innerHTML = '<div class="error-message">Failed to load booking details. Please try again.</div>';
                });
        });
    });
    
    function displayBookingDetails(booking) {
        const detailsContainer = document.getElementById('booking-details-container');
        
        // Format date and time
        const bookingDate = new Date(booking.booking_date);
        const formattedDate = bookingDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        // Build HTML for details
        let html = `
            <div class="booking-detail-header">
                <div class="detail-reference">Ref: ${booking.reference_number}</div>
                <div class="detail-status ${booking.status}">${booking.status}</div>
            </div>
            
            <div class="booking-detail-section">
                <h3>Appointment Details</h3>
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value">${formattedDate}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Time</div>
                    <div class="detail-value">${formatTime(booking.start_time)} - ${formatTime(booking.end_time)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Service</div>
                    <div class="detail-value">${booking.service_name}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Consultation Mode</div>
                    <div class="detail-value">${booking.consultation_mode}</div>
                </div>
            </div>
            
            <div class="booking-detail-section">
                <h3>Client Information</h3>
                <div class="detail-item">
                    <div class="detail-label">Name</div>
                    <div class="detail-value">${booking.client_name}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">${booking.user_email}</div>
                </div>
                ${booking.user_phone ? `
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value">${booking.user_phone}</div>
                </div>` : ''}
            </div>
        `;
        
        // Add team member section if applicable
        if (booking.team_member_name) {
            html += `
                <div class="booking-detail-section">
                    <h3>Assigned Team Member</h3>
                    <div class="detail-item">
                        <div class="detail-label">Name</div>
                        <div class="detail-value">${booking.team_member_name}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Role</div>
                        <div class="detail-value">${booking.team_member_role || 'Not specified'}</div>
                    </div>
                </div>
            `;
        }
        
        // Add notes if available
        if (booking.notes) {
            html += `
                <div class="booking-detail-section">
                    <h3>Client Notes</h3>
                    <div class="detail-notes">${booking.notes}</div>
                </div>
            `;
        }
        
        // Add professional notes if available
        if (booking.professional_notes) {
            html += `
                <div class="booking-detail-section">
                    <h3>Your Notes</h3>
                    <div class="detail-notes">${booking.professional_notes}</div>
                </div>
            `;
        }
        
        // Add admin notes if available
        if (booking.admin_notes) {
            html += `
                <div class="booking-detail-section">
                    <h3>Admin Notes</h3>
                    <div class="detail-notes">${booking.admin_notes}</div>
                </div>
            `;
        }
        
        // Add cancellation info if cancelled
        if (booking.status === 'cancelled') {
            html += `
                <div class="booking-detail-section">
                    <h3>Cancellation Details</h3>
                    <div class="detail-item">
                        <div class="detail-label">Cancelled On</div>
                        <div class="detail-value">${new Date(booking.cancellation_date).toLocaleString()}</div>
                    </div>
                    ${booking.cancellation_reason ? `
                    <div class="detail-item">
                        <div class="detail-label">Reason</div>
                        <div class="detail-value">${booking.cancellation_reason}</div>
                    </div>` : ''}
                </div>
            `;
        }
        
        // Add action buttons at bottom
        html += `
            <div class="booking-detail-actions">
                ${(booking.status === 'pending') ? `
                <button class="btn-confirm confirm-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-check"></i> Confirm
                </button>` : ''}
                
                ${(booking.status === 'pending' || booking.status === 'confirmed') ? `
                <button class="btn-edit reschedule-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-calendar-alt"></i> Reschedule
                </button>
                <button class="btn-cancel cancel-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-times"></i> Cancel
                </button>` : ''}
                
                ${(booking.status === 'confirmed' && new Date(`${booking.booking_date} ${booking.start_time}`) <= new Date()) ? `
                <button class="btn-complete complete-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-check-circle"></i> Mark Completed
                </button>
                <button class="btn-noshow noshow-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-user-slash"></i> No Show
                </button>` : ''}
            </div>
        `;
        
        detailsContainer.innerHTML = html;
        
        // Add event listeners to new buttons
        document.querySelector('.confirm-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'confirmed';
            document.getElementById('status-modal-title').textContent = 'Confirm Booking';
            document.getElementById('status-message').innerHTML = 'Are you sure you want to confirm this booking? This will notify the client that their appointment is confirmed.';
            document.getElementById('update-status-btn').textContent = 'Confirm Booking';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
        
        document.querySelector('.cancel-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'cancelled';
            document.getElementById('status-modal-title').textContent = 'Cancel Booking';
            document.getElementById('status-message').innerHTML = '<strong class="text-danger">Warning: This will cancel the booking.</strong> Please provide a reason for the cancellation:';
            document.getElementById('update-status-btn').textContent = 'Cancel Booking';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
        
        document.querySelector('.reschedule-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('reschedule-booking-id').value = bookingId;
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('reschedule-modal').classList.add('show');
        });
        
        document.querySelector('.complete-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'completed';
            document.getElementById('status-modal-title').textContent = 'Complete Booking';
            document.getElementById('status-message').innerHTML = 'Mark this booking as completed? Add any notes about the appointment:';
            document.getElementById('update-status-btn').textContent = 'Mark Completed';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
        
        document.querySelector('.noshow-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'no_show';
            document.getElementById('status-modal-title').textContent = 'Mark as No Show';
            document.getElementById('status-message').innerHTML = 'Mark this client as a no-show? Add any notes:';
            document.getElementById('update-status-btn').textContent = 'Mark as No Show';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
    }
    
    // Helper function to format time
    function formatTime(timeStr) {
        if (!timeStr) return '';
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours);
        const amPm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${amPm}`;
    }
    
    // Close modals
    const modalCloseButtons = document.querySelectorAll('.modal-close, .modal-cancel');
    
    modalCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.classList.remove('show');
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        });
    });
}); 