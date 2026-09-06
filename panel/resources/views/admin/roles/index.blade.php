@extends('layouts.admin')

@section('title')
    Roles & Permissions
@endsection

@section('content-header')
    <h1>Roles & Permissions<small>Opret roller og styr præcist hvad staff kan tilgå.</small></h1>
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
    @if($canManage)
    <div class="col-xs-12">
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
        <div class="col-xs-12">
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
