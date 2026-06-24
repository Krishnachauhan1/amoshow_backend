<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminHomeContentController;
use App\Http\Controllers\Api\AdminHomeBannerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChunkedUploadController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ShortsController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\WatchHistoryController;
use App\Http\Controllers\Api\VideoCollabController;
use App\Http\Controllers\Api\VideoPurchaseController;
use App\Http\Controllers\Api\YoutubeCommentController;
use App\Http\Controllers\Api\YoutubeController;
use Illuminate\Support\Facades\Route;



// admin register
Route::post('/register-admin', [AdminController::class, 'registerAdmin']);


// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/videos',    [VideoController::class, 'index']);
Route::get('/videos/{video}', [VideoController::class, 'show']);
Route::get('/home',     [HomeController::class, 'index']);
Route::get('/search',    SearchController::class);
Route::get('/plans',     [SubscriptionController::class, 'plans']);
Route::get('/channels/{channel}', [ChannelController::class, 'show']);
Route::get('/channels', [ChannelController::class, 'index']);

// YouTube-style public API (optional auth for is_subscribed / is_liked)
Route::prefix('youtube')->middleware('optional.auth')->group(function () {
    Route::get('/feed',              [YoutubeController::class, 'feed']);
    Route::get('/explore',           [YoutubeController::class, 'explore']);
    Route::get('/explore/{slug}',    [YoutubeController::class, 'exploreSection']);
    Route::get('/categories',        [YoutubeController::class, 'categories']);
    Route::get('/genres',            [YoutubeController::class, 'genres']);
    Route::get('/channels',          [YoutubeController::class, 'channels']);
    Route::get('/channels/{channel}', [YoutubeController::class, 'showChannel']);
    Route::get('/videos/{video}',    [YoutubeController::class, 'show']);
    Route::get('/videos/{video}/comments', [YoutubeCommentController::class, 'index']);
    Route::get('/search',            [YoutubeController::class, 'search']);
    Route::get('/membership-plans',  [YoutubeController::class, 'membershipPlans']);
    Route::post('/videos/{video}/view', [YoutubeController::class, 'recordView']);
});

// YouTube Shorts-style public API
Route::prefix('shorts')->group(function () {
    Route::get('/feed',                    [ShortsController::class, 'feed']);
    Route::get('/trending',                [ShortsController::class, 'trending']);
    Route::get('/genres',                  [ShortsController::class, 'genres']);
    Route::get('/search',                  [ShortsController::class, 'search']);
    Route::get('/channels/{channel}',      [ShortsController::class, 'channelShorts']);
    Route::get('/{video}',                 [ShortsController::class, 'show']);
    Route::get('/{video}/comments',       [YoutubeCommentController::class, 'index']);
    Route::post('/{video}/view',           [ShortsController::class, 'recordView']);
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::match(['put', 'patch'], '/profile', [AuthController::class, 'updateProfile']);

    // Channel
    Route::post('/channels',          [ChannelController::class, 'store']);
    Route::put('/channels/{channel}', [ChannelController::class, 'update']);
    Route::delete('/channels/{channel}', [ChannelController::class, 'destroy']);
    Route::get('/my-channel',         [ChannelController::class, 'myChannel']);

    // Videos
    Route::post('/videos',               [VideoController::class, 'upload']);
    Route::put('/videos/{video}',        [VideoController::class, 'update']);
    Route::delete('/videos/{video}',     [VideoController::class, 'destroy']);
    Route::get('/videos/{video}/stream', [VideoController::class, 'stream']);

    // Watch history
    Route::get('/continue-watching',         [WatchHistoryController::class, 'continueWatching']);
    Route::get('/history',                   [WatchHistoryController::class, 'index']);
    Route::post('/videos/{video}/progress',  [WatchHistoryController::class, 'update']);
    Route::get('/videos/{video}/progress',   [WatchHistoryController::class, 'getProgress']);
    Route::delete('/history/{video}',        [WatchHistoryController::class, 'destroy']);

    // Subscription
    Route::post('/subscribe/initiate', [SubscriptionController::class, 'initiate']);
    Route::post('/subscribe/verify',   [SubscriptionController::class, 'verify']);
    Route::get('/subscription/current',[SubscriptionController::class, 'current']);

    // YouTube creator (authenticated)
    Route::prefix('youtube')->group(function () {
        Route::get('/subscriptions',         [YoutubeController::class, 'subscriptions']);
        Route::post('/subscribe/{channel}', [YoutubeController::class, 'subscribe']);
        Route::delete('/subscribe/{channel}', [YoutubeController::class, 'unsubscribe']);
        Route::get('/profile/stats',        [YoutubeController::class, 'profileStats']);
        Route::get('/profile/earnings',      [YoutubeController::class, 'profileEarnings']);
        Route::patch('/channel/privacy',     [YoutubeController::class, 'toggleChannelPrivacy']);
        Route::get('/my-videos',             [YoutubeController::class, 'myVideos']);
        Route::post('/videos',               [YoutubeController::class, 'upload']);
        Route::post('/videos/{video}/like',  [YoutubeController::class, 'likeVideo']);
        Route::delete('/videos/{video}/like', [YoutubeController::class, 'unlikeVideo']);
        Route::post('/videos/{video}/purchase/initiate', [VideoPurchaseController::class, 'initiate']);
        Route::post('/videos/{video}/purchase/verify', [VideoPurchaseController::class, 'verify']);
        Route::post('/videos/{video}/comments', [YoutubeCommentController::class, 'store']);
        Route::delete('/videos/{video}/comments/{comment}', [YoutubeCommentController::class, 'destroy']);
        Route::get('/ads',                   [YoutubeController::class, 'adHistory']);
        Route::post('/ads',                  [YoutubeController::class, 'createAd']);
        Route::get('/collab/requests',       [VideoCollabController::class, 'incoming']);
        Route::post('/collab/{collaboration}/approve', [VideoCollabController::class, 'approve']);
        Route::post('/collab/{collaboration}/reject',  [VideoCollabController::class, 'reject']);
        Route::get('/notifications',          [VideoCollabController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [VideoCollabController::class, 'markNotificationRead']);
    });

    // Shorts creator (authenticated)
    Route::prefix('shorts')->group(function () {
        Route::get('/my-shorts',             [ShortsController::class, 'myShorts']);
        Route::post('/',                     [ShortsController::class, 'upload']);
        Route::post('/{video}/like',         [ShortsController::class, 'toggleLike']);
        Route::post('/{video}/comments',    [YoutubeCommentController::class, 'store']);
        Route::delete('/{video}/comments/{comment}', [YoutubeCommentController::class, 'destroy']);
        Route::delete('/{video}',            [ShortsController::class, 'destroy']);
    });

    // Chunked upload
    Route::post('/upload/initiate',  [ChunkedUploadController::class, 'initiate']);
    Route::post('/upload/chunk',     [ChunkedUploadController::class, 'uploadChunk']);
    Route::post('/upload/finalize',  [ChunkedUploadController::class, 'finalize']);

    // Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats',   [AdminController::class, 'stats']);
        Route::get('/revenue', [AdminController::class, 'revenue']);
        Route::post('/videos/upload', [AdminController::class, 'uploadVideo']); // ADD

        Route::get('/users',                      [AdminController::class, 'users']);
        Route::post('/users/{user}/subscription', [AdminController::class, 'updateSubscription']);

        Route::get('/videos',                  [AdminController::class, 'videos']);
        Route::patch('/videos/{video}/status', [AdminController::class, 'updateVideoStatus']);
        Route::delete('/videos/{video}',       [AdminController::class, 'deleteVideo']);

        // Categories (OTT)
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);

        // Home content sections (Music, Movies, Games, etc.)
        Route::get('/home-content/layouts', [AdminHomeContentController::class, 'layouts']);
        Route::put('/home-content/{section}/layout', [AdminHomeContentController::class, 'updateLayout']);
        Route::get('/home-content/{section}', [AdminHomeContentController::class, 'index']);
        Route::post('/home-content/{section}', [AdminHomeContentController::class, 'store']);
        Route::delete('/home-content/{section}/{id}', [AdminHomeContentController::class, 'destroy']);

        // Home Banners (hero carousel)
        Route::get('/home-banners', [AdminHomeBannerController::class, 'index']);
        Route::post('/home-banners', [AdminHomeBannerController::class, 'store']);
        Route::post('/home-banners/{homeBanner}', [AdminHomeBannerController::class, 'update']);
        Route::put('/home-banners/{homeBanner}', [AdminHomeBannerController::class, 'update']);
        Route::delete('/home-banners/{homeBanner}', [AdminHomeBannerController::class, 'destroy']);

        Route::post('/plans',          [AdminController::class, 'storePlan']);
        Route::put('/plans/{plan}',    [AdminController::class, 'updatePlan']);
        Route::delete('/plans/{plan}', [AdminController::class, 'deletePlan']);
    });
});