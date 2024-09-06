<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $layout = array(
            'title'     => 'Login',
            'required'  => ['login'],
        );
        return view('auth.login', $layout);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Get the authenticated user
        $user = $request->user();

        // Check user permissions and redirect accordingly
        if ($user->hasRole('super admin | root')) {
            return redirect()->route('pasien'); // Replace with your route name

        } else {
            if ($user->hasPermissionTo('list-pasien')) {
                return redirect()->route('pasien'); // Replace with your route name
            } elseif ($user->hasPermissionTo('list-kunjungan')) {
                return redirect()->route('visit'); // Replace with your route name
            } elseif ($user->hasPermissionTo('list-resep-pasien')) {
                return redirect()->route('pharmacy'); // Replace with your route name
            } elseif ($user->hasPermissionTo('list-transaksi')) {
                return redirect()->route('transaction'); // Replace with your route name
            }
        }



        // return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
