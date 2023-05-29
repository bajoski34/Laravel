<?php

Route::get('/flw-error', function () {
    return view( 'flutterwave::errors.invalid');
})->name('flutterwave.error');
