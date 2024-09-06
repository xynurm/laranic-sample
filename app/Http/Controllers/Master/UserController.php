<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Master\Common;
use App\Models\Master\Obat;
use App\Models\Master\ObatBatch;
use App\Models\Master\ObatMasuk;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\MedicineLog;
use App\Models\MedicineType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    function index()
    {
        $users = User::with('role')->get()->map(function ($user) {
            return $user->only(['id', 'name', 'username', 'email', 'role']);
        });
        $layout = array(
            'title'     => 'List User',
            'required'  => ['dataTable'],
            'users' => $users,
        );

        // dd($layout);
        return view('pages.master.user.index', $layout);
    }

    public function create()
    {
        $layout = array(
            'title'     => 'Tambah User',
            'required'  => ['form'],
            'roles' => Role::where('id', '!=', 1)->get(),
        );
        return view('pages.master.user.create', $layout);
    }


    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required'],
            'username' => ['required', 'string', 'max:30', 'unique:users,username', 'regex:/^[a-zA-Z0-9._]+$/',],
            'password' => ['required', 'min:5'],
            'confirmed' => ['required', 'same:password'],
        ], [
            'name.required' => 'Nama tidak boleh kosong.',
            'role.required' => 'Peran tidak boleh kosong.',
            'confirmed.same' => 'Konfirmasi password yang anda masukkan tidak sesuai.',
            'username.unique' => 'Username tersebut sudah terdaftar.',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => ucwords($request->name),
                'username' => $request->username,
                'role_id' => $request->role,
                'password' => Hash::make($request->password),
            ]);

            if (!$user) {
                return redirect()
                    ->back()
                    ->with('error', 'Gagal mendafatarkan user!');
            }

            $role = Role::findOrFail($user->role_id);

            if (!$role) {
                return redirect()
                    ->back()
                    ->with('error', 'Peran tidak ditemukan!');
            }

            if ($role->name === 'doctor') {
                Doctor::create([
                    'name' => $request->name,
                    'user_id' => $user->id,
                ]);
            }

            $user->assignRole($role->name);
            DB::commit();
            return redirect('/user')
                ->with('success', 'Berhasil mendafatarkan user!');
        } catch (\Exception  $e) {
            DB::rollBack();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function account($id)
    {
        $root = Auth::user()->hasRole('root');

        if ($root) {
            $action = "/user/profile/update-account/$id";
            return $this->getAccountView($id, $action);
        } else {
            return redirect('/user/profile/account-user');
        }
    }

    public function accountUser()
    {
        $idUser = Auth::user()->id;
        $action = '/user/profile/update-account-user';
        return $this->getAccountView($idUser, $action);
    }

    private function getAccountView($id, $action)
    {
        try {
            $user = User::findOrFail($id);
            $layout = [
                'title' => 'Account Settings - Account',
                'required' => ['form'],
                'user' => $user,
                'action' => $action,
                'roles' => Role::where('id', '!=', 1)->get(),
            ];

            return view('pages.master.user.profile.account', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());

            return redirect()
                ->back()
                ->with('error', 'User tidak ditemukan');
        }
    }


    public function updateAccount(Request $request, $id)
    {

        $user = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:30',
                'unique:users,username,' . $user->id,
                'regex:/^[a-zA-Z0-9._]+$/'
            ],
        ], [
            'name.required' => 'Nama tidak boleh kosong.',
            'username.unique' => 'Username tersebut sudah terdaftar.',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::findOrFail($request->role);
            if ($user->role->name === 'doctor' && $user->role_id != $request->role) {
                $doctor = Doctor::where('user_id', $user->id)->first();
                if ($doctor) {
                    $doctor->delete();
                }
            }

            if ($role->name === 'doctor') {
                Doctor::create([
                    'name' => $request->name,
                    'user_id' => $user->id,
                ]);
            }

            if ($user->role_id != $request->role) {
                //unlink all role user and assign new role
                $user->roles()->detach();
                $user->assignRole($role->name);
            }

            $user->name = $request->name;
            $user->role_id = $request->role;
            $user->username = $request->username;

            $user->save();
            DB::commit();
            return redirect()
                ->back()
                ->with('success', 'Berhasil update profile');
        } catch (\Exception  $e) {
            DB::rollBack();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Gagal update profile');
        }
    }


    public function updateAccountUser(Request $request)
    {
        $id = Auth::user()->id;
        $user = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:30',
                'unique:users,username,' . $user->id,
                'regex:/^[a-zA-Z0-9._]+$/'
            ],
        ], [
            'name.required' => 'Nama tidak boleh kosong.',
            'username.unique' => 'Username tersebut sudah terdaftar.',
        ]);

        DB::beginTransaction();
        try {
            $user->name = $request->name;
            $user->username = $request->username;

            $user->save();
            DB::commit();
            return redirect()
                ->back()
                ->with('success', 'Berhasil melakukan update profile');
        } catch (\Exception  $e) {
            DB::rollBack();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Gagal  melakukan update profile');
        }
    }



    public function security($id) // param id user
    {
        $root = Auth::user()->hasRole('root');

        if ($root) {
            $action = "/user/profile/update-security/$id";
            return $this->getSecurityView($id, $action);
        } else {
            return redirect('/user/profile/security-user');
        }
    }

    public function securityUser()
    {
        $idUser = Auth::user()->id;
        $action = '/user/profile/update-security-user';
        return $this->getSecurityView($idUser, $action);
    }

    public function getSecurityView($id, $action)
    {
        try {
            $user = User::findOrFail($id);
            $layout = array(
                'title'     => 'Account Settings - Security',
                'required'  => ['form'],
                'action' => $action,
                'user'  =>  $user,
            );
            return view('pages.master.user.profile.security', $layout);
        } catch (\Exception  $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'User tidak ditemukan');
        }
    }

    public function updateSecurity(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'password' => ['required', 'min:5'],
            'confirmed' => ['required', 'same:password'],
        ], [
            'confirmed.same' => 'Konfirmasi password yang anda masukkan tidak sesuai.',
        ]);

        try {
            $user->password = Hash::make($request->password);
            $user->save();
            DB::commit();
            return redirect()
                ->back()
                ->with('success', 'Berhasil update profile');
        } catch (\Exception  $e) {
            DB::rollBack();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Gagal update profile ');
        }
    }

    public function updateSecurityUser(Request $request)
    {
        $id = Auth::user()->id;
        $user = User::findOrFail($id);
        $request->validate([
            'password' => ['required', 'min:5'],
            'confirmed' => ['required', 'same:password'],
        ], [
            'confirmed.same' => 'Konfirmasi password yang anda masukkan tidak sesuai.',
        ]);
        try {
            $user->password = Hash::make($request->password);
            $user->save();
            DB::commit();
            return redirect()
                ->back()
                ->with('success', 'Berhasil melakukan perubahan password');
        } catch (\Exception  $e) {
            DB::rollBack();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Gagal melakukan perubahan password');
        }
    }
}
