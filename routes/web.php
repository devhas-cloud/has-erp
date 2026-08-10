<?php

use App\Http\Controllers\AccountManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\ContactManagementController;
use App\Http\Controllers\DashboardTaskPlannerController;
use App\Http\Controllers\LeadsManagementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityManagementController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\TaskPlannerController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WaterConfigurationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'access.control'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('user-management.index');
    });

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
    Route::resource('contact-management', ContactManagementController::class);

    Route::get('accounts-management/data', [AccountManagementController::class, 'data'])->name('accounts-management.data');
    Route::resource('accounts-management', AccountManagementController::class);

    Route::get('task-planner/data', [TaskPlannerController::class, 'data'])->name('task-planner.data');
    Route::get('users/search', [LeadsManagementController::class, 'searchUsers'])->name('users.search');
    Route::get('task-planner/export', [TaskPlannerController::class, 'export'])->name('task-planner.export');
    Route::get('task-planner/import-template', [TaskPlannerController::class, 'downloadTemplate'])->name('task-planner.template');
    Route::post('task-planner/import', [TaskPlannerController::class, 'import'])->name('task-planner.import');
    Route::get('task-planner/fetch-assignees', [TaskPlannerController::class, 'fetchAssignees'])->name('task-planner.fetch-assignees');
    Route::get('task-planner/fetch-division-handlers', [TaskPlannerController::class, 'fetchDivisionHandlers'])->name('task-planner.fetch-division-handlers');
    Route::get('task-planner/fetch-whatsapp-groups', [TaskPlannerController::class, 'fetchWhatsAppGroups'])->name('task-planner.fetch-whatsapp-groups');
    Route::post('task-planner/{id}/approve', [TaskPlannerController::class, 'approve'])->name('task-planner.approve');
    Route::post('task-planner/{id}/reject', [TaskPlannerController::class, 'reject'])->name('task-planner.reject');
    Route::post('task-planner/{id}/transition', [TaskPlannerController::class, 'transition'])->name('task-planner.transition');
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
    Route::resource('opportunity-management', OpportunityManagementController::class)->except(['edit'])->parameters(['opportunity-management' => 'opportunity']);

    Route::get('product-management/data', [ProductManagementController::class, 'data'])->name('product-management.data');
    Route::get('product-management/export', [ProductManagementController::class, 'export'])->name('product-management.export');
    Route::get('product-management/template', [ProductManagementController::class, 'downloadTemplate'])->name('product-management.template');
    Route::post('product-management/import', [ProductManagementController::class, 'import'])->name('product-management.import');
    Route::resource('product-management', ProductManagementController::class);

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
    Route::resource('water-configuration', WaterConfigurationController::class);

    Route::get('dashboard-task-planner', [DashboardTaskPlannerController::class, 'index'])
        ->name('dashboard-task-planner.index');

    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/all', [NotificationController::class, 'all'])->name('notifications.all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

});
