document.addEventListener('DOMContentLoaded', function() {
    initializePublicDashboard();
});

function initializePublicDashboard() {
    setupResourceFinderEvents();
    setupVaccinationEvents();
    setupAlertEvents();
}

function setupResourceFinderEvents() {
    const resourceSearchForm = document.getElementById('resource-search-form');
    if (resourceSearchForm) {
        resourceSearchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const category = document.getElementById('category').value;
            const location = document.getElementById('location').value;
            
            searchResources(category, location);
        });
    }
    
    const viewItemsButtons = document.querySelectorAll('.btn-view-items');
    viewItemsButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const merchantId = this.getAttribute('data-merchant-id');
            
            window.location.href = `merchant_items.php?merchant_id=${merchantId}`;
        });
    });
}

function searchResources(category, location) {
    console.log(`Searching for ${category} in ${location}`);
    
    showNotification('Searching for resources...', 'info');
    
    setTimeout(() => {
        showNotification('Resources found!', 'success');
    }, 1000);
}

function setupVaccinationEvents() {
    const vaccinationForm = document.getElementById('vaccination-upload-form');
    if (vaccinationForm) {
        vaccinationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            showNotification('Uploading vaccination record...', 'info');
            
            setTimeout(() => {
                showNotification('Vaccination record uploaded!', 'success');
            }, 1500);
        });
    }
}

function setupAlertEvents() {
    const alertDismissButtons = document.querySelectorAll('.alert-dismiss');
    alertDismissButtons.forEach(button => {
        button.addEventListener('click', function() {
            const alertItem = this.closest('.alert-item');
            
            alertItem.classList.add('fade-out');
            
            setTimeout(() => {
                alertItem.remove();
            }, 300);
        });
    });
}