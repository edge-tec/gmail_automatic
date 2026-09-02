<?php
/**
 * Web Application Routes
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\CSRFMiddleware;

/** @var \App\Core\Router $router */

// Public Landing Page & Auth Routes
$router->get('/', 'LandingController@index');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login', [CSRFMiddleware::class]);
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register', [CSRFMiddleware::class]);
$router->get('/verify-email', 'AuthController@verifyEmail');
$router->get('/logout', 'AuthController@logout');

// Public Marketing & SEO Pages
$router->get('/features', 'PublicPageController@features');
$router->get('/pricing', 'PublicPageController@pricing');
$router->get('/how-it-works', 'PublicPageController@howItWorks');
$router->get('/faq', 'PublicPageController@faq');
$router->get('/contact', 'PublicPageController@contact');
$router->post('/contact', 'PublicPageController@submitContact', [CSRFMiddleware::class]);
$router->get('/blog', 'PublicPageController@blog');
$router->get('/blog/{slug}', 'PublicPageController@blogSingle');
$router->get('/sitemap.xml', 'PublicPageController@sitemap');
$router->get('/robots.txt', 'PublicPageController@robots');

// Public Legal Pages
$router->get('/privacy', 'LegalController@privacy');
$router->get('/terms', 'LegalController@terms');
$router->get('/google-api-disclosure', 'LegalController@googleApiDisclosure');
$router->get('/zero-fallback-policy', 'LegalController@zeroFallbackPolicy');
$router->get('/data-security', 'LegalController@dataSecurity');

// Webhook Routes (Exempt from CSRF)
$router->post('/webhook/gmail/pubsub', 'WebhookController@handlePubSub');
$router->post('/webhook/stripe', 'StripeWebhookController@handle');

// OAuth Callback Route
$router->get('/auth/google/callback', 'GmailAccountController@callback');

// Authenticated User Routes
$router->get('/dashboard', 'DashboardController@index', [AuthMiddleware::class]);

// Billing & Subscriptions
$router->get('/billing', 'BillingController@index', [AuthMiddleware::class]);
$router->post('/billing/start-trial', 'BillingController@startTrial', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->get('/billing/checkout/{planId}', 'BillingController@checkout', [AuthMiddleware::class]);
$router->post('/billing/checkout/{planId}/stripe', 'BillingController@processStripe', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/billing/checkout/{planId}/bkash', 'BillingController@submitBkash', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/billing/checkout/{planId}/nagad', 'BillingController@submitNagad', [AuthMiddleware::class, CSRFMiddleware::class]);

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
$router->post('/settings/automation/{id}/clear-all', 'AutomationSettingsController@clearAll', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/settings/automation/{id}/delete-all', 'AutomationSettingsController@clearAll', [AuthMiddleware::class, CSRFMiddleware::class]);

// Follow-up Steps
$router->get('/settings/followups', 'FollowupController@show', [AuthMiddleware::class]);
$router->get('/settings/followups/{id}', 'FollowupController@show', [AuthMiddleware::class]);
$router->post('/settings/followups/{id}/create', 'FollowupController@create', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/settings/followups/{id}/clear-all', 'FollowupController@deleteAll', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/settings/followups/{id}/delete-all', 'FollowupController@deleteAll', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/settings/followups/step/{id}/update', 'FollowupController@update', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/settings/followups/step/{id}/delete', 'FollowupController@delete', [AuthMiddleware::class, CSRFMiddleware::class]);

// Filter & Routing Rules
$router->get('/rules', 'RuleController@index', [AuthMiddleware::class]);
$router->get('/rules/{id}', 'RuleController@index', [AuthMiddleware::class]);
$router->post('/rules/{id}/create', 'RuleController@create', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/rules/{id}/toggle', 'RuleController@toggle', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/rules/{id}/delete', 'RuleController@delete', [AuthMiddleware::class, CSRFMiddleware::class]);

// Conversation Threads
$router->get('/threads', 'ThreadController@index', [AuthMiddleware::class]);
$router->get('/threads/{id}', 'ThreadController@show', [AuthMiddleware::class]);
$router->post('/threads/clear-all', 'ThreadController@clearAll', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/threads/{id}/delete', 'ThreadController@delete', [AuthMiddleware::class, CSRFMiddleware::class]);
$router->post('/threads/{id}/toggle-automation', 'ThreadController@toggleAutomation', [AuthMiddleware::class, CSRFMiddleware::class]);

// Activity & Automation Logs
$router->get('/logs', 'LogController@index', [AuthMiddleware::class]);
$router->post('/logs/clear', 'LogController@clear', [AuthMiddleware::class, CSRFMiddleware::class]);

// Duplicate & Skipped Emails Report
$router->get('/skipped-emails', 'SkippedEmailController@index', [AuthMiddleware::class]);
$router->get('/skipped-emails/export', 'SkippedEmailController@exportCsv', [AuthMiddleware::class]);
$router->post('/skipped-emails/clear', 'SkippedEmailController@clear', [AuthMiddleware::class, CSRFMiddleware::class]);

// Admin Panel Routes
$router->get('/admin', 'AdminController@index', [AdminMiddleware::class]);
$router->get('/admin/users', 'AdminController@users', [AdminMiddleware::class]);
$router->post('/admin/users/create', 'AdminController@createUser', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/users/{id}/update', 'AdminController@updateUser', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/users/{id}/toggle', 'AdminController@toggleUserStatus', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/users/{id}/delete', 'AdminController@deleteUser', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/users/{id}/impersonate', 'AdminController@impersonateUser', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/impersonate/leave', 'AdminController@leaveImpersonation', [AuthMiddleware::class]);
$router->get('/admin/impersonate/leave', 'AdminController@leaveImpersonation', [AuthMiddleware::class]);

// Admin Subscription & Trial Controls
$router->get('/admin/plans', 'AdminController@plans', [AdminMiddleware::class]);
$router->post('/admin/plans/{id}/update', 'AdminController@updatePlan', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/trial', 'AdminController@trial', [AdminMiddleware::class]);
$router->post('/admin/trial', 'AdminController@updateTrial', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/smtp', 'AdminController@smtp', [AdminMiddleware::class]);
$router->post('/admin/smtp', 'AdminController@updateSmtp', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/smtp/test', 'AdminController@testSmtp', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/smtp/test-connection', 'AdminController@testSmtpConnection', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/gateways', 'AdminController@gateways', [AdminMiddleware::class]);
$router->post('/admin/gateways', 'AdminController@updateGateways', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/email-templates', 'AdminController@emailTemplates', [AdminMiddleware::class]);
$router->post('/admin/email-templates/{id}/update', 'AdminController@updateEmailTemplate', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/email-logs', 'AdminController@emailLogs', [AdminMiddleware::class]);
$router->post('/admin/email-logs/{id}/resend', 'AdminController@resendEmailJob', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/payments', 'AdminController@payments', [AdminMiddleware::class]);
$router->post('/admin/payments/{id}/approve', 'AdminController@approvePayment', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/payments/{id}/reject', 'AdminController@rejectPayment', [AdminMiddleware::class, CSRFMiddleware::class]);

$router->get('/admin/filters', 'AdminController@filters', [AdminMiddleware::class]);
$router->post('/admin/filters', 'AdminController@updateFilters', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/settings', 'AdminController@settings', [AdminMiddleware::class]);
$router->post('/admin/settings', 'AdminController@updateSettings', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/logs', 'AdminController@logs', [AdminMiddleware::class]);
$router->get('/admin/skipped-emails', 'AdminController@skippedEmails', [AdminMiddleware::class]);
$router->post('/admin/skipped-emails/clear', 'AdminController@clearDuplicateTraffic', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/clear-duplicate-traffic', 'AdminController@clearDuplicateTraffic', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/toggle-global', 'AdminController@toggleGlobalAutomation', [AdminMiddleware::class, CSRFMiddleware::class]);

// Admin SEO Management Suite
$router->get('/admin/seo', 'AdminSeoController@index', [AdminMiddleware::class]);
$router->get('/admin/seo/global', 'AdminSeoController@globalSettings', [AdminMiddleware::class]);
$router->post('/admin/seo/global', 'AdminSeoController@updateGlobalSettings', [AdminMiddleware::class, CSRFMiddleware::class]);

$router->get('/admin/seo/pages', 'AdminSeoController@pages', [AdminMiddleware::class]);
$router->get('/admin/seo/pages/{id}', 'AdminSeoController@editPage', [AdminMiddleware::class]);
$router->post('/admin/seo/pages/{id}', 'AdminSeoController@updatePage', [AdminMiddleware::class, CSRFMiddleware::class]);

$router->get('/admin/seo/redirects', 'AdminSeoController@redirects', [AdminMiddleware::class]);
$router->post('/admin/seo/redirects', 'AdminSeoController@createRedirect', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/seo/redirects/{id}/delete', 'AdminSeoController@deleteRedirect', [AdminMiddleware::class, CSRFMiddleware::class]);

$router->get('/admin/seo/faqs', 'AdminSeoController@faqs', [AdminMiddleware::class]);
$router->post('/admin/seo/faqs', 'AdminSeoController@createFaq', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/seo/faqs/{id}/update', 'AdminSeoController@updateFaq', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/seo/faqs/{id}/delete', 'AdminSeoController@deleteFaq', [AdminMiddleware::class, CSRFMiddleware::class]);

$router->get('/admin/seo/blog', 'AdminSeoController@blog', [AdminMiddleware::class]);
$router->get('/admin/seo/blog/create', 'AdminSeoController@createBlogPost', [AdminMiddleware::class]);
$router->post('/admin/seo/blog/create', 'AdminSeoController@saveBlogPost', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->get('/admin/seo/blog/{id}/edit', 'AdminSeoController@editBlogPost', [AdminMiddleware::class]);
$router->post('/admin/seo/blog/{id}/edit', 'AdminSeoController@saveBlogPost', [AdminMiddleware::class, CSRFMiddleware::class]);
$router->post('/admin/seo/blog/{id}/delete', 'AdminSeoController@deleteBlogPost', [AdminMiddleware::class, CSRFMiddleware::class]);

$router->get('/admin/seo/ai-search', 'AdminSeoController@aiSearch', [AdminMiddleware::class]);
$router->post('/admin/seo/ai-search', 'AdminSeoController@updateAiSearch', [AdminMiddleware::class, CSRFMiddleware::class]);

$router->get('/admin/seo/sitemap-robots', 'AdminSeoController@sitemapRobots', [AdminMiddleware::class]);
$router->post('/admin/seo/robots', 'AdminSeoController@updateRobots', [AdminMiddleware::class, CSRFMiddleware::class]);

