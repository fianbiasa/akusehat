<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::patch('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    });
});
