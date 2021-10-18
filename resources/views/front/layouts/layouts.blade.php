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
<body class="d-flex h-100 text-center text-white bg-dark">
<div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">
    <header class="mb-auto">
        <div>
            <h3 class="float-md-start mb-0">Type89</h3>
            <nav class="nav nav-masthead justify-content-center float-md-end">
                <a class="nav-link active" aria-current="page" href="#">ホーム</a>
                <a class="nav-link" href="#">特徴</a>
                <a class="nav-link" href="{{ route('contact_input') }}">お問い合わせ</a>
            </nav>
        </div>
    </header>
    <main class="mt-3">
        <h1>@yield('title')</h1>
        @yield('content')
    </main>
</div>
<script>
    $(function() {
        {{-- 暫定的なダブルサブミット対応 --}}
        $('.btn').on('click', function() {
            $('.btn').prop('disabled', true);
        });
    });
</script>



</body>
</html>
