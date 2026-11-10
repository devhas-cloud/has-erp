<?php

use App\Http\Middleware\CheckAccessControl;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'access.control' => CheckAccessControl::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Logout dari halaman yang sudah lama terbuka bisa membawa token CSRF
        // lama. Jangan tampilkan 419: selesaikan logout dan kembali ke login.
        // TokenMismatchException sudah dipetakan Laravel menjadi HttpException 419.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 && $request->is('logout')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
            }
        });
    })->create();
