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
