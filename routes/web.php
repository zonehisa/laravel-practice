<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
});

Volt::route('/gin-format-converter', 'gin-format-converter.index')
    ->name('gin-format-converter.index');

Route::get('/gin-format-converter/download/{file}', function (string $file) {
    $filePath = storage_path('app/public/' . $file);

    if (! file_exists($filePath)) {
        abort(404, 'ファイルが見つかりません。');
    }

    return response()->download($filePath, $file, [
        'Content-Type' => 'text/plain; charset=Shift_JIS',
    ]);
})->name('gin-format-converter.download');

Volt::route('/register-data-importer', 'register-data-importer.index')
    ->name('register-data-importer.index');

Route::get('/register-data-importer/download/{file}', function (string $file) {
    $filePath = storage_path('app/public/' . $file);

    if (! file_exists($filePath)) {
        abort(404, 'ファイルが見つかりません。');
    }

    return response()->download($filePath, $file, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
})->name('register-data-importer.download');

Volt::route('/consignment-invoice-generator', 'consignment-invoice-generator.index')
    ->name('consignment-invoice-generator.index');

Route::get('/consignment-invoice-generator/download/{file}', function (string $file) {
    $filePath = storage_path('app/public/' . $file);

    if (! file_exists($filePath)) {
        abort(404, 'ファイルが見つかりません。');
    }

    return response()->download($filePath, $file, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
})->name('consignment-invoice-generator.download');

Volt::route('/reservations', 'reservations.calendar')
    ->name('reservations.calendar');

Volt::route('/reservations/create', 'reservations.create')
    ->name('reservations.create');

Volt::route('/reservations/{reservation}', 'reservations.show')
    ->name('reservations.show');

Volt::route('/reservations/{reservation}/edit', 'reservations.edit')
    ->name('reservations.edit');
