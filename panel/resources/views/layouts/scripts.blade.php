{{-- Nodexa global theme bootstrap. This is shared by customer/auth/admin Blade layouts. --}}
@include('partials.nodexa-theme')

<script>
    (function () {
        function installLegacyThemeLayer() {
            if (document.getElementById('nodexa-global-legacy-theme')) return;

            var style = document.createElement('style');
            style.id = 'nodexa-global-legacy-theme';
            style.textContent = `
                body.skin-blue,
                body.skin-blue .wrapper,
                body.skin-blue .content-wrapper,
                body.skin-blue .right-side {
                    color: var(--nodexa-text) !important;
                    background-color: var(--nodexa-bg) !important;
                }

                body.skin-blue {
                    background:
                        radial-gradient(circle at 12% -5%, rgba(var(--nodexa-accent-rgb), .14), transparent 31rem),
                        radial-gradient(circle at 92% 3%, rgba(var(--nodexa-accent-rgb), .06), transparent 25rem),
                        linear-gradient(180deg, var(--nodexa-bg-2), var(--nodexa-bg) 56%, var(--nodexa-bg-deep)) !important;
                    background-attachment: fixed !important;
                }

                body.skin-blue .main-header {
                    border-bottom: 1px solid var(--nodexa-border) !important;
                    box-shadow: 0 14px 36px rgba(0, 0, 0, .22) !important;
                }

                body.skin-blue .main-header .logo,
                body.skin-blue .main-header .navbar {
                    color: var(--nodexa-text) !important;
                    background:
                        linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), .09), rgba(var(--nodexa-accent-rgb), .02)),
                        var(--nodexa-bg-2) !important;
                }

                body.skin-blue .main-header .logo {
                    font-weight: 700 !important;
                    letter-spacing: -.02em !important;
                    border-right: 1px solid var(--nodexa-border) !important;
                }

                body.skin-blue .main-header .logo:hover,
                body.skin-blue .main-header .navbar .sidebar-toggle:hover,
                body.skin-blue .main-header .navbar .nav > li > a:hover,
                body.skin-blue .main-header .navbar .nav > li > a:focus {
                    color: var(--nodexa-accent-2) !important;
                    background: var(--nodexa-accent-soft) !important;
                }

                body.skin-blue .main-header .navbar .sidebar-toggle,
                body.skin-blue .main-header .navbar .nav > li > a {
                    color: var(--nodexa-muted) !important;
                }

                body.skin-blue .main-sidebar,
                body.skin-blue .left-side {
                    background:
                        linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), .09), transparent 16rem),
                        var(--nodexa-bg-2) !important;
                    border-right: 1px solid var(--nodexa-border) !important;
                    box-shadow: 14px 0 38px rgba(0, 0, 0, .14) !important;
                }

                body.skin-blue .sidebar-menu > li.header {
                    padding: 18px 18px 7px !important;
                    color: rgba(var(--nodexa-accent-rgb), .68) !important;
                    background: transparent !important;
                    font-size: 10px !important;
                    font-weight: 700 !important;
                    letter-spacing: .13em !important;
                }

                body.skin-blue .sidebar-menu > li > a,
                body.skin-blue .treeview-menu > li > a {
                    color: var(--nodexa-muted) !important;
                }

                body.skin-blue .sidebar-menu > li > a {
                    margin: 3px 10px !important;
                    padding: 11px 13px !important;
                    border: 1px solid transparent !important;
                    border-radius: 11px !important;
                    transition: all .15s ease !important;
                }

                body.skin-blue .sidebar-menu > li > a .fa,
                body.skin-blue .sidebar-menu > li > a .ion {
                    color: var(--nodexa-muted) !important;
                }

                body.skin-blue .sidebar-menu > li:hover > a,
                body.skin-blue .sidebar-menu > li.active > a,
                body.skin-blue .sidebar-menu > li.menu-open > a,
                body.skin-blue .treeview-menu > li.active > a,
                body.skin-blue .treeview-menu > li > a:hover {
                    color: #fff !important;
                    border-color: var(--nodexa-border) !important;
                    border-left-color: var(--nodexa-accent) !important;
                    background: linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), .16), rgba(var(--nodexa-accent-rgb), .035)) !important;
                }

                body.skin-blue .sidebar-menu > li.active > a .fa,
                body.skin-blue .sidebar-menu > li:hover > a .fa,
                body.skin-blue .treeview-menu > li.active > a .fa {
                    color: var(--nodexa-accent) !important;
                }

                body.skin-blue .content-wrapper {
                    background:
                        radial-gradient(circle at 15% 0%, rgba(var(--nodexa-accent-rgb), .09), transparent 28rem),
                        linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), .025), transparent 22rem),
                        var(--nodexa-bg) !important;
                }

                body.skin-blue .content-header {
                    padding: 24px 22px 10px !important;
                }

                body.skin-blue .content-header > h1,
                body.skin-blue .content-header > h1 > small,
                body.skin-blue .content-header > .breadcrumb > li,
                body.skin-blue .content-header > .breadcrumb > li > a,
                body.skin-blue .breadcrumb > .active {
                    color: var(--nodexa-text) !important;
                }

                body.skin-blue .content-header > .breadcrumb {
                    border: 1px solid var(--nodexa-border) !important;
                    border-radius: 8px !important;
                    background: rgba(var(--nodexa-accent-rgb), .055) !important;
                }

                body.skin-blue .content {
                    padding: 18px 22px 30px !important;
                }

                body.skin-blue .box,
                body.skin-blue .panel,
                body.skin-blue .well,
                body.skin-blue .nav-tabs-custom,
                body.skin-blue .callout,
                body.skin-blue .info-box,
                body.skin-blue .small-box,
                body.skin-blue .modal-content,
                body.skin-blue .dropdown-menu {
                    color: var(--nodexa-text) !important;
                    border: 1px solid var(--nodexa-border) !important;
                    border-top-color: var(--nodexa-border-strong) !important;
                    border-radius: 14px !important;
                    background:
                        linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), .055), rgba(var(--nodexa-accent-rgb), .012)),
                        var(--nodexa-surface) !important;
                    box-shadow: 0 16px 42px rgba(0, 0, 0, .18) !important;
                }

                body.skin-blue .box-header,
                body.skin-blue .box-footer,
                body.skin-blue .panel-heading,
                body.skin-blue .panel-footer,
                body.skin-blue .nav-tabs-custom > .nav-tabs,
                body.skin-blue .nav-tabs-custom > .tab-content,
                body.skin-blue .modal-header,
                body.skin-blue .modal-body,
                body.skin-blue .modal-footer {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background: var(--nodexa-surface-2) !important;
                }

                body.skin-blue .box-title,
                body.skin-blue label,
                body.skin-blue .control-label {
                    color: var(--nodexa-text) !important;
                }

                body.skin-blue .table > thead > tr > th,
                body.skin-blue .table > tbody > tr > td,
                body.skin-blue .table > tbody > tr > th,
                body.skin-blue .table > tfoot > tr > td {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border) !important;
                }

                body.skin-blue .table-hover > tbody > tr:hover,
                body.skin-blue tr:hover + tr.server-description {
                    background: var(--nodexa-accent-soft) !important;
                }

                body.skin-blue .form-control,
                body.skin-blue input.form-control,
                body.skin-blue textarea.form-control,
                body.skin-blue textarea,
                body.skin-blue .input-group-addon,
                body.skin-blue .select2-container--default .select2-selection--single,
                body.skin-blue .select2-container--default .select2-selection--multiple,
                body.skin-blue .select2-dropdown,
                body.skin-blue .select2-search__field,
                body.skin-blue pre,
                body.skin-blue code {
                    color: var(--nodexa-text) !important;
                    border: 1px solid var(--nodexa-border-strong) !important;
                    border-radius: 10px !important;
                    background: var(--nodexa-surface-2) !important;
                    box-shadow: none !important;
                }

                body.skin-blue .form-control:focus,
                body.skin-blue input.form-control:focus,
                body.skin-blue textarea.form-control:focus,
                body.skin-blue .select2-container--default.select2-container--focus .select2-selection--multiple,
                body.skin-blue .select2-container--default.select2-container--open .select2-selection--single {
                    border-color: var(--nodexa-accent) !important;
                    box-shadow: 0 0 0 3px rgba(var(--nodexa-accent-rgb), .11) !important;
                }

                body.skin-blue .select2-results__option,
                body.skin-blue .select2-container--default .select2-results__option[aria-selected=true] {
                    color: var(--nodexa-text) !important;
                    background: var(--nodexa-surface) !important;
                }

                body.skin-blue .select2-container--default .select2-results__option--highlighted[aria-selected] {
                    color: #fff !important;
                    background: rgba(var(--nodexa-accent-rgb), .24) !important;
                }

                body.skin-blue .nav-tabs-custom > .nav-tabs > li:hover,
                body.skin-blue .nav-tabs-custom > .nav-tabs > li.active {
                    border-top-color: var(--nodexa-accent) !important;
                }

                body.skin-blue .nav-tabs-custom > .nav-tabs > li > a {
                    color: var(--nodexa-muted) !important;
                }

                body.skin-blue .nav-tabs-custom > .nav-tabs > li.active > a,
                body.skin-blue .nav-tabs-custom > .nav-tabs > li.active:hover > a {
                    color: var(--nodexa-accent-2) !important;
                    border-color: var(--nodexa-border) !important;
                    background: var(--nodexa-accent-soft) !important;
                }

                body.skin-blue .btn-primary,
                body.skin-blue .btn-success,
                body.skin-blue .btn-info,
                body.skin-blue .btn-green,
                body.skin-blue .pagination > .active > a,
                body.skin-blue .pagination > .active > span {
                    color: #04100c !important;
                    border-color: var(--nodexa-accent-2) !important;
                    border-radius: 10px !important;
                    background: var(--nodexa-accent) !important;
                    font-weight: 600 !important;
                    box-shadow: 0 9px 24px rgba(var(--nodexa-accent-rgb), .16) !important;
                }

                body.skin-blue .btn-primary:hover,
                body.skin-blue .btn-success:hover,
                body.skin-blue .btn-info:hover,
                body.skin-blue .btn-green:hover {
                    color: #04100c !important;
                    border-color: var(--nodexa-accent-2) !important;
                    background: var(--nodexa-accent-2) !important;
                }

                body.skin-blue .btn-default,
                body.skin-blue .pagination > li > a,
                body.skin-blue .pagination > li > span {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border-strong) !important;
                    border-radius: 10px !important;
                    background: var(--nodexa-surface-2) !important;
                }

                body.skin-blue .btn-default:hover,
                body.skin-blue .pagination > li > a:hover {
                    border-color: var(--nodexa-accent) !important;
                    background: var(--nodexa-accent-soft) !important;
                }

                body.skin-blue .progress-bar-primary,
                body.skin-blue .progress-bar-info,
                body.skin-blue .progress-bar-success {
                    background-color: var(--nodexa-accent) !important;
                }

                body.skin-blue .main-footer {
                    color: var(--nodexa-muted) !important;
                    border-top: 1px solid var(--nodexa-border) !important;
                    background:
                        linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), .055), transparent),
                        var(--nodexa-bg-2) !important;
                }

                body.skin-blue .main-footer a,
                body.skin-blue a,
                body.skin-blue .small-box-footer,
                body.skin-blue .text-primary,
                body.skin-blue .text-info,
                body.skin-blue .text-aqua,
                body.skin-blue .text-light-blue {
                    color: var(--nodexa-accent-2) !important;
                }

                body.skin-blue a:hover,
                body.skin-blue a:focus,
                body.skin-blue .main-footer a:hover {
                    color: var(--nodexa-accent) !important;
                }

                body.skin-blue .text-muted,
                body.skin-blue .text-gray,
                body.skin-blue small,
                body.skin-blue .help-block {
                    color: var(--nodexa-muted) !important;
                }

                body.login-page {
                    color: var(--nodexa-text) !important;
                    background:
                        radial-gradient(circle at 15% 0%, rgba(var(--nodexa-accent-rgb), .18), transparent 30rem),
                        radial-gradient(circle at 88% 12%, rgba(var(--nodexa-accent-rgb), .08), transparent 24rem),
                        linear-gradient(180deg, var(--nodexa-bg-2), var(--nodexa-bg), var(--nodexa-bg-deep)) !important;
                }

                body.login-page .pterodactyl-login-box {
                    border: 1px solid var(--nodexa-border) !important;
                    background: rgba(var(--nodexa-accent-rgb), .055) !important;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, .35) !important;
                }

                body.login-page .pterodactyl-login-input > input,
                body.login-page .pterodactyl-login-button--main,
                body.login-page .pterodactyl-login-button--left {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border-strong) !important;
                    background: var(--nodexa-surface-2) !important;
                }

                body.login-page .login-logo,
                body.login-page .login-copyright > a {
                    color: var(--nodexa-accent-2) !important;
                }
            `;
            document.head.appendChild(style);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', installLegacyThemeLayer, { once: true });
        } else {
            installLegacyThemeLayer();
        }
    })();
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var menu = document.querySelector('.sidebar-menu');
        if (!menu) return;

        if (!document.getElementById('nodexa-updates-menu-item')) {
            var updateItem = document.createElement('li');
            updateItem.id = 'nodexa-updates-menu-item';
            updateItem.className = @json(request()->routeIs('admin.updates*') ? 'active' : '');
            updateItem.innerHTML = '<a href="' + @json(route('admin.updates')) + '"><i class="fa fa-cloud-download"></i> <span>Opdateringer</span></a>';

            var managementHeader = Array.prototype.find.call(menu.querySelectorAll('li.header'), function (header) {
                return header.textContent.trim().toUpperCase() === 'MANAGEMENT';
            });

            if (managementHeader) menu.insertBefore(updateItem, managementHeader);
            else menu.appendChild(updateItem);
        }

        @if(Auth::check() && (Auth::user()->root_admin || \Pterodactyl\Support\NodexaPermissions::userHas(Auth::user(), 'admin.roles.view')))
            if (!document.getElementById('nodexa-roles-menu-item')) {
                var roleItem = document.createElement('li');
                roleItem.id = 'nodexa-roles-menu-item';
                roleItem.className = @json(request()->routeIs('admin.roles*') ? 'active' : '');
                roleItem.innerHTML = '<a href="' + @json(route('admin.roles')) + '"><i class="fa fa-shield"></i> <span>Roles & Permissions</span></a>';

                var usersLink = Array.prototype.find.call(menu.querySelectorAll('a'), function (link) {
                    return link.getAttribute('href') === @json(route('admin.users'));
                });
                if (usersLink && usersLink.parentNode) {
                    usersLink.parentNode.insertAdjacentElement('afterend', roleItem);
                } else {
                    menu.appendChild(roleItem);
                }
            }
        @endif
    });
</script>