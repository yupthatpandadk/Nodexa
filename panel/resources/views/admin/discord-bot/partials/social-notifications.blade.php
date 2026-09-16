@php
    $parseSocialSources = static function ($value): array {
        if (is_array($value)) return array_values(array_filter(array_map('trim', $value)));
        $value = trim((string) $value);
        if ($value === '') return [];
        $json = json_decode($value, true);
        if (is_array($json)) return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $json)));
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    };
    $twitchSources = $parseSocialSources(old('twitch_channels', $config['twitch_channels'] ?? ''));
    $youtubeSources = $parseSocialSources(old('youtube_channels', $config['youtube_channels'] ?? ''));
@endphp
<style>
.social-source-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}.social-source-card{background:#071820;border:1px solid rgba(125,211,252,.11);border-radius:12px;padding:15px;min-width:0}.social-source-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.social-source-head strong{color:#eef8fa;font-size:13px}.social-source-head small{display:block;color:#78909a;font-size:10px;margin-top:3px}.social-add{border:1px solid rgba(88,101,242,.35);background:rgba(88,101,242,.12);color:#cbd0ff;border-radius:8px;padding:7px 10px;font-size:11px;white-space:nowrap}.social-list{display:flex;flex-direction:column;gap:8px}.social-row{display:flex;gap:8px;align-items:center}.social-row input{flex:1;min-width:0;background:#07151c;border:1px solid rgba(125,211,252,.14);border-radius:9px;color:#eef8fa;padding:10px 12px;outline:none}.social-remove{width:38px;height:38px;flex:none;border-radius:9px;border:1px solid rgba(248,113,113,.22);background:rgba(248,113,113,.08);color:#fca5a5}.social-empty{padding:13px;border:1px dashed rgba(125,211,252,.13);border-radius:9px;color:#708993;font-size:11px;text-align:center}.social-hidden{display:none!important}@media(max-width:700px){.social-source-grid{grid-template-columns:1fr}.social-source-head{align-items:flex-start}.social-add{padding:7px 9px}}
</style>
<div class="ms-section-title"><i class="fa fa-users"></i> Personer og kanaler</div>
<div class="social-source-grid">
    <div class="social-source-card" data-social-editor="twitch">
        <div class="social-source-head"><div><strong><i class="fa fa-twitch"></i> Twitch</strong><small>Tilføj alle streamere botten skal følge.</small></div><button type="button" class="social-add" data-social-add="twitch"><i class="fa fa-plus"></i> Tilføj person</button></div>
        <div class="social-list" data-social-list="twitch">
            @forelse($twitchSources as $source)<div class="social-row"><input type="text" value="{{ $source }}" placeholder="Twitch brugernavn eller kanal-URL"><button type="button" class="social-remove" title="Fjern"><i class="fa fa-trash"></i></button></div>@empty<div class="social-empty">Ingen Twitch-personer tilføjet endnu.</div>@endforelse
        </div>
        <input type="hidden" name="twitch_channels" id="field-twitch_channels" value="{{ implode("\n", $twitchSources) }}">
    </div>
    <div class="social-source-card" data-social-editor="youtube">
        <div class="social-source-head"><div><strong><i class="fa fa-youtube-play"></i> YouTube</strong><small>Tilføj alle creators/kanaler botten skal følge.</small></div><button type="button" class="social-add" data-social-add="youtube"><i class="fa fa-plus"></i> Tilføj person</button></div>
        <div class="social-list" data-social-list="youtube">
            @forelse($youtubeSources as $source)<div class="social-row"><input type="text" value="{{ $source }}" placeholder="YouTube @handle, kanal-ID eller URL"><button type="button" class="social-remove" title="Fjern"><i class="fa fa-trash"></i></button></div>@empty<div class="social-empty">Ingen YouTube-personer tilføjet endnu.</div>@endforelse
        </div>
        <input type="hidden" name="youtube_channels" id="field-youtube_channels" value="{{ implode("\n", $youtubeSources) }}">
    </div>
</div>
<script>
(function(){
 function list(type){return document.querySelector('[data-social-list="'+type+'"]');}
 function hidden(type){return document.getElementById('field-'+type+'_channels');}
 function sync(type){var l=list(type),h=hidden(type);if(!l||!h)return;var values=[];l.querySelectorAll('.social-row input').forEach(function(input){var v=input.value.trim();if(v&&!values.includes(v))values.push(v);});h.value=values.join('\n');var empty=l.querySelector('.social-empty');if(values.length&&empty)empty.remove();if(!l.querySelector('.social-row')&&!l.querySelector('.social-empty')){var e=document.createElement('div');e.className='social-empty';e.textContent=type==='twitch'?'Ingen Twitch-personer tilføjet endnu.':'Ingen YouTube-personer tilføjet endnu.';l.appendChild(e);}}
 function add(type){var l=list(type);if(!l)return;var empty=l.querySelector('.social-empty');if(empty)empty.remove();var row=document.createElement('div');row.className='social-row';var input=document.createElement('input');input.type='text';input.placeholder=type==='twitch'?'Twitch brugernavn eller kanal-URL':'YouTube @handle, kanal-ID eller URL';var remove=document.createElement('button');remove.type='button';remove.className='social-remove';remove.title='Fjern';remove.innerHTML='<i class="fa fa-trash"></i>';row.appendChild(input);row.appendChild(remove);l.appendChild(row);input.focus();}
 document.addEventListener('click',function(e){var addButton=e.target.closest('[data-social-add]');if(addButton){add(addButton.dataset.socialAdd);return;}var remove=e.target.closest('.social-remove');if(remove){var editor=remove.closest('[data-social-editor]');var type=editor.dataset.socialEditor;remove.closest('.social-row').remove();sync(type);}});
 document.addEventListener('input',function(e){var editor=e.target.closest&&e.target.closest('[data-social-editor]');if(editor)sync(editor.dataset.socialEditor);});
 var form=document.querySelector('[data-social-editor]')?.closest('form');if(form)form.addEventListener('submit',function(){sync('twitch');sync('youtube');});
})();
</script>