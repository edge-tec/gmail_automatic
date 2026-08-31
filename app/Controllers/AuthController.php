<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;

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

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        Auth::login($user);
        flash('success', 'Registration successful! Connect your Gmail account to begin.');
        redirect('/dashboard');
    }

    public function logout(): void {
        Auth::logout();
        flash('success', 'You have been logged out.');
        redirect('/login');
    }
}
