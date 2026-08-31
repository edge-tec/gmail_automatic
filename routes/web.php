<?php
/**
 * Web Application Routes
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\CSRFMiddleware;

/** @var \App\Core\Router $router */

// Public / Guest Routes
$router->get('/', function() {
    if (\App\Core\Auth::check()) {
        redirect('/dashboard');
    } else {
        redirect('/login');
    }
});

$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login', [CSRFMiddleware::class]);
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register', [CSRFMiddleware::class]);
$router->get('/logout', 'AuthController@logout');

// Webhook Route (Exempt from CSRF)
$router->post('/webhook/gmail/pubsub', 'WebhookController@handlePubSub');

// OAuth Callback Route
$router->get('/auth/google/callback', 'GmailAccountController@callback');

// Authenticated User Routes
$router->get('/dashboard', 'DashboardController@index', [AuthMiddleware::class]);

// Gmail Accounts
$router->get('/accounts', 'GmailAccountController@index', [AuthMiddleware::class]);
$router->get('/accounts/connect', 'GmailAccountController@connect', [AuthMiddleware::class]);
$router->post('/accounts/{id}/disconnect', 'GmailAccountController@disconnect', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/accounts/{id}/toggle-reply', 'GmailAccountController@toggleAutoReply', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/accounts/{id}/toggle-followup', 'GmailAccountController@toggleFollowup', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/accounts/{id}/sync', 'GmailAccountController@syncNow', [AuthMiddleware::class, CSRFMiddleware::class]);

// Automation Settings
$router->get('/settings/automation', 'AutomationSettingsController@show', [AuthMiddleware::class]);
$router->get('/settings/automation/{id}', 'AutomationSettingsController@show', [AuthMiddleware::class]);
$router->post('/settings/automation/{id}', 'AutomationSettingsController@update', [AuthMiddleware::class, CSRFMiddleware::class]);

// Follow-up Steps
$router->get('/settings/followups', 'FollowupController@show', [AuthMiddleware::class]);
$router->get('/settings/followups/{id}', 'FollowupController@show', [AuthMiddleware::class]);
$router->post('/settings/followups/{id}/create', 'FollowupController@create', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/settings/followups/step/{id}/update', 'FollowupController@update', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/settings/followups/step/{id}/delete', 'FollowupController@delete', [AuthMiddleware::class, CSRFMiddleware::class]);

// Conversation Threads
$router->get('/threads', 'ThreadController@index', [AuthMiddleware::class]);
$router->get('/threads/{id}', 'ThreadController@show', [AuthMiddleware::class]);
$router->post('/threads/{id}/toggle-automation', 'ThreadController@toggleAutomation', [AuthMiddleware::class, CSRFMiddleware::class]);

// Admin Panel Routes
$router->get('/admin', 'AdminController@index', [AdminMiddleware::class]);
$router->get('/admin/users', 'AdminController@users', [AdminMiddleware::class]);
$router->post('/admin/users/{id}/toggle', 'AdminController@toggleUserStatus', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/settings', 'AdminController@settings', [AdminMiddleware::class]);
$router->post('/admin/settings', 'AdminController@updateSettings', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/logs', 'AdminController@logs', [AdminMiddleware::class]);
$router->post('/admin/toggle-global', 'AdminController@toggleGlobalAutomation', [AdminMiddleware::class, CSRFMiddleware::class]);
