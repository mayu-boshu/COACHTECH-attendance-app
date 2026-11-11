<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.admin-login');
    }
    public function login(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => ['required', 'email'],
                'password' => ['required'],
            ],
            [
                'email.required' => 'メールアドレスを入力してください',
                'email.email' => 'メールアドレスはメール形式で入力してください',
                'password.required' => 'パスワードを入力してください',
            ]
        );

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }

        if (Auth::attempt($request->only('email', 'password'))) {
            if (Auth::user()->is_admin) {
                $request->session()->regenerate();

                return redirect('/admin/attendance');
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return back()
            ->withErrors([
                'email' => 'ログイン情報が登録されていません',
            ])
            ->withInput($request->only('email'));
    }
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
