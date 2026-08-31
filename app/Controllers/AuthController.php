<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;
use App\Models\EmailJob;
use App\Models\SystemSetting;

class AuthController {
    public function showLogin(): string {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        return View::render('auth/login', [], 'layouts/auth');
    }

    public function login(Request $request): void {
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (empty($email) || empty($password)) {
            flash('error', 'Please enter email and password.');
            redirect('/login');
            return;
        }

        if (Auth::attempt($email, $password)) {
            flash('success', 'Welcome back, ' . auth_user()->name . '!');
            if (Auth::isAdmin()) {
                redirect('/admin');
            } else {
                redirect('/dashboard');
            }
            return;
        }

        flash('error', 'Invalid email credentials or account is suspended.');
        redirect('/login');
    }

    public function showRegister(): string {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        return View::render('auth/register', [], 'layouts/auth');
    }

    public function register(Request $request): void {
        $name = trim($request->input('name', ''));
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');
        $passwordConfirm = $request->input('password_confirmation', '');
        $startTrial = $request->input('start_trial', '1') === '1';

        if (empty($name) || empty($email) || empty($password)) {
            flash('error', 'All fields are required.');
            redirect('/register');
            return;
        }

        if ($password !== $passwordConfirm) {
            flash('error', 'Passwords do not match.');
            redirect('/register');
            return;
        }

        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters long.');
            redirect('/register');
            return;
        }

        $existing = User::findByEmail($email);
        if ($existing) {
            flash('error', 'An account with this email already exists.');
            redirect('/register');
            return;
        }

        $verificationToken = bin2hex(random_bytes(32));

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'verification_token' => $verificationToken,
        ]);

        // 1. Dispatch Welcome Email
        EmailJob::dispatchTemplate('welcome', $user->email, [
            'name' => $user->name,
            'email' => $user->email,
        ], "welcome:{$user->id}", $user->id, $user->name);

        // 2. Dispatch Email Verification Email
        $verifyUrl = url('/verify-email?token=' . $verificationToken);
        EmailJob::dispatchTemplate('email_verification', $user->email, [
            'name' => $user->name,
            'verification_url' => $verifyUrl,
        ], "verify:{$user->id}", $user->id, $user->name);

        // 3. Auto-start Free Trial if eligible
        if ($startTrial && $user->canStartTrial()) {
            $trialDays = (int)SystemSetting::get('trial_duration_days', '14');
            $trialLimit = (int)SystemSetting::get('trial_gmail_limit', '5');
            $user->startTrial($trialDays, $trialLimit);

            EmailJob::dispatchTemplate('trial_started', $user->email, [
                'name' => $user->name,
                'trial_days' => $trialDays,
                'gmail_limit' => $trialLimit,
                'start_date' => date('d M Y'),
                'expiry_date' => date('d M Y', strtotime("+{$trialDays} days")),
            ], "trial_started:{$user->id}", $user->id, $user->name);
        }

        Auth::login($user);
        flash('success', 'Registration successful! Welcome to Gmail Automation.');
        redirect('/dashboard');
    }

    /**
     * Verify email via signed token
     */
    public function verifyEmail(Request $request): void {
        $token = $request->query('token', '');
        if (empty($token)) {
            flash('error', 'Invalid or missing email verification link.');
            redirect('/dashboard');
            return;
        }

        $user = User::findByVerificationToken($token);
        if (!$user) {
            flash('error', 'Verification token is invalid or has expired.');
            redirect('/dashboard');
            return;
        }

        $user->update([
            'email_verified_at' => date('Y-m-d H:i:s'),
            'verification_token' => null,
        ]);

        flash('success', 'Your email address has been successfully verified!');
        if (!Auth::check()) {
            Auth::login($user);
        }
        redirect('/dashboard');
    }

    public function logout(): void {
        Auth::logout();
        flash('success', 'You have been logged out.');
        redirect('/login');
    }
}
