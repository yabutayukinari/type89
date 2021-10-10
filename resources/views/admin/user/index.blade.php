@extends('admin.layouts.admin_layouts')
@section('title')
    ユーザー一覧
@endsection
@section('content')
    <div class="col">
        <div class="row">
            <div class="alert alert-primary" role="alert">
                A simple primary alert—check it out!
            </div>
        </div>
        <div class="row">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">nickname</th>
                    <th scope="col">email</th>
                    <th scope="col">last_login_at</th>
                    <th scope="col">created_at</th>
                    <th scope="col">action</th>
                </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <th scope="row">{{ $user->id }}</th>
                        <td>{{ $user->nickname }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->last_login_at }}</td>
                        <td>{{ $user->created_at }}</td>
                        <td><a class="btn btn-outline-primary" role="button" href="{{route('admin_user_show',
                        [$user->id])
                        }}">詳細</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="row">
            {{ $users->onEachSide(5)->links() }}
        </div>

    </div>

@endsection
