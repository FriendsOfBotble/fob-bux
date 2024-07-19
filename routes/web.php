<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'FriendsOfBotble\Bux\Http\Controllers', 'middleware' => ['core']], function () {
    Route::get('bux/payment/callback', [
        'as'   => 'bux.payment.callback',
        'uses' => 'BuxController@getCallback',
    ]);

    Route::get('bux/payment/notifications', [
        'as'   => 'bux.payment.notifications',
        'uses' => 'BuxController@getNotifications',
    ]);
});
