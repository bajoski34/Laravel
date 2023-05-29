<?php

Route::get('/flw-error', function () {
    return view( 'flutterwave::invalid');
})->name('flutterwave.error');
