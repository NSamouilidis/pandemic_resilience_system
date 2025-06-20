document.addEventListener('DOMContentLoaded', function() {
    initializeGovernmentDashboard();
});

function initializeGovernmentDashboard() {
    setupUserManagementEvents();
    setupMerchantApprovalEvents();
    setupAlertManagementEvents();
    setupReportingEvents();
}

function setupUserManagementEvents() {
    const userSearchForm = document.getElementById('user-search-form');
    if (userSearchForm) {
        userSearchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const searchTerm = document.getElementById('user-search').value;
            const userRole = document.getElementById('user-role-filter').value;
            
            searchUsers(searchTerm, userRole);
        });
    }
    
    const userActionButtons = document.querySelectorAll('.user-action-btn');
    userActionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = this.getAttribute('data-action');
            const userId = this.getAttribute('data-user-id');
            
            switch (action) {
                case 'view':
                    viewUserDetails(userId);
                    break;
                case 'edit':
                    editUserDetails(userId);
                    break;
                case 'suspend':
                    confirmUserSuspension(userId);
                    break;
                default:
                    console.error('Unknown action:', action);
            }
        });
    });
}

function searchUsers(searchTerm, role) {
    showNotification('Searching users...', 'info');
    
    console.log(`Searching for users: ${searchTerm}, role: ${role}`);
    
    setTimeout(() => {
        showNotification('Search completed', 'success');
    }, 1000);
}

function setupMerchantApprovalEvents() {
    const merchantApprovalButtons = document.querySelectorAll('.merchant-approval-btn');
    merchantApprovalButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = this.getAttribute('data-action');
            const merchantId = this.getAttribute('data-merchant-id');
            
            switch (action) {
                case 'approve':
                    approveMerchant(merchantId);
                    break;
                case 'reject':
                    rejectMerchant(merchantId);
                    break;
                case 'suspend':
                    suspendMerchant(merchantId);
                    break;
                default:
                    console.error('Unknown action:', action);
            }
        });
    });
}

function approveMerchant(merchantId) {
    showNotification('Processing merchant approval...', 'info');
    
    console.log(`Approving merchant: ${merchantId}`);
    
    setTimeout(() => {
        showNotification('Merchant approved successfully', 'success');
    }, 1000);
}

function setupAlertManagementEvents() {
    const alertActionButtons = document.querySelectorAll('.alert-action-btn');
    alertActionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = this.getAttribute('data-action');
            const alertId = this.getAttribute('data-alert-id');
            
            switch (action) {
                case 'view':
                    viewAlertDetails(alertId);
                    break;
                case 'resolve':
                    resolveAlert(alertId);
                    break;
                case 'dismiss':
                    dismissAlert(alertId);
                    break;
                default:
                    console.error('Unknown action:', action);
            }
        });
    });
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