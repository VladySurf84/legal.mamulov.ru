<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(UserAccess::canViewUsers($request->user()), 403);

        $users = User::query()
            ->withCount(['accessScopes', 'modulePermissions'])
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        return view('users.index', [
            'users' => $users,
            'canViewUserAccess' => UserAccess::canViewUserAccess($request->user()),
        ]);
    }
}
