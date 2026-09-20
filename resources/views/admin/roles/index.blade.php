@extends('layouts.admin')

@section('title', 'Roles & Permissions')

@section('content-header')
    <h1>Roles & Permissions <small>Styr hvem der kan hvad i Nodexa.</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Opret rolle</h3></div>
            <form method="POST" action="{{ route('admin.roles.store') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group"><label>Navn</label><input class="form-control" name="name" required placeholder="Fx Support"></div>
                    <div class="form-group"><label>Beskrivelse</label><input class="form-control" name="description" placeholder="Hvad bruges rollen til?"></div>
                    <div class="form-group"><label>Farve</label><input class="form-control" type="color" name="color" value="#60a5fa"></div>
                    @foreach($permissions as $group => $items)
                        <div class="form-group">
                            <strong>{{ $group }}</strong><br>
                            @foreach($items as $permission)
                                <label class="checkbox-inline" style="margin-left:0;margin-right:14px">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}"> {{ str_ends_with($permission, '.view') ? 'Se' : 'Administrer' }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="box-footer"><button class="btn btn-primary">Opret rolle</button></div>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        @foreach($roles as $role)
            <div class="box">
                <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                    @csrf @method('PATCH')
                    <div class="box-header with-border">
                        <h3 class="box-title"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $role->color }}"></span> {{ $role->name }} <small>{{ $role->users_count }} bruger(e)</small></h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-sm-5"><div class="form-group"><label>Navn</label><input class="form-control" name="name" value="{{ $role->name }}" required></div></div>
                            <div class="col-sm-5"><div class="form-group"><label>Beskrivelse</label><input class="form-control" name="description" value="{{ $role->description }}"></div></div>
                            <div class="col-sm-2"><div class="form-group"><label>Farve</label><input class="form-control" type="color" name="color" value="{{ $role->color }}"></div></div>
                        </div>
                        @foreach($permissions as $group => $items)
                            <div style="padding:8px 0;border-top:1px solid rgba(128,128,128,.15)">
                                <strong style="display:inline-block;min-width:150px">{{ $group }}</strong>
                                @foreach($items as $permission)
                                    <label class="checkbox-inline" style="margin-left:0;margin-right:14px">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission }}" {{ $role->hasPermission($permission) ? 'checked' : '' }}> {{ str_ends_with($permission, '.view') ? 'Se' : 'Administrer' }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="box-footer">
                        <button class="btn btn-primary btn-sm">Gem</button>
                        <button type="submit" form="delete-role-{{ $role->id }}" class="btn btn-danger btn-sm pull-right" onclick="return confirm('Slet rollen {{ $role->name }}?')">Slet</button>
                    </div>
                </form>
                <form id="delete-role-{{ $role->id }}" method="POST" action="{{ route('admin.roles.delete', $role) }}">@csrf @method('DELETE')</form>
            </div>
        @endforeach
    </div>
</div>
@endsection
