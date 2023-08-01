<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\PaperController;
use App\Http\Controllers\API\AuthorController;
use App\Http\Controllers\API\CoAuthorController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/authors', [AuthorController::class, 'index']);
Route::post('/authors', [AuthorController::class, 'store']);
Route::get('/authors/{author}', [AuthorController::class, 'show']);
Route::put('/authors/{id}', [AuthorController::class, 'update']);
Route::delete('/authors/{id}', [AuthorController::class, 'destroy']);

Route::get('/coauthor', [CoAuthorController::class, 'index']);
Route::get('/coauthor/create', [CoAuthorController::class, 'createCoAuthor']);
Route::get('/coauthor/import', [CoAuthorController::class, 'importCoAuthor']);
Route::get('/coauthor/getCandidates', [CoAuthorController::class, 'getCandidates']);
Route::get('/coauthor/importCandidate/{split_year}', [CoAuthorController::class, 'importCandidate']);
Route::get('/coauthor/labedCandidate', [CoAuthorController::class, 'labedCandidate']);
Route::get('/coauthor/calculateMeasures', [CoAuthorController::class, 'getMeasures']);
Route::post('/coauthor/calculateMeasures', [CoAuthorController::class, 'calculateMeasures']);
Route::post('/coauthor/calculateCN', [CoAuthorController::class, 'calculateCN']);
Route::post('/coauthor/calculateAA', [CoAuthorController::class, 'calculateAA']);
Route::post('/coauthor/calculateJC', [CoAuthorController::class, 'calculateJC']);
Route::post('/coauthor/calculateRA', [CoAuthorController::class, 'calculateRA']);
Route::get('/coauthor/test', [CoAuthorController::class, 'test']);
// Route::post('/coauthor/test', [CoAuthorController::class, 'test']);
Route::get('/coauthor/predict/{id}', [CoAuthorController::class, 'predict']);

// papers
Route::get('/papers', [PaperController::class, 'index']);
Route::post('/papers', [PaperController::class, 'store']);
Route::get('/papers/{paper}', [PaperController::class, 'show']);
Route::put('/papers/{id}', [PaperController::class, 'update']);
Route::delete('/papers/{id}', [PaperController::class, 'destroy']);
