<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});
Broadcast::channel('video-consultation.{id}', function ($user, $id) {
    return true;
});
Broadcast::channel('consultation-nurse.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});
Broadcast::channel('consultation-doctor.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});
Broadcast::channel('consultation-receptionist.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});
Broadcast::channel('consultation-waiting-list-doctor.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});
Broadcast::channel('consultation-waiting-list-nurse.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});
