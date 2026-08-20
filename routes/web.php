<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleRouteController;
use App\Http\Controllers\StopController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\UserGroupController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripPassengerController;
use App\Http\Controllers\TripStateController;
use App\Http\Controllers\TripFeedbackController;
use App\Http\Controllers\TripFeedbackStateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FactoryController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Foundation\Application;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Debug route — restricted to Super Admin only
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/debug/auth', [DebugController::class, 'authCheck'])->name('debug.auth');
    });

    Route::resource('products', ProductController::class);
    Route::get('products-export', [ProductController::class, 'export'])->name('products.export');

    Route::resource('factories', FactoryController::class);

    Route::resource('vehicles', VehicleController::class);
    Route::get('vehicles-expiring', [VehicleController::class, 'getExpiringVehicles'])->name('vehicles.expiring');

    Route::resource('vendors', VendorController::class);
    Route::get('vendors-select', [VendorController::class, 'getVendorsForSelect'])->name('vendors.select');

    Route::resource('users', UserController::class);
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/api/drivers/available', [DriverController::class, 'getAvailableDrivers'])->name('drivers.available');
    });
    Route::patch('/drivers/{user}/status', [DriverController::class, 'updateDriverStatus'])->name('drivers.update-status');
    Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
    Route::post('/users/import', [UserController::class, 'import'])->name('users.import');

    // Driver management routes (filtered view of users)
    Route::resource('drivers', DriverController::class);

    // User Groups management routes
    Route::resource('user-groups', UserGroupController::class);
    Route::post('/user-groups/{userGroup}/add-members', [UserGroupController::class, 'addMembers'])->name('user-groups.add-members');
    Route::delete('/user-groups/{userGroup}/members/{user}', [UserGroupController::class, 'removeMember'])->name('user-groups.remove-member');
    Route::get('/user-groups/{userGroup}/available-users', [UserGroupController::class, 'availableUsers'])->name('user-groups.available-users');

    // Logistics management routes
    Route::resource('logistics', LogisticsController::class);
    Route::patch('/logistics/{logistic}/toggle-lock', [LogisticsController::class, 'toggleLock'])->name('logistics.toggle-lock');

    // Department management routes
    Route::resource('departments', DepartmentController::class);
    Route::patch('/departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])->name('departments.toggle-status');
    Route::get('/departments/export', [DepartmentController::class, 'export'])->name('departments.export');
    Route::post('/departments/import', [DepartmentController::class, 'import'])->name('departments.import');

    // Route management routes
    Route::resource('routes', VehicleRouteController::class);

    // Stop management API routes
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/api/stops', [StopController::class, 'index'])->name('api.stops.index');
        Route::post('/api/stops', [StopController::class, 'store'])->name('api.stops.store');
    });

    // Trip management routes
    Route::post('/trips/recurring', [TripController::class, 'storeRecurring'])->name('trips.store-recurring');
    Route::get('/trips/passenger-events', [TripController::class, 'passengerEvents'])->name('trips.passenger-events');
    Route::resource('trips', TripController::class);

    // Reports routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Trip state routes
    Route::post('/trips/{trip}/approve', [TripStateController::class, 'approve'])->name('trips.approve');
    Route::post('/trips/{trip}/reject', [TripStateController::class, 'reject'])->name('trips.reject');
    Route::post('/trips/{trip}/start', [TripStateController::class, 'start'])->name('trips.start');
    Route::post('/trips/{trip}/complete', [TripStateController::class, 'complete'])->name('trips.complete');
    Route::post('/trips/{trip}/cancel', [TripStateController::class, 'cancel'])->name('trips.cancel');
    Route::post('/trips/{trip}/reassign-vehicle', [TripController::class, 'reassignVehicle'])->name('trips.reassign-vehicle');

    // Trip passenger routes
    Route::post('/trips/{trip}/passengers/{tripPassenger}/check-in', [TripPassengerController::class, 'checkIn'])->name('trips.passengers.check-in');
    Route::post('/trips/{trip}/passengers/{tripPassenger}/check-out', [TripPassengerController::class, 'checkOut'])->name('trips.passengers.check-out');
    Route::post('/trips/{trip}/passengers/{tripPassenger}/no-show', [TripPassengerController::class, 'markNoShow'])->name('trips.passengers.no-show');
    Route::post('/trips/{trip}/passengers/{tripPassenger}/events/{tripPassengerEvent}/correct', [TripPassengerController::class, 'correctEvent'])->name('trips.passengers.events.correct');

    // Trip feedback & complaints routes
    Route::resource('complaints', TripFeedbackController::class)->except(['edit', 'update']);
    Route::post('/complaints/{complaint}/assign', [TripFeedbackStateController::class, 'assign'])->name('complaints.assign');
    Route::post('/complaints/{complaint}/resolve', [TripFeedbackStateController::class, 'resolve'])->name('complaints.resolve');
    Route::post('/complaints/{complaint}/close', [TripFeedbackStateController::class, 'close'])->name('complaints.close');
    Route::post('/complaints/{complaint}/reopen', [TripFeedbackStateController::class, 'reopen'])->name('complaints.reopen');

    // Role and Permission management routes
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
