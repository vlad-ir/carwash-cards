<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = User::with('roles');

            // Применяем фильтры
            if ($request->has('name') && !empty($request->name)) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }
            if ($request->has('email') && !empty($request->email)) {
                $query->where('email', 'like', '%' . $request->email . '%');
            }
            if ($request->has('role') && !empty($request->role)) {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            return DataTables::of($query)
                ->addColumn('checkbox', function($user) {
                    return '<input type="checkbox" class="select-row" value="' . $user->id . '">';
                })
                ->addColumn('roles', function($user) {
                    return $user->roles->pluck('description')->implode(', ');
                })
                ->addColumn('action', function($user) {
                    $buttons = '<div class="action-buttons">';
                    $buttons .= '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal' . $user->id . '"><i class="fas fa-edit"></i></button>';
                    
                    if ($user->id !== auth()->id()) {
                        $buttons .= '<form action="' . route('users.destroy', $user) . '" method="POST" class="d-inline">';
                        $buttons .= csrf_field();
                        $buttons .= method_field('DELETE');
                        $buttons .= '<button type="submit" class="btn btn-sm btn-danger delete-single" data-user-name="' . $user->name . '"><i class="fas fa-trash"></i></button>';
                        $buttons .= '</form>';
                    }
                    
                    $buttons .= '</div>';
                    return $buttons;
                })
                ->rawColumns(['checkbox', 'action'])
                ->make(true);
        }

        $roles = Role::all();
        $users = User::with('roles')->get();
        return view('users.index', compact('roles', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'roles' => 'required|array'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->roles()->attach($request->roles);

        return redirect()->route('users.index')->with('success', 'Пользователь успешно создан');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'roles' => 'required|array'
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => 'string|min:8'
            ]);
            $user->update([
                'password' => Hash::make($request->password)
            ]);
        }

        $user->roles()->sync($request->roles);

        return redirect()->route('users.index')->with('success', 'Пользователь успешно обновлен');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Вы не можете удалить свой аккаунт');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'Пользователь успешно удален');
    }

    public function deleteSelected(Request $request)
    {
        $ids = $request->input('ids');

        if (in_array(auth()->id(), $ids)) {
            return response()->json(['error' => 'Вы не можете удалить свой аккаунт'], 400);
        }

        User::whereIn('id', $ids)->delete();
        return response()->json(['success' => true]);
    }
}
