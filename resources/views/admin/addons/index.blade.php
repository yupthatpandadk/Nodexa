@extends('layouts.admin')

@section('title', 'Addon Manager')

@section('content-header')
    <h1>Addon Manager <small>Administrer Nodexa-funktioner som kan aktiveres på kundernes servere.</small></h1>
@endsection

@section('content')
<div class="row">
    @foreach($addons as $name => $addon)
        @php($meta = $definitions[$name])
        <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
            <a href="{{ route('admin.addons.show', $addon->id) }}" style="display:block;text-decoration:none;color:inherit">
                <div class="box" style="border:1px solid #26384d;border-radius:14px;overflow:hidden;min-height:230px;transition:.2s ease">
                    <div class="box-body" style="padding:24px">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:15px">
                            <div style="width:52px;height:52px;border-radius:12px;background:{{ $meta['background'] }};display:flex;align-items:center;justify-content:center;font-size:24px;color:{{ $meta['color'] }}">
                                <i class="fa {{ $meta['icon'] }}"></i>
                            </div>
                            {!! $addon->enabled ? '<span class="label label-success">Aktiv</span>' : '<span class="label label-default">Deaktiveret</span>' !!}
                        </div>
                        <h3 style="margin:22px 0 7px;font-size:18px;color:#fff">{{ $meta['title'] }}</h3>
                        <p class="text-muted" style="line-height:1.6;margin-bottom:18px;min-height:42px">{{ $meta['description'] }}</p>
                        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #26384d;padding-top:15px">
                            <span class="text-muted"><i class="fa fa-cube"></i> Minecraft</span>
                            <span style="color:{{ $meta['color'] }};font-weight:600">Administrer <i class="fa fa-chevron-right" style="font-size:10px;margin-left:5px"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
