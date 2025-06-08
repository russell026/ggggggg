/**
 * Portal Ekstrakurikuler UNSRAT - Main JavaScript
 * Main functionality for the portal
 */

// Global variables
let currentModal = null;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializePortal();
});

/**
 * Initialize the portal
 */
function initializePortal() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize form validations
    initializeFormValidations();
    
    // Initialize image upload previews
    initializeImageUpload();
    
    // Initialize auto-save for forms
    initializeAutoSave();
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
    
    // Initialize responsive navigation
    initializeResponsiveNav();
    
    // Initialize loading states
    initializeLoadingStates();
    
    console.log('Portal Ekstrakurikuler UNSRAT initialized successfully');
}

/**
 * Tooltip functionality
 */
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(event) {
    const element = event.target;
    const tooltipText = element.getAttribute('data-tooltip');
    
    if (!tooltipText) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.cssText = `
        position: absolute;
        background: #1f2937;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-size: 0.875rem;
        z-index: 1000;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
    `;
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    // Show tooltip
    setTimeout(() => {
        tooltip.style.opacity = '1';
    }, 100);
    
    element._tooltip = tooltip;
}

function hideTooltip(event) {
    const element = event.target;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

/**
 * Form validation functionality
 */
function initializeFormValidations() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearFieldError);
        });
    });
}

function validateForm(event) {
    const form = event.target;
    const isValid = validateAllFields(form);
    
    if (!isValid) {
        event.preventDefault();
        showFormErrors(form);
    }
}

function validateAllFields(form) {
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField({ target: input })) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(event) {
    const field = event.target;
    const value = field.value.trim();
    const fieldType = field.type;
    const isRequired = field.hasAttribute('required');
    
    // Clear previous errors
    clearFieldError(event);
    
    // Check if required field is empty
    if (isRequired && !value) {
        showFieldError(field, 'Field ini wajib diisi');
        return false;
    }
    
    // Type-specific validations
    switch (fieldType) {
        case 'email':
            if (value && !isValidEmail(value)) {
                showFieldError(field, 'Format email tidak valid');
                return false;
            }
            break;
            
        case 'tel':
            if (value && !isValidPhone(value)) {
                showFieldError(field, 'Format nomor telepon tidak valid');
                return false;
            }
            break;
            
        case 'url':
            if (value && !isValidURL(value)) {
                showFieldError(field, 'Format URL tidak valid');
                return false;
            }
            break;
            
        case 'password':
            if (value && value.length < 6) {
                showFieldError(field, 'Password minimal 6 karakter');
                return false;
            }
            break;
            
        case 'number':
            const min = field.getAttribute('min');
            const max = field.getAttribute('max');
            const numValue = parseInt(value);
            
            if (min && numValue < parseInt(min)) {
                showFieldError(field, `Nilai minimal ${min}`);
                return false;
            }
            
            if (max && numValue > parseInt(max)) {
                showFieldError(field, `Nilai maksimal ${max}`);
                return false;
            }
            break;
    }
    
    return true;
}

function showFieldError(field, message) {
    field.classList.add('error');
    
    let errorElement = field.parentNode.querySelector('.field-error');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.style.cssText = 'color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem;';
        field.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
}

function clearFieldError(event) {
    const field = event.target;
    field.classList.remove('error');
    
    const errorElement = field.parentNode.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

function showFormErrors(form) {
    const firstError = form.querySelector('.error');
    if (firstError) {
        firstError.focus();
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

/**
 * Validation helper functions
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    const phoneRegex = /^[0-9+\-\s()]{8,}$/;
    return phoneRegex.test(phone);
}

function isValidURL(url) {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

/**
 * Image upload functionality
 */
function initializeImageUpload() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', handleImageUpload);
        
        // Add drag and drop functionality
        const container = input.closest('.form-group');
        if (container) {
            makeDragDropZone(container, input);
        }
    });
}

function handleImageUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        showAlert('File harus berupa gambar', 'error');
        event.target.value = '';
        return;
    }
    
    // Validate file size (5MB)
    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        showAlert('Ukuran file maksimal 5MB', 'error');
        event.target.value = '';
        return;
    }
    
    // Show preview
    showImagePreview(event.target, file);
}

function showImagePreview(input, file) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        let preview = input.parentNode.querySelector('.image-preview');
        
        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'image-preview';
            preview.style.cssText = 'margin-top: 1rem;';
            input.parentNode.appendChild(preview);
        }
        
        preview.innerHTML = `
            <div style="position: relative; display: inline-block;">
                <img src="${e.target.result}" 
                     style="max-width: 200px; max-height: 200px; border-radius: 8px; object-fit: cover;">
                <button type="button" onclick="removeImagePreview(this)" 
                        style="position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px;">
                    ×
                </button>
            </div>
            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                ${file.name} (${formatFileSize(file.size)})
            </p>
        `;
    };
    
    reader.readAsDataURL(file);
}

function removeImagePreview(button) {
    const preview = button.closest('.image-preview');
    const input = preview.parentNode.querySelector('input[type="file"]');
    
    preview.remove();
    input.value = '';
}

function makeDragDropZone(container, input) {
    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        container.classList.add('dragover');
    });
    
    container.addEventListener('dragleave', function(e) {
        e.preventDefault();
        container.classList.remove('dragover');
    });
    
    container.addEventListener('drop', function(e) {
        e.preventDefault();
        container.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            input.files = files;
            handleImageUpload({ target: input });
        }
    });
}

/**
 * Auto-save functionality
 */
function initializeAutoSave() {
    const forms = document.querySelectorAll('form[data-autosave]');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('input', debounce(() => {
                autoSaveForm(form);
            }, 2000));
        });
        
        // Load saved data
        loadAutoSavedData(form);
    });
}

function autoSaveForm(form) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    const formId = form.id || form.getAttribute('data-autosave');
    localStorage.setItem(`autosave_${formId}`, JSON.stringify(data));
    
    showTemporaryMessage('Data tersimpan otomatis', 'success');
}

function loadAutoSavedData(form) {
    const formId = form.id || form.getAttribute('data-autosave');
    const savedData = localStorage.getItem(`autosave_${formId}`);
    
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            
            Object.keys(data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input && input.type !== 'file' && input.type !== 'password') {
                    input.value = data[key];
                }
            });
            
            showTemporaryMessage('Data sebelumnya dimuat', 'info');
        } catch (e) {
            console.error('Error loading autosaved data:', e);
        }
    }
}

function clearAutoSavedData(formId) {
    localStorage.removeItem(`autosave_${formId}`);
}

/**
 * Keyboard shortcuts
 */
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S to save forms
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const form = document.querySelector('form');
            if (form) {
                form.dispatchEvent(new Event('submit'));
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            closeAllModals();
        }
        
        // Ctrl/Cmd + / for help
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            showKeyboardShortcuts();
        }
    });
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
        modal.classList.remove('show');
    });
}

function showKeyboardShortcuts() {
    const shortcuts = [
        'Ctrl+S: Simpan form',
        'Esc: Tutup modal',
        'Ctrl+/: Tampilkan shortcuts'
    ];
    
    showAlert('Keyboard Shortcuts:\n' + shortcuts.join('\n'), 'info');
}

/**
 * Responsive navigation
 */
function initializeResponsiveNav() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.header-nav');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('show');
        });
        
        // Close nav when clicking outside
        document.addEventListener('click', function(e) {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('show');
            }
        });
    }
}

/**
 * Loading states
 */
function initializeLoadingStates() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitButton) {
                showLoadingState(submitButton);
            }
        });
    });
}

function showLoadingState(button) {
    const originalText = button.textContent;
    button.textContent = 'Memproses...';
    button.disabled = true;
    button.classList.add('loading');
    
    // Reset after 5 seconds as fallback
    setTimeout(() => {
        hideLoadingState(button, originalText);
    }, 5000);
}

function hideLoadingState(button, originalText) {
    button.textContent = originalText;
    button.disabled = false;
    button.classList.remove('loading');
}

/**
 * Utility functions
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showAlert(message, type = 'info') {
    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    alert.textContent = message;
    
    // Add close button
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '×';
    closeBtn.style.cssText = `
        position: absolute;
        top: 0.5rem;
        right: 0.75rem;
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0.7;
    `;
    closeBtn.onclick = () => hideAlert(alert);
    alert.appendChild(closeBtn);
    
    document.body.appendChild(alert);
    
    // Show alert
    setTimeout(() => {
        alert.style.opacity = '1';
        alert.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        hideAlert(alert);
    }, 5000);
}

function hideAlert(alert) {
    alert.style.opacity = '0';
    alert.style.transform = 'translateX(100%)';
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 300);
}

function showTemporaryMessage(message, type = 'info') {
    const existingMessage = document.querySelector('.temp-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    const messageEl = document.createElement('div');
    messageEl.className = `temp-message alert-${type}`;
    messageEl.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 20px;
        background: ${type === 'success' ? '#10b981' : '#3b82f6'};
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
        z-index: 9999;
        opacity: 0;
        transform: translateY(100%);
        transition: all 0.3s ease;
    `;
    messageEl.textContent = message;
    
    document.body.appendChild(messageEl);
    
    setTimeout(() => {
        messageEl.style.opacity = '1';
        messageEl.style.transform = 'translateY(0)';
    }, 100);
    
    setTimeout(() => {
        messageEl.style.opacity = '0';
        messageEl.style.transform = 'translateY(100%)';
        setTimeout(() => messageEl.remove(), 300);
    }, 3000);
}

/**
 * Modal management
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        currentModal = modal;
        
        // Focus first input
        const firstInput = modal.querySelector('input, textarea, select');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        currentModal = null;
    }
}

/**
 * Export functions for global use
 */
window.PortalUNSRAT = {
    showAlert,
    showTemporaryMessage,
    openModal,
    closeModal,
    formatFileSize,
    debounce
};