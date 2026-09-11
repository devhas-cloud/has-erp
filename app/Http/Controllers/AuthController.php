<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended($this->landingUrl(Auth::user()));
        }

        throw ValidationException::withMessages([
            'username' => ['Username atau password salah.'],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Halaman untuk akun yang belum punya akses can_read ke modul manapun,
     * supaya login tidak berputar mengarah ke modul yang tidak dimiliki.
     */
    public function noAccess()
    {
        return view('auth.no-access');
    }

    /**
     * Tujuan universal setelah login: modul pertama (urutan sidebar) yang
     * boleh dibaca akun ini — tidak terikat pada satu modul tertentu, karena
     * modul yang tersedia bisa berbeda untuk tiap akun.
     */
    private function landingUrl(User $user): string
    {
        $module = Module::firstAccessibleFor($user);

        return $module ? route($module->route_name.'.index') : route('no-access');
    }
}
