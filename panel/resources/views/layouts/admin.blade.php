<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="manifest" href="/favicons/manifest.json">
        <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
        <link rel="shortcut icon" href="/favicons/favicon.ico">
        <meta name="msapplication-config" content="/favicons/browserconfig.xml">
        <meta name="theme-color" content="#42e9a6" id="nodexa-browser-theme-color">

        <script>
            (function () {
                var STORAGE_KEY = 'nodexa_theme_accent';
                var DEFAULT_ACCENT = '#42e9a6';

                function normalize(value) {
                    return /^#[0-9a-fA-F]{6}$/.test(String(value || '').trim()) ? String(value).toLowerCase() : DEFAULT_ACCENT;
                }

                function rgb(hex) {
                    return [
                        parseInt(hex.slice(1, 3), 16),
                        parseInt(hex.slice(3, 5), 16),
                        parseInt(hex.slice(5, 7), 16)
                    ];
                }

                function mixWhite(hex, amount) {
                    var values = rgb(hex);
                    return '#' + values.map(function (value) {
                        return Math.round(value + (255 - value) * amount).toString(16).padStart(2, '0');
                    }).join('');
                }

                window.applyNodexaAdminAccent = function (value) {
                    var accent = normalize(value);
                    var values = rgb(accent);
                    var root = document.documentElement;
                    root.style.setProperty('--nodexa-accent', accent);
                    root.style.setProperty('--nodexa-accent-2', mixWhite(accent, 0.2));
                    root.style.setProperty('--nodexa-accent-rgb', values.join(', '));
                    root.style.setProperty('--nodexa-accent-soft', 'rgba(' + values.join(', ') + ', 0.12)');
                    root.style.setProperty('--nodexa-border', 'rgba(' + values.join(', ') + ', 0.14)');
                    root.style.setProperty('--nodexa-border-strong', 'rgba(' + values.join(', ') + ', 0.32)');
                    root.style.setProperty('--nodexa-surface-glow', 'rgba(' + values.join(', ') + ', 0.065)');
                    root.dataset.nodexaAccent = accent;

                    var themeMeta = document.getElementById('nodexa-browser-theme-color');
                    if (themeMeta) themeMeta.setAttribute('content', accent);
                    return accent;
                };

                var saved = DEFAULT_ACCENT;
                try { saved = localStorage.getItem(STORAGE_KEY) || DEFAULT_ACCENT; } catch (_) {}
                window.applyNodexaAdminAccent(saved);

                window.addEventListener('storage', function (event) {
                    if (event.key === STORAGE_KEY) window.applyNodexaAdminAccent(event.newValue || DEFAULT_ACCENT);
                });
            })();
        </script>

        @include('layouts.scripts')

        @section('scripts')
            {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">

            <style id="nodexa-admin-theme">
                :root {
                    --nodexa-accent: #42e9a6;
                    --nodexa-accent-2: #68edb8;
                    --nodexa-accent-rgb: 66, 233, 166;
                    --nodexa-accent-soft: rgba(66, 233, 166, 0.12);
                    --nodexa-border: rgba(66, 233, 166, 0.14);
                    --nodexa-border-strong: rgba(66, 233, 166, 0.32);
                    --nodexa-surface-glow: rgba(66, 233, 166, 0.065);
                    --nodexa-admin-bg: #050b0d;
                    --nodexa-admin-surface: #0a1417;
                    --nodexa-admin-surface-2: #0e1a1e;
                    --nodexa-admin-text: #edf7f5;
                    --nodexa-admin-muted: #8ba09c;
                }

                html,
                body,
                .skin-blue .wrapper {
                    background: var(--nodexa-admin-bg) !important;
                }

                body {
                    color: var(--nodexa-admin-text) !important;
                    background:
                        radial-gradient(circle at 12% -6%, rgba(var(--nodexa-accent-rgb), 0.13), transparent 32rem),
                        radial-gradient(circle at 90% 4%, rgba(var(--nodexa-accent-rgb), 0.055), transparent 28rem),
                        linear-gradient(180deg, #071012 0%, #050b0d 60%, #04090b 100%) !important;
                    background-attachment: fixed !important;
                }

                .skin-blue .main-sidebar,
                .skin-blue .left-side {
                    background:
                        linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), 0.075), transparent 15rem),
                        #061012 !important;
                    border-right: 1px solid var(--nodexa-border) !important;
                    box-shadow: 14px 0 42px rgba(0, 0, 0, 0.18) !important;
                }

                .skin-blue .main-header .logo,
                .skin-blue .main-header .navbar {
                    background:
                        linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), 0.075), rgba(var(--nodexa-accent-rgb), 0.018)),
                        #071214 !important;
                    border-bottom: 1px solid var(--nodexa-border) !important;
                }

                .skin-blue .main-header .logo {
                    color: #f4fbf9 !important;
                    font-weight: 700 !important;
                }

                .skin-blue .main-header .logo:hover,
                .skin-blue .main-header .navbar .sidebar-toggle:hover,
                .skin-blue .main-header .navbar .nav > li > a:hover,
                .skin-blue .main-header .navbar .nav > li > a:focus {
                    color: var(--nodexa-accent-2) !important;
                    background: var(--nodexa-accent-soft) !important;
                }

                .skin-blue .main-header .navbar .sidebar-toggle,
                .skin-blue .main-header .navbar .nav > li > a {
                    color: var(--nodexa-admin-muted) !important;
                }

                .skin-blue .sidebar-menu > li.header {
                    color: rgba(var(--nodexa-accent-rgb), 0.62) !important;
                    background: rgba(var(--nodexa-accent-rgb), 0.025) !important;
                    letter-spacing: 0.13em;
                }

                .skin-blue .sidebar a,
                .skin-blue .sidebar-menu > li > a,
                .skin-blue .treeview-menu > li > a {
                    color: var(--nodexa-admin-muted) !important;
                }

                .skin-blue .sidebar-menu > li > a {
                    margin: 2px 8px;
                    border: 1px solid transparent;
                    border-radius: 8px;
                }

                .skin-blue .sidebar-menu > li:hover > a,
                .skin-blue .sidebar-menu > li.active > a,
                .skin-blue .sidebar-menu > li.menu-open > a,
                .skin-blue .treeview-menu > li.active > a,
                .skin-blue .treeview-menu > li > a:hover {
                    color: #f4fbf9 !important;
                    border-color: var(--nodexa-border) !important;
                    border-left-color: var(--nodexa-accent) !important;
                    background: linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), 0.14), rgba(var(--nodexa-accent-rgb), 0.035)) !important;
                }

                .skin-blue .sidebar-menu > li.active > a > i,
                .skin-blue .sidebar-menu > li:hover > a > i,
                .skin-blue .treeview-menu > li.active > a > i {
                    color: var(--nodexa-accent) !important;
                }

                .skin-blue .sidebar-menu > li > .treeview-menu {
                    background: rgba(0, 0, 0, 0.18) !important;
                }

                .content-wrapper {
                    min-height: calc(100vh - 50px) !important;
                    background:
                        radial-gradient(circle at 15% 0%, rgba(var(--nodexa-accent-rgb), 0.075), transparent 28rem),
                        linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), 0.018), transparent 20rem),
                        #050c0e !important;
                }

                .content-header > h1,
                .content-header > h1 > small,
                .content-header > .breadcrumb > li,
                .content-header > .breadcrumb > li > a,
                .breadcrumb > .active {
                    color: var(--nodexa-admin-text) !important;
                }

                .content-header > .breadcrumb {
                    background: rgba(var(--nodexa-accent-rgb), 0.055) !important;
                    border: 1px solid var(--nodexa-border) !important;
                    border-radius: 7px !important;
                }

                .box,
                .panel,
                .well,
                .nav-tabs-custom {
                    color: var(--nodexa-admin-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background:
                        linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), 0.055), rgba(var(--nodexa-accent-rgb), 0.012)),
                        var(--nodexa-admin-surface) !important;
                    box-shadow: 0 14px 38px rgba(0, 0, 0, 0.18), inset 0 1px rgba(255, 255, 255, 0.015) !important;
                }

                .box,
                .box.box-default,
                .box.box-primary,
                .box.box-success,
                .box.box-info {
                    border-top-color: var(--nodexa-border-strong) !important;
                }

                .box-header,
                .box-footer,
                .panel-heading,
                .panel-footer {
                    color: var(--nodexa-admin-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background: rgba(var(--nodexa-accent-rgb), 0.045) !important;
                }

                .box-header.with-border {
                    border-bottom-color: var(--nodexa-border) !important;
                }

                .main-footer {
                    color: var(--nodexa-admin-muted) !important;
                    border-top: 1px solid var(--nodexa-border) !important;
                    background: #061012 !important;
                }

                .main-footer a,
                a,
                .small-box-footer {
                    color: var(--nodexa-accent-2) !important;
                }

                a:hover,
                a:focus,
                .main-footer a:hover {
                    color: var(--nodexa-accent) !important;
                }

                .text-primary,
                .text-info {
                    color: var(--nodexa-accent-2) !important;
                }

                .table > thead > tr > th,
                .table > tbody > tr > th,
                .table > tfoot > tr > th,
                .table > thead > tr > td,
                .table > tbody > tr > td,
                .table > tfoot > tr > td {
                    border-color: rgba(var(--nodexa-accent-rgb), 0.11) !important;
                }

                .table-hover > tbody > tr:hover,
                tr:hover + tr.server-description {
                    background: rgba(var(--nodexa-accent-rgb), 0.065) !important;
                }

                .nav-tabs-custom > .nav-tabs {
                    border-bottom-color: var(--nodexa-border) !important;
                }

                .nav-tabs-custom > .nav-tabs > li:hover,
                .nav-tabs-custom > .nav-tabs > li.active {
                    border-top-color: var(--nodexa-accent) !important;
                }

                .nav-tabs-custom > .nav-tabs > li > a,
                .nav-tabs-custom > .nav-tabs > li.active > a,
                .nav-tabs-custom > .nav-tabs > li.active:hover > a {
                    color: var(--nodexa-admin-muted) !important;
                    border-left-color: var(--nodexa-border) !important;
                    border-right-color: var(--nodexa-border) !important;
                    background: transparent !important;
                }

                .nav-tabs-custom > .nav-tabs > li.active > a,
                .nav-tabs-custom > .nav-tabs > li.active:hover > a {
                    color: var(--nodexa-accent-2) !important;
                    background: rgba(var(--nodexa-accent-rgb), 0.075) !important;
                }

                .form-control,
                input.form-control,
                textarea.form-control,
                .input-group .input-group-addon,
                .select2-container--default .select2-selection--single,
                .select2-container--default .select2-selection--multiple,
                .select2-dropdown,
                pre,
                code {
                    color: var(--nodexa-admin-text) !important;
                    border-color: rgba(var(--nodexa-accent-rgb), 0.19) !important;
                    background: rgba(var(--nodexa-accent-rgb), 0.045) !important;
                }

                .form-control:focus,
                input.form-control:focus,
                textarea.form-control:focus,
                .select2-container--default.select2-container--focus .select2-selection--multiple,
                .select2-container--default .select2-search--dropdown .select2-search__field {
                    border-color: var(--nodexa-accent) !important;
                    box-shadow: 0 0 0 3px rgba(var(--nodexa-accent-rgb), 0.1) !important;
                }

                .select2-container--default .select2-selection--single .select2-selection__rendered,
                .select2-container--default .select2-selection--multiple .select2-selection__choice {
                    color: var(--nodexa-admin-text) !important;
                }

                .select2-results__option,
                .select2-container--default .select2-results__option[aria-selected=true] {
                    color: var(--nodexa-admin-text) !important;
                    background: #0b1518 !important;
                }

                .select2-container--default .select2-results__option--highlighted[aria-selected] {
                    color: #ffffff !important;
                    background: rgba(var(--nodexa-accent-rgb), 0.24) !important;
                }

                .btn-primary,
                .btn-success,
                .btn-info,
                .btn.active:not(.btn-danger):not(.btn-warning) {
                    color: #04110e !important;
                    border-color: var(--nodexa-accent) !important;
                    background: linear-gradient(135deg, var(--nodexa-accent-2), var(--nodexa-accent)) !important;
                    box-shadow: 0 8px 24px rgba(var(--nodexa-accent-rgb), 0.16) !important;
                }

                .btn-primary:hover,
                .btn-primary:focus,
                .btn-success:hover,
                .btn-success:focus,
                .btn-info:hover,
                .btn-info:focus {
                    color: #03100d !important;
                    border-color: var(--nodexa-accent-2) !important;
                    filter: brightness(1.06);
                }

                .btn-default {
                    color: var(--nodexa-admin-text) !important;
                    border-color: var(--nodexa-border-strong) !important;
                    background: rgba(var(--nodexa-accent-rgb), 0.045) !important;
                }

                .btn-default:hover,
                .btn-default:focus {
                    color: #ffffff !important;
                    border-color: var(--nodexa-accent) !important;
                    background: var(--nodexa-accent-soft) !important;
                }

                .pagination > .active > a,
                .pagination > .active > span,
                .pagination > .active > a:hover,
                .pagination > .active > span:hover,
                .label-primary,
                .bg-blue,
                .bg-light-blue,
                .progress-bar-primary,
                .progress-bar-info {
                    color: #04110e !important;
                    border-color: var(--nodexa-accent) !important;
                    background-color: var(--nodexa-accent) !important;
                }

                .pagination > li > a,
                .pagination > li > span {
                    color: var(--nodexa-admin-muted) !important;
                    border-color: var(--nodexa-border) !important;
                    background: rgba(var(--nodexa-accent-rgb), 0.035) !important;
                }

                .modal-content,
                .modal-header,
                .modal-body,
                .modal-footer {
                    color: var(--nodexa-admin-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background: var(--nodexa-admin-surface) !important;
                }

                .dropdown-menu {
                    color: var(--nodexa-admin-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background: #091316 !important;
                }

                .dropdown-menu > li > a {
                    color: var(--nodexa-admin-muted) !important;
                }

                .dropdown-menu > li > a:hover,
                .dropdown-menu > li > a:focus {
                    color: #ffffff !important;
                    background: var(--nodexa-accent-soft) !important;
                }

                ::selection {
                    color: #ffffff;
                    background: rgba(var(--nodexa-accent-rgb), 0.32);
                }

                ::-webkit-scrollbar {
                    width: 10px;
                    height: 10px;
                    background: transparent;
                }

                ::-webkit-scrollbar-thumb {
                    border: 2px solid transparent;
                    border-radius: 999px;
                    background: rgba(var(--nodexa-accent-rgb), 0.4);
                    background-clip: padding-box;
                }

                @media (max-width: 991px) {
                    .content-header > .breadcrumb {
                        background: rgba(var(--nodexa-accent-rgb), 0.055) !important;
                    }
                }
            </style>

            <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
            <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
            <![endif]-->
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini">
        <div class="wrapper">
            <header class="main-header">
                <a href="{{ route('index') }}" class="logo">
                    <span>{{ config('app.name', 'Pterodactyl') }}</span>
                </a>
                <nav class="navbar navbar-static-top">
                    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>
                    <div class="navbar-custom-menu">
                        <ul class="nav navbar-nav">
                            <li class="user-menu">
                                <a href="{{ route('account') }}">
                                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(Auth::user()->email)) }}?s=160" class="user-image" alt="User Image">
                                    <span class="hidden-xs">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                                </a>
                            </li>
                            <li>
                                <li><a href="{{ route('index') }}" data-toggle="tooltip" data-placement="bottom" title="Exit Admin Control"><i class="fa fa-server"></i></a></li>
                            </li>
                            <li>
                                <li><a href="{{ route('auth.logout') }}" id="logoutButton" data-toggle="tooltip" data-placement="bottom" title="Logout"><i class="fa fa-sign-out"></i></a></li>
                            </li>
                        </ul>
                    </div>
                </nav>
            </header>
            <aside class="main-sidebar">
                <section class="sidebar">
                    <ul class="sidebar-menu">
                        <li class="header">BASIC ADMINISTRATION</li>
                        <li class="{{ Route::currentRouteName() !== 'admin.index' ?: 'active' }}">
                            <a href="{{ route('admin.index') }}">
                                <i class="fa fa-home"></i> <span>Overview</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.settings') ?: 'active' }}">
                            <a href="{{ route('admin.settings')}}">
                                <i class="fa fa-wrench"></i> <span>Settings</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.api') ?: 'active' }}">
                            <a href="{{ route('admin.api.index')}}">
                                <i class="fa fa-gamepad"></i> <span>Application API</span>
                            </a>
                        </li>
                        <li class="header">MANAGEMENT</li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.databases') ?: 'active' }}">
                            <a href="{{ route('admin.databases') }}">
                                <i class="fa fa-database"></i> <span>Databases</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.locations') ?: 'active' }}">
                            <a href="{{ route('admin.locations') }}">
                                <i class="fa fa-globe"></i> <span>Locations</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nodes') ?: 'active' }}">
                            <a href="{{ route('admin.nodes') }}">
                                <i class="fa fa-sitemap"></i> <span>Nodes</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.servers') ?: 'active' }}">
                            <a href="{{ route('admin.servers') }}">
                                <i class="fa fa-server"></i> <span>Servers</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.users') ?: 'active' }}">
                            <a href="{{ route('admin.users') }}">
                                <i class="fa fa-users"></i> <span>Users</span>
                            </a>
                        </li>
                        <li class="header">SERVICE MANAGEMENT</li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.mounts') ?: 'active' }}">
                            <a href="{{ route('admin.mounts') }}">
                                <i class="fa fa-magic"></i> <span>Mounts</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nests') ?: 'active' }}">
                            <a href="{{ route('admin.nests') }}">
                                <i class="fa fa-th-large"></i> <span>Nests</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </aside>
            <div class="content-wrapper">
                <section class="content-header">
                    @yield('content-header')
                </section>
                <section class="content">
                    <div class="row">
                        <div class="col-xs-12">
                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    There was an error validating the data provided.<br><br>
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @foreach (Alert::getMessages() as $type => $messages)
                                @foreach ($messages as $message)
                                    <div class="alert alert-{{ $type }} alert-dismissable" role="alert">
                                        {{ $message }}
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    @yield('content')
                </section>
            </div>
            <footer class="main-footer">
                <div class="pull-right small text-gray" style="margin-right:10px;margin-top:-7px;">
                    <strong><i class="fa fa-fw {{ $appIsGit ? 'fa-git-square' : 'fa-code-fork' }}"></i></strong> {{ $appVersion }}<br />
                    <strong><i class="fa fa-fw fa-clock-o"></i></strong> {{ round(microtime(true) - LARAVEL_START, 3) }}s
                </div>
                Copyright &copy; 2015 - {{ date('Y') }} <a href="https://pterodactyl.io/">Pterodactyl Software</a>.
            </footer>
        </div>
        @section('footer-scripts')
            <script src="/js/keyboard.polyfill.js" type="application/javascript"></script>
            <script>keyboardeventKeyPolyfill.polyfill();</script>

            {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/sweetalert/sweetalert.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/slimscroll/jquery.slimscroll.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/adminlte/app.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap-notify/bootstrap-notify.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/select2/select2.full.min.js?t={cache-version}') !!}
            {!! Theme::js('js/admin/functions.js?t={cache-version}') !!}
            <script src="/js/autocomplete.js" type="application/javascript"></script>

            @if(Auth::user()->root_admin)
                <script>
                    $('#logoutButton').on('click', function (event) {
                        event.preventDefault();

                        var that = this;
                        swal({
                            title: 'Do you want to log out?',
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d9534f',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Log out'
                        }, function () {
                             $.ajax({
                                type: 'POST',
                                url: '{{ route('auth.logout') }}',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },complete: function () {
                                    window.location.href = '{{route('auth.login')}}';
                                }
                        });
                    });
                });
                </script>
            @endif

            <script>
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip();
                })
            </script>
        @show
    </body>
</html>
