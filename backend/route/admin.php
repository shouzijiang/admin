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
        Route::post('users/quota', 'Admin/userUpdateQuota');
        Route::post('users/progress', 'Admin/userUpdateProgress');
        Route::get('streamer/unit-prices', 'Admin/channelUnitPriceList');
        Route::post('streamer/unit-prices', 'Admin/channelUnitPriceSave');
        Route::post('streamer/unit-prices/sync', 'Admin/channelUnitPriceSync');
        Route::get('streamer/settlement', 'Admin/streamerSettlement');
        Route::post('streamer/payouts', 'Admin/streamerPayoutAdd');
        Route::get('mails', 'Admin/mailList');
        Route::post('mails/send', 'Admin/mailSend');
        Route::get('orders', 'Admin/orderList');
    })->middleware([
        \app\middleware\AdminAuth::class,
        \app\middleware\AdminRequestLog::class,
    ]);
});
