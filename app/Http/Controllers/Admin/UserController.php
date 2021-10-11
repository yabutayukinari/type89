<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\AdminUserUpdate;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use App\Repositories\Slack\SlackRepository as SlackPepo;
use App\Notifications\UpdateUserSlack;

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
     * @param SlackPepo $slackHook
     * @param UserUpdateRequest $request
     * @param User $user
     * @return RedirectResponse
     */
    public function update(SlackPepo $slackHook, UserUpdateRequest $request, User $user): RedirectResponse
    {
        Log::info("".__LINE__);
        $user->update($request->only(['nickname', 'email']));
        Log::info("".__LINE__);
        AdminUserUpdate::dispatch($user);
        $slackHook->notify(new UpdateUserSlack());
        Log::info("".__LINE__);
        return back()->with('status', 'Profile updated!');
    }
}
