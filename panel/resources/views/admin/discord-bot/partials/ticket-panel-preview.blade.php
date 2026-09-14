@php
$previewSelected = json_decode((string)($config['ticket_templates'] ?? '[]'), true);
if (!is_array($previewSelected)) $previewSelected = [];
@endphp
@if(count($previewSelected))
<div class="ms-section" id="ticket-panel-preview"><div class="ms-section-title"><i class="fa fa-eye"></i> Panel preview</div><div class="ms-hint" style="margin-bottom:10px">Disse ticket-typer bliver samlet under den samme ticket-besked.</div><div class="ms-selected-chips">@foreach($previewSelected as $item)<span class="ms-selected-chip">{{ ['support'=>'🎫 Support','billing'=>'💳 Betaling','technical'=>'🔧 Teknisk problem','report'=>'🚨 Spiller-rapport','staff'=>'👮 Staff-klage','ban'=>'🔨 Ban Appeal','whitelist'=>'✅ Whitelist','partner'=>'🤝 Partnerskab','other'=>'📩 Andet'][$item] ?? $item }}</span>@endforeach</div></div>
@endif
