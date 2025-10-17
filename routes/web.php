<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use Illuminate\Support\Facades\Auth;

// Auth::routes(['verify' => true]);

 
 
 Route::get('/_preview/reset-mail', function () {
    $fakeUser = \App\Models\User::first() ?? (object)['prenom' => 'Issa', 'nom' => 'Barry', 'email' => 'issa@example.com'];
    return view('emails.passwordReset', [
        'appName'       => config('app.name', 'EAU-LA-MAMAN'),
        'url'           => 'http://localhost:4200/auth/newpassword?token=demo&email=issa%40example.com',
        'expiresIn'     => '60 minutes',
        'user'          => $fakeUser,
        'userFirstName' => 'Issa',
        'userLastName'  => 'Barry',
    ]);
});
