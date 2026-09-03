@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Configuration
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Nodexa Agent configuration.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Configuration</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Node Configuration</h3>
            </div>
            <div class="box-body">
                <pre class="no-margin">{{ $node->getYamlConfiguration() }}</pre>
            </div>
            <div class="box-footer">
                <p class="no-margin">Nodexa Agent runtime settings are created automatically by Auto-Deploy and stored in <code>/etc/nodexa.env</code> on the Node. You do not need to create <code>/etc/pterodactyl/config.yml</code> manually.</p>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Nodexa Auto-Deploy</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">
                    Generate a Node token and copy the complete Nodexa Agent installation command with one click.
                </p>
            </div>
            <div class="box-footer">
                <button type="button" id="configTokenBtn" class="btn btn-sm btn-success" style="width:100%;">
                    <i class="fa fa-key"></i> Generate Configuration Token
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    (function () {
        function escapeHtml(value) {
            return $('<div/>').text(value == null ? '' : String(value)).html();
        }

        function fallbackCopy(element) {
            element.focus();
            element.select();
            if (element.setSelectionRange) {
                element.setSelectionRange(0, element.value.length);
            }
            return document.execCommand('copy');
        }

        function copyValue(element, button) {
            var value = element.value;
            var original = button.html();

            function copied() {
                button.removeClass('btn-default btn-primary').addClass('btn-success').html('<i class="fa fa-check"></i> Copied');
                setTimeout(function () {
                    button.removeClass('btn-success').addClass('btn-primary').html(original);
                }, 1800);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(copied).catch(function () {
                    if (fallbackCopy(element)) copied();
                });
            } else if (fallbackCopy(element)) {
                copied();
            }
        }

        $('#configTokenBtn').on('click', function () {
            var button = $(this);
            button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

            $.ajax({
                method: 'POST',
                url: '{{ route('admin.nodes.view.configuration.token', $node->id) }}',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            }).done(function (data) {
                var token = String(data.token || '');
                var command = "curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/pterodactyl-core/install.sh -o /tmp/nodexa-install.sh && NODEXA_BRANCH=pterodactyl-core NODEXA_AGENT_TOKEN='" + token + "' NODEXA_PANEL_URL='{{ config('app.url') }}' bash /tmp/nodexa-install.sh node";

                var html = '' +
                    '<div style="text-align:left;margin-top:8px;">' +
                        '<p style="margin-bottom:6px;font-weight:600;color:#53606d;">Nodexa Node token</p>' +
                        '<textarea id="nodexaNodeToken" readonly rows="2" spellcheck="false" style="width:100%;resize:none;box-sizing:border-box;padding:10px 12px;border:1px solid #bcc6ce;border-radius:6px;background:#18222d;color:#e9fff6;font-family:monospace;font-size:13px;line-height:1.45;word-break:break-all;">' + escapeHtml(token) + '</textarea>' +
                        '<button type="button" id="copyNodexaNodeToken" class="btn btn-primary" style="width:100%;margin-top:8px;"><i class="fa fa-copy"></i> Copy token</button>' +
                        '<p style="margin:18px 0 6px;font-weight:600;color:#53606d;">Nodexa Agent auto-deploy command</p>' +
                        '<textarea id="nodexaNodeCommand" readonly rows="6" spellcheck="false" style="width:100%;resize:vertical;box-sizing:border-box;padding:10px 12px;border:1px solid #bcc6ce;border-radius:6px;background:#18222d;color:#e9fff6;font-family:monospace;font-size:12px;line-height:1.5;overflow:auto;">' + escapeHtml(command) + '</textarea>' +
                        '<button type="button" id="copyNodexaNodeCommand" class="btn btn-primary" style="width:100%;margin-top:8px;"><i class="fa fa-terminal"></i> Copy full command</button>' +
                        '<p style="margin-top:12px;margin-bottom:0;font-size:11px;color:#82909c;"><i class="fa fa-shield"></i> Run this command as root on the Node server. Nodexa will create <code>/etc/nodexa.env</code> automatically.</p>' +
                    '</div>';

                swal({
                    type: 'success',
                    title: 'Nodexa Node token created',
                    text: html,
                    html: true,
                    confirmButtonText: 'Done'
                });

                $('#nodexaNodeToken, #nodexaNodeCommand').on('click', function () {
                    this.focus();
                    this.select();
                });

                $('#copyNodexaNodeToken').on('click', function () {
                    copyValue(document.getElementById('nodexaNodeToken'), $(this));
                });

                $('#copyNodexaNodeCommand').on('click', function () {
                    copyValue(document.getElementById('nodexaNodeCommand'), $(this));
                });
            }).fail(function () {
                swal({
                    title: 'Error',
                    text: 'Something went wrong creating your token.',
                    type: 'error'
                });
            }).always(function () {
                button.prop('disabled', false).html('<i class="fa fa-key"></i> Generate Configuration Token');
            });
        });
    })();
    </script>
@endsection
