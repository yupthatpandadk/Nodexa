@extends('layouts.admin')

@section('title')
    Roles & Permissions
@endsection

@section('content-header')
    <h1>Roles & Permissions<small>Vælg en rolle for at se og redigere dens permissions.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Roles & Permissions</li>
    </ol>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-shield"></i> Vælg rolle</h3>
            </div>
            <div class="box-body">
                <p class="text-muted" style="margin-bottom:14px;">Tryk på en rolle for at vise dens permissions.</p>
                <div class="nodexa-role-grid">
                    @foreach($roles as $role)
                        <button type="button" class="nodexa-role-choice" data-role-select="{{ $role->id }}" style="--role-color: {{ $role->color }};">
                            <span class="nodexa-role-dot"></span>
                            <span class="nodexa-role-copy">
                                <strong>{{ $role->name }}</strong>
                                <small>{{ $role->is_system ? 'Standardrolle' : 'Brugerdefineret rolle' }} · {{ $role->user_count }} bruger{{ $role->user_count === 1 ? '' : 'e' }}</small>
                            </span>
                            <i class="fa fa-chevron-right"></i>
                        </button>
                    @endforeach

                    @if($canManage)
                        <button type="button" class="nodexa-role-choice nodexa-role-choice-new" data-role-select="new">
                            <span class="nodexa-role-dot"><i class="fa fa-plus"></i></span>
                            <span class="nodexa-role-copy">
                                <strong>Opret ny rolle</strong>
                                <small>Lav en ny rolle og vælg permissions</small>
                            </span>
                            <i class="fa fa-chevron-right"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($canManage)
    <div class="col-xs-12 nodexa-role-panel" data-role-panel="new" style="display:none;">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-plus-circle"></i> Opret ny rolle</h3>
            </div>
            <form action="{{ route('admin.roles.store') }}" method="POST">
                {!! csrf_field() !!}
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Navn</label>
                                <input type="text" name="name" class="form-control" placeholder="Fx Support, Moderator, Senior Admin" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Farve</label>
                                <input type="color" name="color" class="form-control" value="#42e9a6" style="height:40px;padding:4px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Beskrivelse</label>
                                <input type="text" name="description" class="form-control" placeholder="Kort beskrivelse af rollen">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <label>Permissions</label>
                            <div class="row">
                                @foreach($permissions as $permission => $label)
                                    <div class="col-md-6 col-lg-4">
                                        <label style="font-weight:400;display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" style="margin-top:3px;">
                                            <span><strong>{{ $label }}</strong><br><small class="text-muted">{{ $permission }}</small></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tildel brugere</label>
                                <select name="users[]" class="form-control" multiple size="12">
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->username }} — {{ $user->email }}{{ $user->root_admin ? ' (Root)' : '' }}</option>
                                    @endforeach
                                </select>
                                <p class="text-muted small">Hold Ctrl/Cmd nede for at vælge flere.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Opret rolle</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @forelse($roles as $role)
        <div class="col-xs-12 nodexa-role-panel" data-role-panel="{{ $role->id }}" style="display:none;">
            <div class="box" style="border-top:3px solid {{ $role->color }} !important;">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $role->color }};margin-right:7px;"></span>
                        {{ $role->name }}
                        @if($role->is_system)
                            <span class="label label-default" style="margin-left:6px;">Standard</span>
                        @endif
                    </h3>
                    <div class="box-tools">
                        <span class="label label-info">{{ $role->user_count }} bruger{{ $role->user_count === 1 ? '' : 'e' }}</span>
                    </div>
                </div>

                @if($canManage)
                <form action="{{ route('admin.roles.update', $role->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                @endif

                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Navn</label>
                                    <input type="text" name="name" class="form-control" value="{{ $role->name }}" {{ $canManage ? '' : 'disabled' }}>
                                </div>
                                <div class="form-group">
                                    <label>Farve</label>
                                    <input type="color" name="color" class="form-control" value="{{ $role->color }}" style="height:40px;padding:4px;" {{ $canManage ? '' : 'disabled' }}>
                                </div>
                                <div class="form-group">
                                    <label>Beskrivelse</label>
                                    <textarea name="description" class="form-control" rows="3" {{ $canManage ? '' : 'disabled' }}>{{ $role->description }}</textarea>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label>Permissions</label>
                                <div class="row">
                                    @foreach($permissions as $permission => $label)
                                        <div class="col-md-6">
                                            <label style="font-weight:400;display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" {{ in_array($permission, $role->permissions, true) ? 'checked' : '' }} {{ $canManage ? '' : 'disabled' }} style="margin-top:3px;">
                                                <span><strong>{{ $label }}</strong><br><small class="text-muted">{{ $permission }}</small></span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Brugere med rollen</label>
                                    <select name="users[]" class="form-control" multiple size="14" {{ $canManage ? '' : 'disabled' }}>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ in_array((int) $user->id, $role->user_ids, true) ? 'selected' : '' }}>
                                                {{ $user->username }} — {{ $user->email }}{{ $user->root_admin ? ' (Root)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($canManage)
                    <div class="box-footer" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Gem rolle</button>
                </form>
                        @if(!$role->is_system)
                            <form action="{{ route('admin.roles.delete', $role->id) }}" method="POST" onsubmit="return confirm('Slet rollen {{ addslashes($role->name) }}?');">
                                {!! csrf_field() !!}
                                {!! method_field('DELETE') !!}
                                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Slet rolle</button>
                            </form>
                        @endif
                    </div>
                    @endif
            </div>
        </div>
    @empty
        <div class="col-xs-12">
            <div class="alert alert-warning">Ingen roller fundet. Kontroller at Nodexa-migrationerne er kørt.</div>
        </div>
    @endforelse
</div>
@endsection

@section('footer-scripts')
    @parent
    <style>
        .nodexa-role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .nodexa-role-choice {
            width: 100%;
            min-height: 72px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--nodexa-border-strong, rgba(66,233,166,.25));
            border-radius: 12px;
            color: var(--nodexa-text, #eef7f5);
            background: rgba(255,255,255,.025);
            text-align: left;
            cursor: pointer;
            transition: .15s ease;
        }
        .nodexa-role-choice:hover,
        .nodexa-role-choice.active {
            border-color: var(--role-color, var(--nodexa-accent, #42e9a6));
            background: rgba(var(--nodexa-accent-rgb, 66,233,166), .10);
            transform: translateY(-1px);
        }
        .nodexa-role-dot {
            width: 14px;
            height: 14px;
            flex: 0 0 14px;
            border-radius: 50%;
            background: var(--role-color, var(--nodexa-accent, #42e9a6));
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .nodexa-role-choice-new .nodexa-role-dot {
            width: 28px;
            height: 28px;
            flex-basis: 28px;
            color: #061012;
        }
        .nodexa-role-copy {
            min-width: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .nodexa-role-copy strong {
            font-size: 15px;
        }
        .nodexa-role-copy small {
            color: var(--nodexa-muted, #8ba09c);
            line-height: 1.35;
        }
        .nodexa-role-choice > .fa-chevron-right {
            color: var(--nodexa-muted, #8ba09c);
        }
        @media (max-width: 767px) {
            .nodexa-role-grid { grid-template-columns: 1fr; }
            .nodexa-role-choice { min-height: 64px; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var choices = document.querySelectorAll('[data-role-select]');
            var panels = document.querySelectorAll('.nodexa-role-panel');

            choices.forEach(function (choice) {
                choice.addEventListener('click', function () {
                    var selected = String(choice.getAttribute('data-role-select') || '');

                    choices.forEach(function (item) {
                        item.classList.toggle('active', item === choice);
                    });

                    panels.forEach(function (panel) {
                        panel.style.display = panel.getAttribute('data-role-panel') === selected ? '' : 'none';
                    });

                    var activePanel = document.querySelector('.nodexa-role-panel[data-role-panel="' + selected + '"]');
                    if (activePanel) {
                        setTimeout(function () {
                            activePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 50);
                    }
                });
            });
        });
    </script>
@endsection
