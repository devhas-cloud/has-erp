<?php

use App\Http\Controllers\AccountManagementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\ContactManagementController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardTaskPlannerController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\GoodsRequestController;
use App\Http\Controllers\ImsConfigurationController;
use App\Http\Controllers\LeadsManagementController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityManagementController;
use App\Http\Controllers\OvertimeRequestController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\ProfitEstimateController;
use App\Http\Controllers\PoSupplierApprovalController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskPlannerController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WaterConfigurationController;
use App\Models\Module;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'access.control'])->group(function () {
    // Universal: arahkan ke modul pertama yang boleh dibaca akun ini (bukan
    // hardcode ke satu modul), karena tiap akun bisa punya modul berbeda.
    Route::get('/', function () {
        $module = Module::firstAccessibleFor(auth()->user());

        return redirect($module ? route($module->route_name.'.index') : route('no-access'));
    });

    Route::get('/no-access', [AuthController::class, 'noAccess'])->name('no-access');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('user-management/data', [UserManagementController::class, 'data'])->name('user-management.data');
    Route::resource('user-management', UserManagementController::class);

    Route::get('/configuration', [ConfigurationController::class, 'index'])->name('configuration.index');
    Route::get('/configuration/{table}', [ConfigurationController::class, 'list'])->name('configuration.list');
    Route::post('/configuration/{table}', [ConfigurationController::class, 'store'])->name('configuration.store');
    Route::put('/configuration/{table}/{id}', [ConfigurationController::class, 'update'])->name('configuration.update');
    Route::delete('/configuration/{table}/{id}', [ConfigurationController::class, 'destroy'])->name('configuration.destroy');

    Route::get('leads-management/data', [LeadsManagementController::class, 'data'])->name('leads-management.data');
    Route::get('leads-management/template', [LeadsManagementController::class, 'downloadTemplate'])->name('leads-management.template');
    Route::post('leads-management/import', [LeadsManagementController::class, 'import'])->name('leads-management.import');
    Route::get('leads-management/search-companies', [LeadsManagementController::class, 'searchCompanies'])->name('leads-management.search-companies');
    Route::get('leads-management/{lead}/fetch', [LeadsManagementController::class, 'fetch'])->name('leads-management.fetch');
    Route::get('leads-management/{lead}/activities', [LeadsManagementController::class, 'fetchActivities'])->name('leads-management.activities.fetch');
    Route::post('leads-management/{lead}/activities', [LeadsManagementController::class, 'storeActivity'])->name('leads-management.activities.store');
    Route::post('leads-management/activities/{activity}/upload', [LeadsManagementController::class, 'uploadActivityAttachment'])->name('leads-management.activities.upload');
    Route::delete('leads-management/activities/{activity}', [LeadsManagementController::class, 'destroyActivity'])->name('leads-management.activities.destroy');
    Route::get('leads-management/{lead}/tasks', [LeadsManagementController::class, 'fetchLeadTasks'])->name('leads-management.tasks.fetch');
    Route::post('leads-management/{lead}/tasks', [LeadsManagementController::class, 'storeLeadTask'])->name('leads-management.tasks.store');
    Route::post('leads-management/{lead}/unqualified', [LeadsManagementController::class, 'markUnqualified'])->name('leads-management.unqualified');
    Route::post('leads-management/{lead}/qualified', [LeadsManagementController::class, 'markQualified'])->name('leads-management.qualified');
    Route::post('leads-management/{lead}/converted', [LeadsManagementController::class, 'markConverted'])->name('leads-management.converted');
    Route::resource('leads-management', LeadsManagementController::class)->except(['edit']);
    // Route::get('leads-management/{lead}/edit', ...)->name('leads-management.edit'); // dikomentari

    Route::get('contact-management/data', [ContactManagementController::class, 'data'])->name('contact-management.data');
    Route::post('contact-management/{contact_management}/leads', [ContactManagementController::class, 'storeLead'])->name('contact-management.leads.store');
    Route::resource('contact-management', ContactManagementController::class);

    Route::get('accounts-management/data', [AccountManagementController::class, 'data'])->name('accounts-management.data');
    Route::resource('accounts-management', AccountManagementController::class);

    Route::get('task-planner/data', [TaskPlannerController::class, 'data'])->name('task-planner.data');
    Route::get('users/search', [LeadsManagementController::class, 'searchUsers'])->name('users.search');
    Route::get('task-planner/export', [TaskPlannerController::class, 'export'])->name('task-planner.export');
    Route::get('task-planner/import-template', [TaskPlannerController::class, 'downloadTemplate'])->name('task-planner.template');
    Route::post('task-planner/import', [TaskPlannerController::class, 'import'])->name('task-planner.import');
    Route::get('task-planner/fetch-assignees', [TaskPlannerController::class, 'fetchAssignees'])->name('task-planner.fetch-assignees');
    Route::get('task-planner/fetch-handling-group-users', [TaskPlannerController::class, 'fetchHandlingGroupUsers'])->name('task-planner.fetch-handling-group-users');
    Route::get('task-planner/fetch-whatsapp-groups', [TaskPlannerController::class, 'fetchWhatsAppGroups'])->name('task-planner.fetch-whatsapp-groups');
    Route::get('task-planner/fetch-account-contacts', [TaskPlannerController::class, 'fetchAccountContacts'])->name('task-planner.fetch-account-contacts');
    Route::post('task-planner/{id}/approve', [TaskPlannerController::class, 'approve'])->name('task-planner.approve');
    Route::post('task-planner/{id}/reject', [TaskPlannerController::class, 'reject'])->name('task-planner.reject');
    Route::post('task-planner/{id}/transition', [TaskPlannerController::class, 'transition'])->name('task-planner.transition');
    Route::post('task-planner/{id}/open', [TaskPlannerController::class, 'open'])->name('task-planner.open');
    Route::get('task-planner/{id}/activities', [TaskPlannerController::class, 'activities'])->name('task-planner.activities');
    Route::post('task-planner/{id}/activities', [TaskPlannerController::class, 'storeActivity'])->name('task-planner.store-activity');
    Route::post('task-planner/{id}/visit', [TaskPlannerController::class, 'storeVisit'])->name('task-planner.visit');
    Route::get('task-planner/{id}/visits', [TaskPlannerController::class, 'visits'])->name('task-planner.visits');
    Route::get('task-planner/{id}/proposals', [TaskPlannerController::class, 'fetchProposals'])->name('task-planner.proposals');
    Route::post('task-planner/{id}/proposals', [TaskPlannerController::class, 'storeProposal'])->name('task-planner.store-proposal');
    Route::get('task-planner/{id}/proposals/{proposal}/view', [TaskPlannerController::class, 'viewProposal'])->name('task-planner.proposal-view');
    Route::resource('task-planner', TaskPlannerController::class);

    Route::get('opportunity-management/data', [OpportunityManagementController::class, 'data'])->name('opportunity-management.data');
    Route::get('opportunity-management/search-users', [OpportunityManagementController::class, 'searchUsers'])->name('opportunity-management.search-users');
    Route::get('opportunity-management/search-leads', [OpportunityManagementController::class, 'searchLeads'])->name('opportunity-management.search-leads');
    Route::get('opportunity-management/search-companies', [OpportunityManagementController::class, 'searchCompanies'])->name('opportunity-management.search-companies');
    Route::get('opportunity-management/search-contacts', [OpportunityManagementController::class, 'searchContacts'])->name('opportunity-management.search-contacts');
    Route::get('opportunity-management/{opportunity}/fetch', [OpportunityManagementController::class, 'fetch'])->name('opportunity-management.fetch');
    Route::get('opportunity-management/{opportunity}/activities', [OpportunityManagementController::class, 'fetchActivities'])->name('opportunity-management.activities.fetch');
    Route::post('opportunity-management/{opportunity}/activities', [OpportunityManagementController::class, 'storeActivity'])->name('opportunity-management.activities.store');
    Route::post('opportunity-management/activities/{activity}/upload', [OpportunityManagementController::class, 'uploadActivityAttachment'])->name('opportunity-management.activities.upload');
    Route::delete('opportunity-management/activities/{activity}', [OpportunityManagementController::class, 'destroyActivity'])->name('opportunity-management.activities.destroy');
    Route::get('opportunity-management/{opportunity}/tasks', [OpportunityManagementController::class, 'fetchTasks'])->name('opportunity-management.tasks.fetch');
    Route::post('opportunity-management/{opportunity}/tasks', [OpportunityManagementController::class, 'storeTask'])->name('opportunity-management.tasks.store');
    Route::post('opportunity-management/{opportunity}/stage/in-review', [OpportunityManagementController::class, 'moveToInReview'])->name('opportunity-management.in-review');
    Route::post('opportunity-management/{opportunity}/negotiation', [OpportunityManagementController::class, 'requestNegotiation'])->name('opportunity-management.negotiation');
    Route::post('opportunity-management/{opportunity}/negotiation/approve', [OpportunityManagementController::class, 'approveNegotiation'])->name('opportunity-management.approve-negotiation');
    Route::post('opportunity-management/{opportunity}/close-loss', [OpportunityManagementController::class, 'requestCloseLoss'])->name('opportunity-management.close-loss');
    Route::post('opportunity-management/{opportunity}/next-step', [OpportunityManagementController::class, 'updateNextStep'])->name('opportunity-management.next-step');
    Route::post('opportunity-management/{opportunity}/approve', [OpportunityManagementController::class, 'approveCloseLoss'])->name('opportunity-management.approve');
    Route::resource('opportunity-management', OpportunityManagementController::class)->except(['edit'])->parameters(['opportunity-management' => 'opportunity']);

    Route::get('product-management/data', [ProductManagementController::class, 'data'])->name('product-management.data');
    Route::get('product-management/export', [ProductManagementController::class, 'export'])->name('product-management.export');
    Route::get('product-management/template', [ProductManagementController::class, 'downloadTemplate'])->name('product-management.template');
    Route::post('product-management/import', [ProductManagementController::class, 'import'])->name('product-management.import');
    Route::resource('product-management', ProductManagementController::class);

    Route::get('currency/data', [CurrencyController::class, 'data'])->name('currency.data');
    Route::resource('currency', CurrencyController::class)->except(['create', 'show']);

    Route::get('water-configuration/data', [WaterConfigurationController::class, 'data'])->name('water-configuration.data');
    Route::post('water-configuration/{id}/submit', [WaterConfigurationController::class, 'submit'])->name('water-configuration.submit');
    Route::post('water-configuration/{id}/approve', [WaterConfigurationController::class, 'approve'])->name('water-configuration.approve');
    Route::post('water-configuration/{id}/reject', [WaterConfigurationController::class, 'reject'])->name('water-configuration.reject');
    Route::post('water-configuration/{id}/unlock', [WaterConfigurationController::class, 'unlock'])->name('water-configuration.unlock');
    Route::post('water-configuration/{id}/revise', [WaterConfigurationController::class, 'revise'])->name('water-configuration.revise');
    Route::get('water-configuration/{id}/versions', [WaterConfigurationController::class, 'versions'])->name('water-configuration.versions');
    Route::get('water-configuration/{id}/pdf', [WaterConfigurationController::class, 'pdf'])->name('water-configuration.pdf');
    Route::get('water-configuration/search-products', [WaterConfigurationController::class, 'searchProducts'])->name('water-configuration.search-products');
    Route::get('water-configuration/fetch-task', [WaterConfigurationController::class, 'fetchTask'])->name('water-configuration.fetch-task');
    Route::get('water-configuration/{id}/template', [WaterConfigurationController::class, 'fetchTemplate'])->name('water-configuration.fetch-template');
    Route::resource('water-configuration', WaterConfigurationController::class);

    Route::get('ims-configuration/data', [ImsConfigurationController::class, 'data'])->name('ims-configuration.data');
    Route::post('ims-configuration/{id}/submit', [ImsConfigurationController::class, 'submit'])->name('ims-configuration.submit');
    Route::post('ims-configuration/{id}/approve', [ImsConfigurationController::class, 'approve'])->name('ims-configuration.approve');
    Route::post('ims-configuration/{id}/reject', [ImsConfigurationController::class, 'reject'])->name('ims-configuration.reject');
    Route::post('ims-configuration/{id}/unlock', [ImsConfigurationController::class, 'unlock'])->name('ims-configuration.unlock');
    Route::post('ims-configuration/{id}/revise', [ImsConfigurationController::class, 'revise'])->name('ims-configuration.revise');
    Route::get('ims-configuration/{id}/versions', [ImsConfigurationController::class, 'versions'])->name('ims-configuration.versions');
    Route::get('ims-configuration/{id}/pdf', [ImsConfigurationController::class, 'pdf'])->name('ims-configuration.pdf');
    Route::get('ims-configuration/search-products', [ImsConfigurationController::class, 'searchProducts'])->name('ims-configuration.search-products');
    Route::get('ims-configuration/fetch-task', [ImsConfigurationController::class, 'fetchTask'])->name('ims-configuration.fetch-task');
    Route::get('ims-configuration/{id}/template', [ImsConfigurationController::class, 'fetchTemplate'])->name('ims-configuration.fetch-template');
    Route::resource('ims-configuration', ImsConfigurationController::class);

    Route::get('quotation/data', [QuotationController::class, 'data'])->name('quotation.data');
    Route::get('quotation/fetch-task', [QuotationController::class, 'fetchTask'])->name('quotation.fetch-task');
    Route::get('quotation/search-products', [QuotationController::class, 'searchProducts'])->name('quotation.search-products');
    Route::get('quotation/fetch-template', [QuotationController::class, 'fetchTemplate'])->name('quotation.fetch-template');
    Route::get('quotation/fetch-cost-template', [QuotationController::class, 'fetchCostTemplate'])->name('quotation.fetch-cost-template');
    Route::post('quotation/{id}/submit', [QuotationController::class, 'submit'])->name('quotation.submit');
    Route::post('quotation/{id}/approve', [QuotationController::class, 'approve'])->name('quotation.approve');
    Route::post('quotation/{id}/reject', [QuotationController::class, 'reject'])->name('quotation.reject');
    Route::post('quotation/{id}/unlock', [QuotationController::class, 'unlock'])->name('quotation.unlock');
    Route::post('quotation/{id}/revise', [QuotationController::class, 'revise'])->name('quotation.revise');
    Route::get('quotation/{id}/versions', [QuotationController::class, 'versions'])->name('quotation.versions');
    Route::get('quotation/{id}/pdf', [QuotationController::class, 'pdf'])->name('quotation.pdf');
    Route::get('quotation/{id}/pdf-cost', [QuotationController::class, 'pdfCost'])->name('quotation.pdf-cost');
    Route::put('quotation/{id}/update-notes', [QuotationController::class, 'updateNotes'])->name('quotation.update-notes');
    Route::post('quotation/{id}/upload-po', [QuotationController::class, 'uploadPo'])->name('quotation.upload-po');
    Route::get('quotation/{id}/view-po', [QuotationController::class, 'viewPo'])->name('quotation.view-po');
    Route::resource('quotation', QuotationController::class);

    Route::get('po-supplier-approval/data', [PoSupplierApprovalController::class, 'data'])->name('po-supplier-approval.data');
    Route::post('po-supplier-approval/{id}/approve', [PoSupplierApprovalController::class, 'approve'])->name('po-supplier-approval.approve');
    Route::get('po-supplier-approval', [PoSupplierApprovalController::class, 'index'])->name('po-supplier-approval.index');

    Route::get('goods-request/data', [GoodsRequestController::class, 'data'])->name('goods-request.data');
    Route::get('goods-request/fetch-config-items', [GoodsRequestController::class, 'fetchConfigItems'])->name('goods-request.fetch-config-items');
    Route::get('goods-request/search-products', [GoodsRequestController::class, 'searchProducts'])->name('goods-request.search-products');
    Route::post('goods-request/{id}/submit', [GoodsRequestController::class, 'submit'])->name('goods-request.submit');
    Route::post('goods-request/{id}/approve', [GoodsRequestController::class, 'approve'])->name('goods-request.approve');
    Route::post('goods-request/{id}/reject', [GoodsRequestController::class, 'reject'])->name('goods-request.reject');
    Route::resource('goods-request', GoodsRequestController::class);

    Route::get('purchase-order/data', [PurchaseOrderController::class, 'data'])->name('purchase-order.data');
    Route::get('purchase-order/fetch-available-items', [PurchaseOrderController::class, 'fetchAvailableItems'])->name('purchase-order.fetch-available-items');
    Route::get('purchase-order/search-products', [PurchaseOrderController::class, 'searchProducts'])->name('purchase-order.search-products');
    Route::post('purchase-order/{id}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-order.submit');
    Route::post('purchase-order/{id}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-order.approve');
    Route::post('purchase-order/{id}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-order.reject');
    Route::get('purchase-order/{id}/pdf', [PurchaseOrderController::class, 'pdf'])->name('purchase-order.pdf');
    Route::resource('purchase-order', PurchaseOrderController::class);

    Route::get('supplier/data', [SupplierController::class, 'data'])->name('supplier.data');
    Route::get('supplier/search', [SupplierController::class, 'search'])->name('supplier.search');
    Route::resource('supplier', SupplierController::class)->except(['create', 'show']);

    Route::get('profit-estimate/data', [ProfitEstimateController::class, 'data'])->name('profit-estimate.data');
    Route::get('profit-estimate/{id}/sync', [ProfitEstimateController::class, 'sync'])->name('profit-estimate.sync');
    Route::get('profit-estimate/{id}/pdf', [ProfitEstimateController::class, 'pdf'])->name('profit-estimate.pdf');
    Route::resource('profit-estimate', ProfitEstimateController::class);

    // ============================================================
    // Employee Relations (ER)
    // Implementasi penuh mengikuti employee-management.md
    // ============================================================

    Route::get('employee-management/data', [EmployeeManagementController::class, 'data'])->name('employee-management.data');
    Route::get('employee-management/search-managers', [EmployeeManagementController::class, 'searchManagers'])->name('employee-management.search-managers');
    Route::get('employee-management/{employee}/families', [EmployeeManagementController::class, 'families'])->name('employee-management.families');
    Route::post('employee-management/{employee}/families', [EmployeeManagementController::class, 'storeFamily'])->name('employee-management.families.store');
    Route::put('employee-management/{employee}/families/{familyId}', [EmployeeManagementController::class, 'updateFamily'])->name('employee-management.families.update');
    Route::delete('employee-management/{employee}/families/{familyId}', [EmployeeManagementController::class, 'destroyFamily'])->name('employee-management.families.destroy');
    Route::put('employee-management/{employee}/terminate', [EmployeeManagementController::class, 'terminate'])->name('employee-management.terminate');
    Route::put('employee-management/{employee}/reactivate', [EmployeeManagementController::class, 'reactivate'])->name('employee-management.reactivate');
    Route::resource('employee-management', EmployeeManagementController::class)->except(['create']);

    Route::get('attendance/data', [AttendanceController::class, 'data'])->name('attendance.data');
    Route::get('attendance/office', [AttendanceController::class, 'office'])->name('attendance.office');
    Route::post('attendance/office', [AttendanceController::class, 'storeOffice'])->name('attendance.office.store');
    Route::put('attendance/office/{officeId}', [AttendanceController::class, 'updateOffice'])->name('attendance.office.update');
    Route::delete('attendance/office/{officeId}', [AttendanceController::class, 'destroyOffice'])->name('attendance.office.destroy');
    Route::post('attendance/shift', [AttendanceController::class, 'storeShift'])->name('attendance.shift.store');
    Route::put('attendance/shift/{shiftId}', [AttendanceController::class, 'updateShift'])->name('attendance.shift.update');
    Route::delete('attendance/shift/{shiftId}', [AttendanceController::class, 'destroyShift'])->name('attendance.shift.destroy');
    Route::post('attendance/manual', [AttendanceController::class, 'manual'])->name('attendance.manual');
    Route::post('attendance/import-device', [AttendanceController::class, 'importDevice'])->name('attendance.import-device');
    Route::get('attendance/corrections/data', [AttendanceController::class, 'correctionsData'])->name('attendance.corrections.data');
    Route::post('attendance/corrections', [AttendanceController::class, 'storeCorrection'])->name('attendance.corrections.store');
    Route::post('attendance/corrections/{correctionId}/approve', [AttendanceController::class, 'approveCorrection'])->name('attendance.corrections.approve');
    Route::post('attendance/corrections/{correctionId}/reject', [AttendanceController::class, 'rejectCorrection'])->name('attendance.corrections.reject');
    Route::post('attendance/{attendance}/approve', [AttendanceController::class, 'approve'])->name('attendance.approve');
    Route::post('attendance/{attendance}/reject', [AttendanceController::class, 'reject'])->name('attendance.reject');
    Route::resource('attendance', AttendanceController::class)->except(['create', 'edit', 'store', 'update', 'destroy']);

    Route::get('leave-request/data', [LeaveRequestController::class, 'data'])->name('leave-request.data');
    Route::get('leave-request/balances', [LeaveRequestController::class, 'balances'])->name('leave-request.balances');
    Route::post('leave-request/{leave}/approve', [LeaveRequestController::class, 'approve'])->name('leave-request.approve');
    Route::post('leave-request/{leave}/reject', [LeaveRequestController::class, 'reject'])->name('leave-request.reject');
    Route::post('leave-request/{leave}/revise', [LeaveRequestController::class, 'revise'])->name('leave-request.revise');
    Route::post('leave-request/{leave}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-request.cancel');
    Route::resource('leave-request', LeaveRequestController::class)->except(['create', 'edit']);

    Route::get('overtime-request/data', [OvertimeRequestController::class, 'data'])->name('overtime-request.data');
    Route::get('overtime-request/fetch-attendance', [OvertimeRequestController::class, 'fetchAttendance'])->name('overtime-request.fetch-attendance');
    Route::post('overtime-request/{overtime}/approve', [OvertimeRequestController::class, 'approve'])->name('overtime-request.approve');
    Route::post('overtime-request/{overtime}/reject', [OvertimeRequestController::class, 'reject'])->name('overtime-request.reject');
    Route::post('overtime-request/{overtime}/cancel', [OvertimeRequestController::class, 'cancel'])->name('overtime-request.cancel');
    Route::resource('overtime-request', OvertimeRequestController::class)->except(['create', 'edit']);

    Route::get('loan/data', [LoanController::class, 'data'])->name('loan.data');
    Route::post('loan/{loan}/approve', [LoanController::class, 'approve'])->name('loan.approve');
    Route::post('loan/{loan}/reject', [LoanController::class, 'reject'])->name('loan.reject');
    Route::post('loan/{loan}/settle', [LoanController::class, 'settle'])->name('loan.settle');
    Route::post('loan/installments/{installmentId}/pay', [LoanController::class, 'pay'])->name('loan.installments.pay');
    Route::resource('loan', LoanController::class)->except(['create', 'edit']);

    Route::get('payroll/data', [PayrollController::class, 'data'])->name('payroll.data');
    Route::post('payroll/{payroll}/generate-draft', [PayrollController::class, 'generateDraft'])->name('payroll.generate-draft');
    Route::post('payroll/{payroll}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');
    Route::post('payroll/{payroll}/components', [PayrollController::class, 'updateComponent'])->name('payroll.components.store');
    Route::put('payroll/{payroll}/components/{componentId}', [PayrollController::class, 'updateComponent'])->name('payroll.components.update');
    Route::delete('payroll/{payroll}/components/{componentId}', [PayrollController::class, 'destroyComponent'])->name('payroll.components.destroy');
    Route::get('payroll/{payroll}/payslip/{payslipId}/pdf', [PayrollController::class, 'payslipPdf'])->name('payroll.payslip.pdf');
    Route::get('payroll/{payroll}/excel', [PayrollController::class, 'excel'])->name('payroll.excel');
    Route::resource('payroll', PayrollController::class)->except(['create', 'edit', 'update', 'destroy']);

    // Employee salary components (Tab Komponen Gaji)
    Route::get('employee-management/{employee}/salary-components', [EmployeeManagementController::class, 'salaryComponents'])->name('employee-management.salary-components');
    Route::post('employee-management/{employee}/salary-components', [EmployeeManagementController::class, 'storeSalaryComponent'])->name('employee-management.salary-components.store');
    Route::put('employee-management/{employee}/salary-components/{employeeSalaryComponentId}', [EmployeeManagementController::class, 'updateSalaryComponent'])->name('employee-management.salary-components.update');
    Route::delete('employee-management/{employee}/salary-components/{employeeSalaryComponentId}', [EmployeeManagementController::class, 'destroySalaryComponent'])->name('employee-management.salary-components.destroy');

    Route::get('dashboard-task-planner', [DashboardTaskPlannerController::class, 'index'])
        ->name('dashboard-task-planner.index');

    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/all', [NotificationController::class, 'all'])->name('notifications.all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

});
