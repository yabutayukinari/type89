<!doctype html>
<html lang="ja"{!! env('APP_ENV') && env('APP_ENV') != 'production' ? ' class="at-env-' . env('APP_ENV') . '"' : '' !!}>
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>
        @hasSection('title')
            @yield('title')
        @else
            {{ trans('app.name') }}
        @endif
    </title>
    <link rel="index" href="https://en-photo.net/" />
    <meta name="description" content="{{ trans('app.description') }}">
    <meta name="keywords" content="{{ trans('app.keywords') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="row">
    <nav class="col-2 navbar-light sidebar pink-100">
        <div class="position-sticky pt-3">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">Navbar</a>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="user">
                        ユーザー一覧
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="/">
                        管理者一覧
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="/">
                        お知らせ一覧
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    <main class="col-10 mt-3 pe-5">
        <h1>@yield('title')</h1>
        @yield('content')
    </main>
</div>




</body>
</html>
