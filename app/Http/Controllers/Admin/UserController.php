<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * @return View
     */
    public function index()
    {
        $users = User::paginate(10);

        return view('admin.user.index', compact('users'));
    }



    /**
     * @param User $user
     * @return View
     */
    public function show(User $user)
    {
        return view('admin.user.show', compact('user'));
    }
}
