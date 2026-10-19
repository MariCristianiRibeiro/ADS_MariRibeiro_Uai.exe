<?php

use App\Http\Controllers\AccessProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('participants', ParticipantController::class)->except(['show'])->middleware('permission:participants.view');
    Route::get('/projects', [ProjectController::class, 'index'])->middleware('permission:projects.view')->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('permission:projects.create')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:projects.create')->name('projects.store');
    Route::post('/meetings/{meeting}/timer/start', [MeetingController::class, 'startTimer'])->middleware('permission:meetings.view')->name('meetings.timer.start');
    Route::post('/meetings/{meeting}/timer/stop', [MeetingController::class, 'stopTimer'])->middleware('permission:meetings.view')->name('meetings.timer.stop');
    Route::post('/meetings/{meeting}/finish', [MeetingController::class, 'finish'])->middleware('permission:meetings.view')->name('meetings.finish');
    Route::resource('meetings', MeetingController::class)->middleware('permission:meetings.view');
    Route::get('/reports/meetings', [ReportController::class, 'meetings'])->middleware('permission:reports.view')->name('reports.meetings');

    Route::middleware('can:manage-users')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });

    Route::middleware('can:manage-access-profiles')->group(function () {
        Route::get('/access-profiles', [AccessProfileController::class, 'index'])->middleware('permission:access_profiles.view')->name('access-profiles.index');
        Route::get('/access-profiles/create', [AccessProfileController::class, 'create'])->middleware('permission:access_profiles.create')->name('access-profiles.create');
        Route::post('/access-profiles', [AccessProfileController::class, 'store'])->middleware('permission:access_profiles.create')->name('access-profiles.store');
        Route::get('/access-profiles/{accessProfile}/edit', [AccessProfileController::class, 'edit'])->middleware('permission:access_profiles.edit')->name('access-profiles.edit');
        Route::put('/access-profiles/{accessProfile}', [AccessProfileController::class, 'update'])->middleware('permission:access_profiles.edit')->name('access-profiles.update');
        Route::patch('/access-profiles/{accessProfile}/deactivate', [AccessProfileController::class, 'deactivate'])->middleware('permission:access_profiles.administer')->name('access-profiles.deactivate');
    });

    Route::middleware('can:manage-structure')->group(function () {
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
        Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::get('/job-positions', [JobPositionController::class, 'index'])->middleware('permission:job_positions.view')->name('job-positions.index');
        Route::get('/job-positions/create', [JobPositionController::class, 'create'])->middleware('permission:job_positions.create')->name('job-positions.create');
        Route::post('/job-positions', [JobPositionController::class, 'store'])->middleware('permission:job_positions.create')->name('job-positions.store');
        Route::get('/job-positions/{jobPosition}/edit', [JobPositionController::class, 'edit'])->middleware('permission:job_positions.edit')->name('job-positions.edit');
        Route::put('/job-positions/{jobPosition}', [JobPositionController::class, 'update'])->middleware('permission:job_positions.edit')->name('job-positions.update');
        Route::patch('/job-positions/{jobPosition}/deactivate', [JobPositionController::class, 'deactivate'])->middleware('permission:job_positions.deactivate')->name('job-positions.deactivate');
    });
});

require __DIR__.'/auth.php';
