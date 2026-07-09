<?php

use think\facade\Route;

// 管理后台 API
Route::group('admin', function () {
    Route::post('login', 'Admin/login');

    Route::group(function () {
        Route::get('projects', 'Admin/projects');
        Route::get('activity-float', 'Admin/activityFloatList');
        Route::post('activity-float', 'Admin/activityFloatSave');
        Route::delete('activity-float', 'Admin/activityFloatDelete');
        Route::get('announcements', 'Admin/announcementList');
        Route::post('announcements', 'Admin/announcementSave');
        Route::delete('announcements', 'Admin/announcementDelete');
        Route::get('users/search', 'Admin/searchUsers');
        Route::get('users/detail', 'Admin/userDetail');
        Route::get('mails', 'Admin/mailList');
        Route::post('mails/send', 'Admin/mailSend');
    })->middleware(\app\middleware\AdminAuth::class);
});
