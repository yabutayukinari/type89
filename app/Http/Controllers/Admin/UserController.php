<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Services\Admin\User\UserUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * @return View
     */
    public function index(): View
    {
        $users = User::paginate(10);

        return view('admin.user.index', compact('users'));
    }

    /**
     * @param User $user
     * @return View
     */
    public function show(User $user): View
    {
        return view('admin.user.show', compact('user'));
    }

    /**
     * ユーザー更新
     *
     * @param UserUpdateService $service
     * @param UserUpdateRequest $request
     * @param User $user
     * @return RedirectResponse
     */
    public function update(UserUpdateService $service, UserUpdateRequest $request, User $user): RedirectResponse
    {
        $service->execute($user, $request->only(['nickname', 'email']));
        return back()->with('status', 'Profile updated!');
    }
}
