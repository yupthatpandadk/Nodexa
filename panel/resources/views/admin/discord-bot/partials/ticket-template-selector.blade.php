@php
$selectedTicketTemplates = json_decode((string)($config['ticket_templates'] ?? '[]'), true);
if (!is_array($selectedTicketTemplates)) $selectedTicketTemplates = [];
$ticketTemplates = [
 'support'=>['emoji'=>'🎫','name'=>'Support','description'=>'Generel hjælp og spørgsmål'],
 'billing'=>['emoji'=>'💳','name'=>'Betaling','description'=>'Faktura, køb og betaling'],
 'technical'=>['emoji'=>'🔧','name'=>'Teknisk problem','description'=>'Fejl, server og teknisk hjælp'],
 'report'=>['emoji'=>'🚨','name'=>'Spiller-rapport','description'=>'Rapportér en spiller eller hændelse'],
 'staff'=>['emoji'=>'👮','name'=>'Staff-klage','description'=>'Privat klage over staff'],
 'ban'=>['emoji'=>'🔨','name'=>'Ban Appeal','description'=>'Ansøg om unban'],
 'whitelist'=>['emoji'=>'✅','name'=>'Whitelist','description'=>'Whitelist-ansøgning og hjælp'],
 'partner'=>['emoji'=>'🤝','name'=>'Partnerskab','description'=>'Partner- og samarbejdsforespørgsler'],
 'other'=>['emoji'=>'📩','name'=>'Andet','description'=>'Fleksibel standardskabelon'],
];
@endphp
<style>
.ms-template.selected{border-color:#5865f2!important;background:rgba(88,101,242,.18)!important;box-shadow:inset 0 0 0 1px rgba(88,101,242,.35)}
.ms-template .ms-check{float:right;width:18px;height:18px;border-radius:50%;border:1px solid rgba(125,211,252,.25);display:flex;align-items:center;justify-content:center;font-size:10px;color:transparent}.ms-template.selected .ms-check{background:#5865f2;border-color:#5865f2;color:#fff}
.ms-selected-summary{margin-top:12px;padding:11px;border-radius:9px;background:rgba(88,101,242,.06);border:1px solid rgba(88,101,242,.13)}.ms-selected-summary b{font-size:10px;color:#aab2ff}.ms-selected-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.ms-selected-chip{padding:5px 8px;border-radius:7px;background:#0a1b24;border:1px solid rgba(125,211,252,.13);font-size:10px;color:#dbe7eb}.ms-selected-empty{font-size:10px;color:#78909a;margin-top:6px}
</style>
<input type="hidden" name="ticket_templates" id="ticket-templates-value" value="{{ json_encode(array_values($selectedTicketTemplates)) }}">
<div class="ms-section">
 <div class="ms-template-head"><div><div class="ms-section-title" style="margin:0"><i class="fa fa-magic"></i> Ticket Templates</div><p>Vælg én eller flere skabeloner. Alle valgte typer vises under den samme ticket-besked.</p></div></div>
 <div class="ms-template-grid">
 @foreach($ticketTemplates as $templateKey=>$template)
 <button type="button" class="ms-template ms-template-choice {{ in_array($templateKey,$selectedTicketTemplates,true)?'selected':'' }}" data-template="{{ $templateKey }}"><span class="ms-check"><i class="fa fa-check"></i></span><span class="emoji">{{ $template['emoji'] }}</span><b>{{ $template['name'] }}</b><small>{{ $template['description'] }}</small></button>
 @endforeach
 </div>
 <div class="ms-selected-summary"><b>VALGTE TICKET-KATEGORIER</b><div id="ticket-template-chips" class="ms-selected-chips"></div><div id="ticket-template-empty" class="ms-selected-empty">Ingen templates valgt endnu.</div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 var input=document.getElementById('ticket-templates-value'); if(!input)return;
 var selected=[]; try{selected=JSON.parse(input.value||'[]');}catch(e){selected=[];} if(!Array.isArray(selected))selected=[];
 var names={support:'🎫 Support',billing:'💳 Betaling',technical:'🔧 Teknisk problem',report:'🚨 Spiller-rapport',staff:'👮 Staff-klage',ban:'🔨 Ban Appeal',whitelist:'✅ Whitelist',partner:'🤝 Partnerskab',other:'📩 Andet'};
 function render(){input.value=JSON.stringify(selected);document.querySelectorAll('.ms-template-choice').forEach(function(b){b.classList.toggle('selected',selected.indexOf(b.dataset.template)!==-1);});var chips=document.getElementById('ticket-template-chips'),empty=document.getElementById('ticket-template-empty');chips.innerHTML='';selected.forEach(function(k){var c=document.createElement('span');c.className='ms-selected-chip';c.textContent=names[k]||k;chips.appendChild(c);});empty.style.display=selected.length?'none':'block';}
 document.querySelectorAll('.ms-template-choice').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var k=b.dataset.template,i=selected.indexOf(k);if(i===-1)selected.push(k);else selected.splice(i,1);render();});});
 render();
});
</script>
