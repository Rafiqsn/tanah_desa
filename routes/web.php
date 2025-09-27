<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');                     // halaman welcome simulasi
Route::view('/tanah/simulate', 'tanah.simulate'); // tabel A.6 dummy
