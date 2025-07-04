// Global JavaScript functions for Veer Sahayata

// Document ready function
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// File upload functions
function initFileUpload() {
    const uploadAreas = document.querySelectorAll('.upload-area');
    
    uploadAreas.forEach(function(area) {
        const fileInput = area.querySelector('input[type="file"]');
        
        area.addEventListener('click', function() {
            fileInput.click();
        });
        
        area.addEventListener('dragover', function(e) {
            e.preventDefault();
            area.classList.add('drag-over');
        });
        
        area.addEventListener('dragleave', function() {
            area.classList.remove('drag-over');
        });
        
        area.addEventListener('drop', function(e) {
            e.preventDefault();
            area.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(fileInput);
            }
        });
        
        fileInput.addEventListener('change', function() {
            handleFileSelect(this);
        });
    });
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = input.closest('.upload-area').querySelector('.file-preview');
            if (preview) {
                if (file.type.startsWith('image/')) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="img-fluid rounded">`;
                } else {
                    preview.innerHTML = `<i class="fas fa-file fa-3x text-primary"></i><br><span>${file.name}</span>`;
                }
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }
}

// Form validation
function validateForm(form) {
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
    });
    
    return isValid;
}

// Phone number validation
function validatePhone(phone) {
    const phoneRegex = /^[6-9]\d{9}$/;
    return phoneRegex.test(phone);
}

// Aadhar number validation
function validateAadhar(aadhar) {
    const aadharRegex = /^\d{12}$/;
    return aadharRegex.test(aadhar);
}

// PAN validation
function validatePAN(pan) {
    const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
    return panRegex.test(pan);
}

// Service number validation
function validateServiceNumber(serviceNumber) {
    // Basic validation - can be customized based on service requirements
    return serviceNumber.length >= 6 && serviceNumber.length <= 15;
}

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 8) strength++;
    else feedback.push('At least 8 characters');
    
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Include lowercase letters');
    
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Include uppercase letters');
    
    if (/[0-9]/.test(password)) strength++;
    else feedback.push('Include numbers');
    
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else feedback.push('Include special characters');
    
    return {
        strength: strength,
        feedback: feedback,
        level: strength < 3 ? 'weak' : strength < 5 ? 'medium' : 'strong'
    };
}

// Date formatting functions
function formatDate(dateString, format = 'dd/mm/yyyy') {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    switch(format) {
        case 'dd/mm/yyyy':
            return `${day}/${month}/${year}`;
        case 'mm/dd/yyyy':
            return `${month}/${day}/${year}`;
        case 'yyyy-mm-dd':
            return `${year}-${month}-${day}`;
        default:
            return `${day}/${month}/${year}`;
    }
}

function calculateAge(birthDate) {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    
    return age;
}

// Currency formatting
function formatCurrency(amount, currency = 'INR') {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
    }).format(amount);
}

// Loading spinner functions
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<div class="text-center"><div class="spinner"></div><p class="mt-2">Loading...</p></div>';
    }
}

function hideLoading(elementId, content = '') {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content;
    }
}

// Modal functions
function showModal(modalId) {
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

function hideModal(modalId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
        modal.hide();
    }
}

// AJAX helper functions
function makeAjaxRequest(url, method = 'GET', data = null, successCallback = null, errorCallback = null) {
    const xhr = new XMLHttpRequest();
    
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    if (data instanceof FormData) {
        xhr.setRequestHeader('Content-Type', 'multipart/form-data');
    }
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (successCallback) successCallback(response);
            } catch (e) {
                if (successCallback) successCallback(xhr.responseText);
            }
        } else {
            if (errorCallback) errorCallback(xhr.status, xhr.statusText);
        }
    };
    
    xhr.onerror = function() {
        if (errorCallback) errorCallback(0, 'Network Error');
    };
    
    xhr.send(data ? JSON.stringify(data) : null);
}

// Notification functions
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 300px;';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.remove();
    }, duration);
}

// Camera functions for selfie verification
function initCamera(videoElement, canvasElement) {
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(function(stream) {
            videoElement.srcObject = stream;
        })
        .catch(function(error) {
            console.error('Error accessing camera:', error);
            showNotification('Unable to access camera. Please check permissions.', 'error');
        });
}

function capturePhoto(videoElement, canvasElement) {
    const context = canvasElement.getContext('2d');
    canvasElement.width = videoElement.videoWidth;
    canvasElement.height = videoElement.videoHeight;
    context.drawImage(videoElement, 0, 0);
    
    return canvasElement.toDataURL('image/jpeg');
}

// Search and filter functions
function filterTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm.toLowerCase())) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function sortTable(tableId, columnIndex, direction = 'asc') {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort(function(a, b) {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        if (direction === 'asc') {
            return aValue.localeCompare(bValue);
        } else {
            return bValue.localeCompare(aValue);
        }
    });
    
    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}

// Print functions
function printElement(elementId) {
    const element = document.getElementById(elementId);
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { font-family: Arial, sans-serif; }
                    .no-print { display: none !important; }
                </style>
            </head>
            <body>
                ${element.innerHTML}
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
    printWindow.close();
}

// Local storage functions
function saveToLocalStorage(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify(data));
        return true;
    } catch (error) {
        console.error('Error saving to localStorage:', error);
        return false;
    }
}

function loadFromLocalStorage(key) {
    try {
        const data = localStorage.getItem(key);
        return data ? JSON.parse(data) : null;
    } catch (error) {
        console.error('Error loading from localStorage:', error);
        return null;
    }
}

// Initialize all components
document.addEventListener('DOMContentLoaded', function() {
    initFileUpload();
    
    // Add smooth scrolling to anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Add form validation on submit
    document.querySelectorAll('form[data-validate="true"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showNotification('Please fill in all required fields correctly.', 'warning');
            }
        });
    });
});
