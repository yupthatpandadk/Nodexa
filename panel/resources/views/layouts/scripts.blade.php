{{-- Nodexa admin theme layer. Kept separate from upstream AdminLTE/Pterodactyl assets. --}}
<style>
    body.skin-blue {
        background: #050d0b !important;
        color: #dcefe7 !important;
    }

    body.skin-blue .wrapper,
    body.skin-blue .content-wrapper,
    body.skin-blue .right-side {
        background: #07110f !important;
    }

    body.skin-blue .main-header {
        border-bottom: 1px solid rgba(73, 238, 169, .12) !important;
        box-shadow: 0 14px 36px rgba(0, 0, 0, .22) !important;
    }

    body.skin-blue .main-header .logo,
    body.skin-blue .main-header .navbar {
        background: #071511 !important;
        color: #effbf6 !important;
    }

    body.skin-blue .main-header .logo {
        font-weight: 700 !important;
        letter-spacing: -.02em !important;
        border-right: 1px solid rgba(73, 238, 169, .1) !important;
    }

    body.skin-blue .main-header .logo:hover,
    body.skin-blue .main-header .navbar .sidebar-toggle:hover,
    body.skin-blue .main-header .navbar .nav > li > a:hover,
    body.skin-blue .main-header .navbar .nav > li > a:focus {
        background: rgba(66, 233, 166, .075) !important;
        color: #5cf0b2 !important;
    }

    body.skin-blue .main-sidebar {
        background: #081410 !important;
        border-right: 1px solid rgba(73, 238, 169, .1) !important;
        box-shadow: 14px 0 38px rgba(0, 0, 0, .12) !important;
    }

    body.skin-blue .sidebar-menu > li.header {
        padding: 18px 18px 7px !important;
        background: transparent !important;
        color: #527c6d !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        letter-spacing: .13em !important;
    }

    body.skin-blue .sidebar-menu > li > a {
        margin: 3px 10px !important;
        padding: 11px 13px !important;
        border: 1px solid transparent !important;
        border-left: 0 !important;
        border-radius: 11px !important;
        color: #8ca69c !important;
        transition: all .15s ease !important;
    }

    body.skin-blue .sidebar-menu > li > a .fa,
    body.skin-blue .sidebar-menu > li > a .ion {
        color: #668a7d !important;
    }

    body.skin-blue .sidebar-menu > li:hover > a,
    body.skin-blue .sidebar-menu > li.active > a {
        background: rgba(66, 233, 166, .075) !important;
        border-color: rgba(73, 238, 169, .14) !important;
        color: #eafff6 !important;
    }

    body.skin-blue .sidebar-menu > li.active > a .fa,
    body.skin-blue .sidebar-menu > li:hover > a .fa {
        color: #49eaa9 !important;
    }

    body.skin-blue .content-header {
        padding: 24px 22px 10px !important;
    }

    body.skin-blue .content-header > h1,
    body.skin-blue .content-header > h1 > small {
        color: #ecfff7 !important;
    }

    body.skin-blue .content {
        padding: 18px 22px 30px !important;
    }

    body.skin-blue .box,
    body.skin-blue .nav-tabs-custom,
    body.skin-blue .callout,
    body.skin-blue .info-box,
    body.skin-blue .small-box {
        border: 1px solid rgba(73, 238, 169, .105) !important;
        border-top: 1px solid rgba(73, 238, 169, .105) !important;
        border-radius: 14px !important;
        background: linear-gradient(145deg, #0f211b, #0a1713) !important;
        color: #dcefe7 !important;
        box-shadow: 0 16px 42px rgba(0, 0, 0, .18) !important;
        overflow: hidden !important;
    }

    body.skin-blue .box-header,
    body.skin-blue .box-footer,
    body.skin-blue .nav-tabs-custom > .nav-tabs,
    body.skin-blue .nav-tabs-custom > .tab-content {
        background: transparent !important;
        border-color: rgba(73, 238, 169, .08) !important;
        color: #eafff6 !important;
    }

    body.skin-blue .box-title,
    body.skin-blue label,
    body.skin-blue .control-label {
        color: #dff6ed !important;
    }

    body.skin-blue .table > thead > tr > th,
    body.skin-blue .table > tbody > tr > td,
    body.skin-blue .table > tbody > tr > th,
    body.skin-blue .table > tfoot > tr > td {
        border-color: rgba(119, 160, 145, .13) !important;
        color: #cfe4dc !important;
    }

    body.skin-blue .table-hover > tbody > tr:hover {
        background: rgba(66, 233, 166, .04) !important;
    }

    body.skin-blue .form-control,
    body.skin-blue .select2-container--default .select2-selection--single,
    body.skin-blue .select2-container--default .select2-selection--multiple,
    body.skin-blue textarea {
        border: 1px solid rgba(122, 161, 146, .24) !important;
        border-radius: 10px !important;
        background: #0a1915 !important;
        color: #e8faf3 !important;
        box-shadow: none !important;
    }

    body.skin-blue .form-control:focus,
    body.skin-blue .select2-container--default.select2-container--focus .select2-selection--multiple,
    body.skin-blue .select2-container--default.select2-container--open .select2-selection--single {
        border-color: rgba(66, 233, 166, .68) !important;
        box-shadow: 0 0 0 3px rgba(66, 233, 166, .09) !important;
    }

    body.skin-blue .btn-primary,
    body.skin-blue .btn-success {
        border-color: #29bf84 !important;
        border-radius: 10px !important;
        background: linear-gradient(135deg, #5be9af, #2bd895) !important;
        color: #062018 !important;
        font-weight: 600 !important;
        box-shadow: 0 9px 24px rgba(43, 216, 149, .15) !important;
    }

    body.skin-blue .btn-primary:hover,
    body.skin-blue .btn-success:hover {
        border-color: #5cf0b4 !important;
        background: linear-gradient(135deg, #6ef0bb, #37e0a0) !important;
    }

    body.skin-blue .btn-default {
        border-color: rgba(126, 160, 148, .22) !important;
        border-radius: 10px !important;
        background: #10231d !important;
        color: #d9eee6 !important;
    }

    body.skin-blue .pagination > .active > a,
    body.skin-blue .pagination > .active > span {
        border-color: #34d799 !important;
        background: #34d799 !important;
        color: #06150f !important;
    }

    body.skin-blue .pagination > li > a,
    body.skin-blue .pagination > li > span {
        border-color: rgba(126, 160, 148, .18) !important;
        background: #0c1c17 !important;
        color: #91b0a4 !important;
    }

    body.skin-blue .main-footer {
        border-top: 1px solid rgba(73, 238, 169, .09) !important;
        background: #07110f !important;
        color: #66887b !important;
    }

    body.skin-blue .main-footer a {
        color: #45dca0 !important;
    }

    body.skin-blue .text-muted,
    body.skin-blue .text-gray,
    body.skin-blue small,
    body.skin-blue .help-block {
        color: #718f84 !important;
    }

    body.skin-blue a {
        color: #43dca0;
    }

    body.skin-blue a:hover {
        color: #72f1bf;
    }

    body.skin-blue .alert-info {
        border-color: rgba(56, 189, 248, .22) !important;
        background: rgba(56, 189, 248, .08) !important;
        color: #bcecff !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var menu = document.querySelector('.sidebar-menu');
        if (!menu || document.getElementById('nodexa-updates-menu-item')) return;

        var item = document.createElement('li');
        item.id = 'nodexa-updates-menu-item';
        item.className = @json(request()->routeIs('admin.updates*') ? 'active' : '');
        item.innerHTML = '<a href="' + @json(route('admin.updates')) + '"><i class="fa fa-cloud-download"></i> <span>Opdateringer</span></a>';

        var managementHeader = Array.prototype.find.call(menu.querySelectorAll('li.header'), function (header) {
            return header.textContent.trim().toUpperCase() === 'MANAGEMENT';
        });

        if (managementHeader) {
            menu.insertBefore(item, managementHeader);
        } else {
            menu.appendChild(item);
        }
    });
</script>
