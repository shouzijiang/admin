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
        Route::get('album-categories', 'Admin/albumCategoryList');
        Route::post('album-categories', 'Admin/albumCategorySave');
        Route::delete('album-categories', 'Admin/albumCategoryDelete');
        Route::get('announcements', 'Admin/announcementList');
        Route::post('announcements', 'Admin/announcementSave');
        Route::post('announcements/unpublish', 'Admin/announcementDelete');
        Route::get('users/search', 'Admin/searchUsers');
        Route::get('users/detail', 'Admin/userDetail');
        Route::post('users/quota', 'Admin/userUpdateQuota');
        Route::post('users/progress', 'Admin/userUpdateProgress');
        Route::post('users/vip', 'Admin/userUpdateVip');
        Route::post('users/remark', 'Admin/userUpdateRemark');
        Route::get('streamer/unit-prices', 'Admin/channelUnitPriceList');
        Route::post('streamer/unit-prices', 'Admin/channelUnitPriceSave');
        Route::post('streamer/unit-prices/sync', 'Admin/channelUnitPriceSync');
        Route::get('streamer/settlement', 'Admin/streamerSettlement');
        Route::post('streamer/payouts', 'Admin/streamerPayoutAdd');
        Route::get('mails', 'Admin/mailList');
        Route::post('mails/send', 'Admin/mailSend');
        Route::post('mails/update', 'Admin/mailUpdate');
        Route::get('feedbacks', 'Admin/feedbackList');
        Route::post('feedbacks/reply', 'Admin/feedbackReply');
        Route::post('feedbacks/reply/update', 'Admin/feedbackReplyUpdate');
        Route::get('operation-logs', 'Admin/operationLogList');
        Route::get('leaderboard', 'Admin/leaderboardList');
        Route::get('orders', 'Admin/orderList');

        // 公司官网内容（独立库 qianzhi_website，不接多项目切换）
        Route::get('website/config', 'Website/configList');
        Route::post('website/config', 'Website/configSave');
        Route::get('website/products', 'Website/productList');
        Route::post('website/products', 'Website/productSave');
        Route::delete('website/products', 'Website/productDelete');
        Route::get('website/capabilities', 'Website/capabilityList');
        Route::post('website/capabilities', 'Website/capabilitySave');
        Route::delete('website/capabilities', 'Website/capabilityDelete');
        Route::get('website/milestones', 'Website/milestoneList');
        Route::post('website/milestones', 'Website/milestoneSave');
        Route::delete('website/milestones', 'Website/milestoneDelete');
        Route::get('website/jobs', 'Website/jobList');
        Route::post('website/jobs', 'Website/jobSave');
        Route::delete('website/jobs', 'Website/jobDelete');
        Route::get('website/messages', 'Website/messageList');
        Route::post('website/messages/read', 'Website/messageRead');
        Route::delete('website/messages', 'Website/messageDelete');
    })->middleware([
        \app\middleware\AdminAuth::class,
        \app\middleware\AdminRequestLog::class,
    ]);
});
