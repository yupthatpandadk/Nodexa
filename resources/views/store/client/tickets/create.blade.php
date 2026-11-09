@extends('store.layout')
@section('title', 'Opret supportticket')
@section('content')
@include('store.client.tickets.alerts')
<div class="container tickets"><a class="ticketBack" href="{{ route('store.client.tickets') }}">← Mine tickets</a><div class="ticketHead"><div><div class="eyebrow">Client Area / Support</div><h1>Hvordan kan vi hjælpe?</h1><p class="muted">Vælg afdeling, og beskriv din henvendelse.</p></div></div>
<form class="card" method="POST" enctype="multipart/form-data" action="{{ route('store.client.tickets.store') }}">@csrf
<label class="formLabel" for="ticket-subject">Emne</label><input class="input" id="ticket-subject" name="subject" required maxlength="160" value="{{ old('subject', $vpsTicketSubject ?? '') }}">
<div id="ticket-suggestions" class="ticketNotice suggestedArticles" hidden aria-live="polite"></div>
<div class="ticketFields"><div><label class="formLabel" for="department">Afdeling</label><select class="input" id="department" name="department" required><option value="">Vælg afdeling…</option>@foreach($departments as $d)<option value="{{ $d->key }}" @selected(old('department') === $d->key)>{{ $d->name }}</option>@endforeach</select></div><div><label class="formLabel" for="priority">Prioritet</label><select class="input" id="priority" name="priority">@foreach(['low'=>'Lav','normal'=>'Normal','high'=>'Høj','urgent'=>'Haster'] as $key=>$label)<option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>@endforeach</select></div><div><label class="formLabel" for="server">Server</label><select class="input" id="server" name="server_id"><option value="">Ingen specifik server</option>@foreach($servers as $s)<option value="{{ $s->id }}" @selected((string)old('server_id') === (string)$s->id)>{{ $s->name }}</option>@endforeach</select></div></div>
<label class="formLabel" for="message">Besked</label><textarea class="textarea" id="message" name="message" required minlength="10" maxlength="20000" placeholder="Hvad sker der, og hvad har du allerede forsøgt?">{{ old('message', $vpsTicketMessage ?? '') }}</textarea>
<label class="formLabel" for="attachments">Vedhæft filer</label><input id="attachments" type="file" name="attachments[]" multiple accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.txt,.log,.zip"><p class="micro">Op til 10 filer, højst 10 MB pr. fil. Billeder, PDF, tekst/log og ZIP.</p>
<button class="btn primary" style="margin-top:20px" @disabled($departments->isEmpty())>Opret supportticket</button>
</form></div>
@include('store.client.tickets.styles')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const subject = document.getElementById('ticket-subject'), box = document.getElementById('ticket-suggestions');
    let timer, pending;
    subject.addEventListener('input', function () {
        clearTimeout(timer); if (pending) pending.abort(); box.hidden = true;
        const term = subject.value.trim(); if (term.length < 3) return;
        timer = setTimeout(async function () {
            pending = new AbortController();
            try {
                const response = await fetch(@json(route('store.client.tickets.suggestions')) + '?q=' + encodeURIComponent(term), {headers:{Accept:'application/json'}, signal:pending.signal});
                if (!response.ok) return;
                const articles = await response.json(); if (subject.value.trim() !== term) return;
                box.replaceChildren(); if (!articles.length) return;
                const title = document.createElement('strong'); title.textContent = 'Måske finder du svaret her'; box.append(title);
                articles.forEach(function (article) { const a = document.createElement('a'); a.href = article.url; a.textContent = article.title; a.target = '_blank'; a.rel = 'noopener'; box.append(a); }); box.hidden = false;
            } catch (error) { if (error.name !== 'AbortError') box.hidden = true; }
        }, 400);
    });
});
</script>
@endsection
