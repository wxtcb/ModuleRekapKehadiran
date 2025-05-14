<?php

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

use Illuminate\Support\Facades\Route;

Route::prefix('rekapkehadiran')->group(function() {
    Route::prefix('kehadirani')->group(function() {
        Route::get('/', 'KehadiranIController@index')->name('kehadirani.index');  
        Route::get('/rekap-kehadiran/export', 'KehadiranIController@export')->name('rekap-kehadiran.export');
      
    });

    Route::prefix('kehadiranii')->group(function() {
        Route::get('/', 'KehadiranIIController@index')->name('kehadiranii.index');        
        Route::get('/rekap-kehadiran/export', 'KehadiranIIController@export')->name('rekap-kehadiran.export');
    });
    
    Route::prefix('kehadiraniii')->group(function() {
        Route::get('/', 'KehadiranIIIController@index')->name('kehadiraniii.index');        
        Route::get('/rekap-kehadiran/export', 'KehadiranIIIController@export')->name('rekap-kehadiran.export');
    });
});
