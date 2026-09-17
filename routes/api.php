<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * Sales reporting feed (read-only).
 *
 * Authenticated with Sanctum personal access tokens rather than the admin
 * session: these are called by report generators and integrations, not by a
 * browser. Issue one with `php artisan crm:api-token {email}`.
 *
 * Throttled because a report run is a handful of paginated calls, not a
 * firehose — and the endpoint returns customer data, so a leaked token
 * should not also be an unbounded export.
 */
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('reports')
    ->controller(App\Http\Controllers\Api\LeadReportController::class)
    ->group(function () {
        Route::get('leads', 'index')->name('api.reports.leads');

        Route::get('lead-fields', 'fields')->name('api.reports.lead-fields');
    });
