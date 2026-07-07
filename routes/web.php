<?php

use App\Http\Controllers\AccountManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\ContactManagementController;
use App\Http\Controllers\DashboardTaskPlannerController;
use App\Http\Controllers\LeadsManagementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskPlannerController;
use App\Http\Controllers\UserManagementController;
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
    Route::resource('leads-management', LeadsManagementController::class)->except(['edit']);
    // Route::get('leads-management/{lead}/edit', ...)->name('leads-management.edit'); // dikomentari

    Route::get('contact-management/data', [ContactManagementController::class, 'data'])->name('contact-management.data');
    Route::resource('contact-management', ContactManagementController::class);

    Route::get('accounts-management/data', [AccountManagementController::class, 'data'])->name('accounts-management.data');
    Route::resource('accounts-management', AccountManagementController::class);

    Route::get('task-planner/data', [TaskPlannerController::class, 'data'])->name('task-planner.data');
    Route::get('task-planner/export', [TaskPlannerController::class, 'export'])->name('task-planner.export');
    Route::get('task-planner/import-template', [TaskPlannerController::class, 'downloadTemplate'])->name('task-planner.template');
    Route::post('task-planner/import', [TaskPlannerController::class, 'import'])->name('task-planner.import');
    Route::get('task-planner/fetch-assignees', [TaskPlannerController::class, 'fetchAssignees'])->name('task-planner.fetch-assignees');
    Route::get('task-planner/fetch-whatsapp-groups', [TaskPlannerController::class, 'fetchWhatsAppGroups'])->name('task-planner.fetch-whatsapp-groups');
    Route::post('task-planner/{id}/approve', [TaskPlannerController::class, 'approve'])->name('task-planner.approve');
    Route::post('task-planner/{id}/transition', [TaskPlannerController::class, 'transition'])->name('task-planner.transition');
    Route::get('task-planner/{id}/activities', [TaskPlannerController::class, 'activities'])->name('task-planner.activities');
    Route::post('task-planner/{id}/activities', [TaskPlannerController::class, 'storeActivity'])->name('task-planner.store-activity');
    Route::resource('task-planner', TaskPlannerController::class);

    Route::get('dashboard-task-planner', [DashboardTaskPlannerController::class, 'index'])
        ->name('dashboard-task-planner.index');

    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

});
