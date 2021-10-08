<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * @param Request $request
     * @return View
     */
    public function index(Request $request)
    {
        $users = User::paginate(10);

        return view('admin.user.index', compact('users'));
    }



    /**
     * @param Request $request
     * @param User $user
     * @return View
     */
    public function show(Request $request, User $user)
    {
        return view('admin.user.show', compact('user'));
    }
}
