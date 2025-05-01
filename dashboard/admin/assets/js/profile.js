document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current button
            this.classList.add('active');
            
            // Show the corresponding tab content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });

    // Profile edit functionality
    const editProfileBtn = document.getElementById('edit-profile-btn');
    const saveProfileBtn = document.getElementById('save-profile-btn');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const firstNameInput = document.getElementById('first-name');
    const lastNameInput = document.getElementById('last-name');
    const emailInput = document.getElementById('email');
    
    // Store original values for cancel functionality
    let originalFirstName, originalLastName, originalEmail;
    
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function() {
            // Store original values
            originalFirstName = firstNameInput.value;
            originalLastName = lastNameInput.value;
            originalEmail = emailInput.value;
            
            // Make fields editable
            firstNameInput.removeAttribute('readonly');
            lastNameInput.removeAttribute('readonly');
            emailInput.removeAttribute('readonly');
            
            // Toggle buttons
            editProfileBtn.classList.add('hidden');
            saveProfileBtn.classList.remove('hidden');
            cancelEditBtn.classList.remove('hidden');
        });
    }
    
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            // Restore original values
            firstNameInput.value = originalFirstName;
            lastNameInput.value = originalLastName;
            emailInput.value = originalEmail;
            
            // Make fields readonly again
            firstNameInput.setAttribute('readonly', true);
            lastNameInput.setAttribute('readonly', true);
            emailInput.setAttribute('readonly', true);
            
            // Toggle buttons
            editProfileBtn.classList.remove('hidden');
            saveProfileBtn.classList.add('hidden');
            cancelEditBtn.classList.add('hidden');
        });
    }

    // Profile picture change functionality
    const profileImageContainer = document.querySelector('.profile-image-container');
    const imageOverlay = document.getElementById('image-overlay');
    const photoUpload = document.getElementById('photo-upload');
    
    if (imageOverlay && photoUpload) {
        imageOverlay.addEventListener('click', function() {
            photoUpload.click();
        });
        
        photoUpload.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Check if profile image exists, otherwise create it
                    let profileImg = document.getElementById('profile-image');
                    if (!profileImg) {
                        profileImg = document.createElement('img');
                        profileImg.id = 'profile-image';
                        profileImg.className = 'profile-image';
                        
                        // Remove placeholder if it exists
                        const placeholder = profileImageContainer.querySelector('.profile-image-placeholder');
                        if (placeholder) {
                            profileImageContainer.removeChild(placeholder);
                        }
                        
                        profileImageContainer.insertBefore(profileImg, imageOverlay);
                    }
                    
                    profileImg.src = e.target.result;
                    
                    // Auto-submit the form when a new image is selected
                    document.getElementById('profile-form').submit();
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // Password strength checker
    const newPasswordInput = document.getElementById('new-password');
    const strengthIndicator = document.getElementById('strength-indicator');
    const strengthText = document.getElementById('strength-text');
    
    if (newPasswordInput && strengthIndicator && strengthText) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let status = '';
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]+/)) strength += 1;
            
            switch (strength) {
                case 0:
                    strengthIndicator.style.width = '0%';
                    strengthIndicator.style.backgroundColor = '';
                    strengthText.textContent = 'Password strength';
                    break;
                case 1:
                    strengthIndicator.style.width = '20%';
                    strengthIndicator.style.backgroundColor = '#f00';
                    strengthText.textContent = 'Very Weak';
                    break;
                case 2:
                    strengthIndicator.style.width = '40%';
                    strengthIndicator.style.backgroundColor = '#f90';
                    strengthText.textContent = 'Weak';
                    break;
                case 3:
                    strengthIndicator.style.width = '60%';
                    strengthIndicator.style.backgroundColor = '#fc0';
                    strengthText.textContent = 'Medium';
                    break;
                case 4:
                    strengthIndicator.style.width = '80%';
                    strengthIndicator.style.backgroundColor = '#3c0';
                    strengthText.textContent = 'Strong';
                    break;
                case 5:
                    strengthIndicator.style.width = '100%';
                    strengthIndicator.style.backgroundColor = '#0c3';
                    strengthText.textContent = 'Very Strong';
                    break;
            }
        });
    }

    // Email verification resend
    const resendVerification = document.getElementById('resend-verification');
    if (resendVerification) {
        resendVerification.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Show sending indicator
            this.textContent = 'Sending...';
            this.style.pointerEvents = 'none';
            
            fetch('resend_verification.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.textContent = 'Verification email sent!';
                    } else {
                        this.textContent = 'Failed to send. Try again.';
                        this.style.pointerEvents = 'auto';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.textContent = 'Failed to send. Try again.';
                    this.style.pointerEvents = 'auto';
                });
        });
    }
});
