document.addEventListener('DOMContentLoaded', function() {
    initializeMerchantDashboard();
});

function initializeMerchantDashboard() {
    setupInventoryManagementEvents();
    setupTransactionEvents();
    setupReportingEvents();
}

function setupInventoryManagementEvents() {
    const inventoryUpdateForm = document.getElementById('inventory-update-form');
    if (inventoryUpdateForm) {
        inventoryUpdateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            updateInventoryItem(formData);
        });
    }
    
    const inventoryAddForm = document.getElementById('inventory-add-form');
    if (inventoryAddForm) {
        inventoryAddForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            addInventoryItem(formData);
        });
    }
    
    const inventoryActionButtons = document.querySelectorAll('.inventory-action-btn');
    inventoryActionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = this.getAttribute('data-action');
            const inventoryId = this.getAttribute('data-inventory-id');
            
            switch (action) {
                case 'edit':
                    editInventoryItem(inventoryId);
                    break;
                case 'delete':
                    confirmDeleteInventoryItem(inventoryId);
                    break;
                default:
                    console.error('Unknown action:', action);
            }
        });
    });
}

function updateInventoryItem(formData) {
    showNotification('Updating inventory item...', 'info');
    
    console.log('Updating inventory item');
    
    setTimeout(() => {
        showNotification('Inventory updated successfully', 'success');
    }, 1000);
}

function addInventoryItem(formData) {
    showNotification('Adding new inventory item...', 'info');
    
    console.log('Adding new inventory item');
    
    setTimeout(() => {
        showNotification('Item added to inventory', 'success');
    }, 1000);
}

function setupTransactionEvents() {
    const transactionForm = document.getElementById('transaction-form');
    if (transactionForm) {
        transactionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            processTransaction(formData);
        });
    }
    
    const transactionDetailButtons = document.querySelectorAll('.transaction-detail-btn');
    transactionDetailButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const transactionId = this.getAttribute('data-transaction-id');
            
            viewTransactionDetails(transactionId);
        });
    });
}

function processTransaction(formData) {
    showNotification('Processing transaction...', 'info');
    
    console.log('Processing transaction');
    
    setTimeout(() => {
        showNotification('Transaction completed successfully', 'success');
    }, 1500);
}

function setupReportingEvents() {
    const reportForm = document.getElementById('report-generation-form');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reportType = document.getElementById('report-type').value;
            const dateRange = document.getElementById('date-range').value;
            
            generateReport(reportType, dateRange);
        });
    }
    
    const chartPeriodSelectors = document.querySelectorAll('.chart-period-selector');
    chartPeriodSelectors.forEach(selector => {
        selector.addEventListener('change', function() {
            const chartId = this.getAttribute('data-chart-id');
            const period = this.value;
            
            updateChartPeriod(chartId, period);
        });
    });
}

function generateReport(reportType, dateRange) {
    showNotification('Generating report...', 'info');
    
    console.log(`Generating ${reportType} report for ${dateRange}`);
    
    setTimeout(() => {
        showNotification('Report generated successfully', 'success');
    }, 1500);
}

function updateChartPeriod(chartId, period) {
    showNotification('Updating chart...', 'info');
    
    console.log(`Updating chart ${chartId} to period: ${period}`);
    
    setTimeout(() => {
        showNotification('Chart updated', 'success');
    }, 1000);
}