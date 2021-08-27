<?php

Route::group(['namespace' => 'Jgu\Wfotp\Http\Controllers', 'middleware' => ['web']], function(){
    Route::get('wfo-verification/{token}', 'VerificationController@index');
});
