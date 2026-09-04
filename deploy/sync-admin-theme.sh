#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
WRAPPER="$PANEL_DIR/resources/views/templates/wrapper.blade.php"
ADMIN="$PANEL_DIR/resources/views/layouts/admin.blade.php"
PTERO_CSS="$PANEL_DIR/public/themes/pterodactyl/css/pterodactyl.css"

python3 - "$WRAPPER" "$ADMIN" "$PTERO_CSS" <<'PY'
from pathlib import Path
import re
import sys

wrapper = Path(sys.argv[1])
admin = Path(sys.argv[2])
css = Path(sys.argv[3])
include = "        @include('partials.nodexa-theme')\n"

# Every normal/auth/customer Blade page inherits from templates/wrapper. Load the
# same theme bootstrap before application scripts so there is no green flash while
# React starts, and so login/account pages inherit the selected accent too.
if wrapper.is_file():
    text = wrapper.read_text()
    text = re.sub(
        r'\s*<script id="nodexa-theme-cookie-sync">.*?</script>\s*',
        '\n',
        text,
        flags=re.S,
    )
    text = text.replace(include, '')
    needle = "        @include('layouts.scripts')"
    if needle in text:
        text = text.replace(needle, include + needle, 1)
    else:
        text = text.replace('</head>', include + '    </head>', 1)
    wrapper.write_text(text)

# The legacy AdminLTE area does not use the React theme component. Load the same
# central bootstrap here and then map every visual surface to the shared vars.
if admin.is_file():
    text = admin.read_text()
    text = text.replace(include, '')
    needle = "        @include('layouts.scripts')"
    if needle in text:
        text = text.replace(needle, include + needle, 1)
    else:
        text = text.replace('</head>', include + '    </head>', 1)

    # Remove an older isolated admin persistence bridge. The shared bootstrap is
    # now authoritative for customer, admin, auth and account pages.
    text = re.sub(
        r'\n\s*<script>\s*\(function \(\) \{\s*var STORAGE_KEY = \'nodexa_theme_accent\';.*?</script>\s*',
        '\n',
        text,
        flags=re.S,
    )

    marker = 'nodexa-global-admin-accent-overrides'
    if marker not in text:
        overrides = r'''
            <style id="nodexa-global-admin-accent-overrides">
                /* Nodexa global theme: AdminLTE uses the exact same selected
                   accent and dark color family as the customer/server area. */
                html,
                body,
                .skin-blue .wrapper,
                .content-wrapper {
                    background-color: var(--nodexa-bg) !important;
                }

                body {
                    background:
                        radial-gradient(circle at 12% -5%, rgba(var(--nodexa-accent-rgb), .14), transparent 30rem),
                        radial-gradient(circle at 92% 2%, rgba(var(--nodexa-accent-rgb), .06), transparent 25rem),
                        linear-gradient(180deg, var(--nodexa-bg-2), var(--nodexa-bg) 55%, var(--nodexa-bg-deep)) !important;
                    background-attachment: fixed !important;
                }

                .skin-blue .main-sidebar,
                .skin-blue .left-side {
                    background:
                        linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), .09), transparent 15rem),
                        var(--nodexa-bg-2) !important;
                    border-right: 1px solid var(--nodexa-border) !important;
                }

                .skin-blue .main-header .logo,
                .skin-blue .main-header .navbar {
                    background:
                        linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), .09), rgba(var(--nodexa-accent-rgb), .02)),
                        var(--nodexa-bg-2) !important;
                    border-bottom-color: var(--nodexa-border) !important;
                }

                .skin-blue .sidebar-menu > li.header {
                    color: rgba(var(--nodexa-accent-rgb), .72) !important;
                    background: rgba(var(--nodexa-accent-rgb), .025) !important;
                }

                .skin-blue .sidebar-menu > li > a,
                .skin-blue .treeview-menu > li > a,
                .skin-blue .main-header .navbar .nav > li > a,
                .skin-blue .main-header .navbar .sidebar-toggle {
                    color: var(--nodexa-muted) !important;
                }

                .skin-blue .sidebar-menu > li.active > a,
                .skin-blue .sidebar-menu > li:hover > a,
                .skin-blue .sidebar-menu > li.menu-open > a,
                .skin-blue .treeview-menu > li.active > a,
                .skin-blue .treeview-menu > li > a:hover {
                    color: #fff !important;
                    border-color: var(--nodexa-border) !important;
                    border-left-color: var(--nodexa-accent) !important;
                    background: linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), .16), rgba(var(--nodexa-accent-rgb), .035)) !important;
                }

                .skin-blue .sidebar-menu > li.active > a i,
                .skin-blue .sidebar-menu > li:hover > a i,
                .skin-blue .treeview-menu > li.active > a i,
                .skin-blue .main-header .logo:hover,
                .skin-blue .main-header .navbar .sidebar-toggle:hover {
                    color: var(--nodexa-accent-2) !important;
                }

                .content-wrapper {
                    background:
                        radial-gradient(circle at 14% 0%, rgba(var(--nodexa-accent-rgb), .09), transparent 28rem),
                        linear-gradient(180deg, rgba(var(--nodexa-accent-rgb), .025), transparent 22rem),
                        var(--nodexa-bg) !important;
                }

                .box,
                .panel,
                .well,
                .nav-tabs-custom,
                .modal-content,
                .dropdown-menu {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background:
                        linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), .055), rgba(var(--nodexa-accent-rgb), .012)),
                        var(--nodexa-surface) !important;
                }

                .box-header,
                .box-footer,
                .modal-header,
                .modal-body,
                .modal-footer,
                .nav-tabs-custom > .nav-tabs,
                .main-footer {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background: var(--nodexa-surface-2) !important;
                }

                .main-footer {
                    background:
                        linear-gradient(90deg, rgba(var(--nodexa-accent-rgb), .055), transparent),
                        var(--nodexa-bg-2) !important;
                }

                .form-control,
                input.form-control,
                textarea.form-control,
                .input-group-addon,
                .select2-selection,
                .select2-dropdown,
                .select2-search__field,
                pre,
                code {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border) !important;
                    background-color: var(--nodexa-surface-2) !important;
                }

                .form-control:focus,
                .select2-container--focus .select2-selection,
                .select2-container--open .select2-selection {
                    border-color: var(--nodexa-accent) !important;
                    box-shadow: 0 0 0 2px rgba(var(--nodexa-accent-rgb), .12) !important;
                }

                .table > thead > tr > th,
                .table > tbody > tr > th,
                .table > tfoot > tr > th,
                .table > thead > tr > td,
                .table > tbody > tr > td,
                .table > tfoot > tr > td {
                    border-color: var(--nodexa-border) !important;
                }

                .table-hover > tbody > tr:hover,
                .select2-results__option--highlighted[aria-selected] {
                    background: var(--nodexa-accent-soft) !important;
                }

                a,
                .content-header > .breadcrumb > li > a,
                .pagination > li > a,
                .pagination > li > span,
                .text-aqua,
                .text-light-blue {
                    color: var(--nodexa-accent-2) !important;
                }

                a:hover,
                a:focus {
                    color: var(--nodexa-accent) !important;
                }

                .skin-blue .sidebar-menu > li.active > a,
                .nav-tabs-custom > .nav-tabs > li.active,
                .nav-tabs-custom > .nav-tabs > li:hover {
                    border-color: var(--nodexa-accent) !important;
                }

                .btn-primary,
                .btn-success,
                .btn-info,
                .btn-green,
                .bg-aqua,
                .bg-light-blue,
                .label-primary,
                .pagination > .active > a,
                .pagination > .active > span,
                .pagination > .active > a:hover,
                .pagination > .active > span:hover {
                    color: #04100c !important;
                    border-color: var(--nodexa-accent-2) !important;
                    background: var(--nodexa-accent) !important;
                }

                .btn-primary:hover,
                .btn-success:hover,
                .btn-info:hover,
                .btn-green:hover {
                    color: #04100c !important;
                    border-color: var(--nodexa-accent-2) !important;
                    background: var(--nodexa-accent-2) !important;
                }

                .btn-default {
                    color: var(--nodexa-text) !important;
                    border-color: var(--nodexa-border-strong) !important;
                    background: var(--nodexa-surface-2) !important;
                }

                .btn-default:hover {
                    border-color: var(--nodexa-accent) !important;
                    background: var(--nodexa-accent-soft) !important;
                }

                .progress-bar-primary,
                .progress-bar-info,
                .progress-bar-success {
                    background-color: var(--nodexa-accent) !important;
                }

                /* Semantic danger/warning colors stay meaningful. */
                .btn-danger,
                .alert-danger,
                .label-danger,
                .text-danger {
                    --nodexa-semantic: #ef4444;
                }

                .btn-warning,
                .alert-warning,
                .label-warning,
                .text-warning {
                    --nodexa-semantic: #f59e0b;
                }
            </style>
'''
        needle = '            <!--[if lt IE 9]>'
        if needle in text:
            text = text.replace(needle, overrides + '\n' + needle, 1)
        else:
            first_show = text.find('        @show')
            if first_show >= 0:
                text = text[:first_show] + overrides + '\n' + text[first_show:]
            else:
                text = text.replace('</head>', overrides + '\n    </head>', 1)

    admin.write_text(text)

# The login/auth stylesheet is legacy CSS. Append a variable-driven layer so
# login and old Blade surfaces follow the same chosen theme as React and admin.
if css.is_file():
    text = css.read_text()
    marker = 'NODEXA GLOBAL THEME OVERRIDES'
    if marker not in text:
        text += r'''

/* NODEXA GLOBAL THEME OVERRIDES */
.login-page {
    color: var(--nodexa-text) !important;
    background:
        radial-gradient(circle at 15% 0%, rgba(var(--nodexa-accent-rgb), .18), transparent 30rem),
        radial-gradient(circle at 88% 12%, rgba(var(--nodexa-accent-rgb), .08), transparent 24rem),
        linear-gradient(180deg, var(--nodexa-bg-2), var(--nodexa-bg), var(--nodexa-bg-deep)) !important;
}

.pterodactyl-login-box {
    border: 1px solid var(--nodexa-border) !important;
    background: rgba(var(--nodexa-accent-rgb), .055) !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .35) !important;
}

.pterodactyl-login-input > input,
.pterodactyl-login-button--main,
.pterodactyl-login-button--left {
    color: var(--nodexa-text) !important;
    border-color: var(--nodexa-border-strong) !important;
    background: var(--nodexa-surface-2) !important;
}

.pterodactyl-login-input > input:focus,
.pterodactyl-login-button--main:hover,
.pterodactyl-login-button--left:hover {
    border-color: var(--nodexa-accent) !important;
    background: var(--nodexa-accent-soft) !important;
    box-shadow: 0 0 0 2px rgba(var(--nodexa-accent-rgb), .12) !important;
}

.login-logo,
.login-copyright > a {
    color: var(--nodexa-accent-2) !important;
}
'''
        css.write_text(text)
PY

echo "[Nodexa] Global theme synchronized across customer, server, admin, auth and legacy Blade UI."
