@extends('templates/wrapper', [
    'css' => ['body' => 'bg-neutral-800'],
])

@section('container')
    <div id="modal-portal"></div>
    <div id="app"></div>
    <a href="{{ route('tickets.index') }}" id="nodexa-ticket-launcher" aria-label="Åbn support tickets">
        <span style="font-size:18px;line-height:1;">🎫</span>
        <span>Tickets</span>
    </a>
    <style>
        #nodexa-ticket-launcher {
            position: fixed;
            right: 18px;
            bottom: 76px;
            z-index: 10000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 14px;
            border: 1px solid rgba(66, 233, 166, .34);
            border-radius: 999px;
            color: #ecfffa;
            background: rgba(8, 24, 28, .94);
            box-shadow: 0 14px 34px rgba(0, 0, 0, .3);
            font: 600 14px/1 system-ui, sans-serif;
            text-decoration: none;
            backdrop-filter: blur(12px);
        }
        #nodexa-ticket-launcher:hover { border-color: #42e9a6; background: rgba(20, 55, 49, .96); }
        @media (max-width: 680px) {
            #nodexa-ticket-launcher { right: 12px; bottom: 82px; padding: 10px 12px; }
        }
    </style>
@endsection
