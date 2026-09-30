@extends('layouts.admin')

@section('title',$article->exists ? 'Rediger artikel' : 'Ny artikel')

@section('content-header')
    <h1>{{ $article->exists ? 'Rediger artikel' : 'Ny artikel' }} <small>Vidensbase</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.knowledgebase') }}">Vidensbase</a></li>
        <li class="active">{{ $article->exists ? 'Rediger' : 'Ny artikel' }}</li>
    </ol>
@endsection

@section('content')
<style>
.kb-editor-toolbar{display:flex;gap:6px;flex-wrap:wrap;padding:10px;border:1px solid #d2d6de;border-bottom:0;border-radius:4px 4px 0 0;background:#f7f8fa}.kb-editor-toolbar .btn{min-width:34px}.kb-editor-toolbar .sep{width:1px;background:#d9dfe5;margin:2px 2px}.kb-editor-area{border-radius:0 0 4px 4px!important;resize:vertical;min-height:540px;font-family:Menlo,Monaco,Consolas,monospace!important;line-height:1.65!important}.kb-editor-help{margin-top:12px;border:1px solid #dfe5ea;border-radius:5px;background:#fbfcfd;padding:14px}.kb-editor-help code{background:#eef2f5;color:#40566b;padding:2px 5px;border-radius:4px}.kb-editor-help-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 18px;font-size:12px;color:#667}.kb-editor-uploading{opacity:.65;pointer-events:none}.kb-example{white-space:pre-wrap;background:#101820;color:#dce8f2;border-radius:5px;padding:12px;font:12px/1.6 Menlo,Monaco,Consolas,monospace;margin-top:10px}@media(max-width:700px){.kb-editor-help-grid{grid-template-columns:1fr}.kb-editor-toolbar .sep{display:none}}
</style>

<form method="POST" action="{{ $article->exists ? route('admin.knowledgebase.articles.update',$article) : route('admin.knowledgebase.articles.store') }}">
    @csrf
    @if($article->exists) @method('PATCH') @endif
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Artikelindhold</h3>
                    <span class="pull-right text-muted small">Rich article editor</span>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Titel</label>
                        <input class="form-control input-lg" name="title" required maxlength="180" value="{{ old('title',$article->title) }}" placeholder="Fx Aktivér commandblocks på din Minecraft server">
                    </div>

                    <div class="form-group">
                        <label>Kort beskrivelse</label>
                        <textarea class="form-control" name="summary" rows="2" maxlength="500" placeholder="En kort introduktion som vises i søgeresultater og øverst på artiklen.">{{ old('summary',$article->summary) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label>Indhold</label>
                        <div class="kb-editor-toolbar" id="kb-toolbar">
                            <button type="button" class="btn btn-default btn-xs" data-kb="h2" title="Stor overskrift"><strong>H2</strong></button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="h3" title="Mindre overskrift"><strong>H3</strong></button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="h4" title="Lille overskrift"><strong>H4</strong></button>
                            <span class="sep"></span>
                            <button type="button" class="btn btn-default btn-xs" data-kb="bold" title="Fed"><strong>B</strong></button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="italic" title="Kursiv"><em>I</em></button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="inline-code" title="Inline kode"><i class="fa fa-code"></i></button>
                            <span class="sep"></span>
                            <button type="button" class="btn btn-default btn-xs" data-kb="ul" title="Punktliste"><i class="fa fa-list-ul"></i></button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="ol" title="Nummereret liste"><i class="fa fa-list-ol"></i></button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="quote" title="Citat"><i class="fa fa-quote-left"></i></button>
                            <span class="sep"></span>
                            <button type="button" class="btn btn-default btn-xs" data-kb="link" title="Link"><i class="fa fa-link"></i></button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="code-block" title="Kodeblok"><i class="fa fa-terminal"></i></button>
                            <button type="button" class="btn btn-info btn-xs" data-kb="info" title="Informationsboks"><i class="fa fa-info-circle"></i> Info</button>
                            <button type="button" class="btn btn-warning btn-xs" data-kb="warning" title="Advarselsboks"><i class="fa fa-exclamation-triangle"></i> Bemærk</button>
                            <button type="button" class="btn btn-default btn-xs" data-kb="divider" title="Skillelinje">—</button>
                            <span class="sep"></span>
                            <button type="button" class="btn btn-success btn-xs" id="kb-image-button"><i class="fa fa-image"></i> Billede</button>
                            <input type="file" id="kb-image-input" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none">
                        </div>
                        <textarea class="form-control kb-editor-area" id="kb-content" name="content" rows="26" required maxlength="200000" placeholder="Skriv artiklen her...">{{ old('content',$article->content) }}</textarea>

                        <div class="kb-editor-help">
                            <strong><i class="fa fa-magic"></i> Artikelbygger</strong>
                            <p class="text-muted small" style="margin:5px 0 10px">Du kan bygge artikler med sektioner, trin-for-trin lister, kode, links, screenshots, infobokse og advarsler som i professionelle hosting-vidensbaser.</p>
                            <div class="kb-editor-help-grid">
                                <div><code>## Overskrift</code> — sektion</div>
                                <div><code>1. Trin</code> — nummereret guide</div>
                                <div><code>**Fed tekst**</code> — fremhævning</div>
                                <div><code>inline kode</code> via kode-knappen</div>
                                <div><code>[Linktekst](https://...)</code> — link</div>
                                <div><code>![Billede](...)</code> — screenshot</div>
                                <div><code>:::info Titel</code> — informationsboks</div>
                                <div><code>:::warning Titel</code> — advarsel</div>
                            </div>
                            <details style="margin-top:12px">
                                <summary style="cursor:pointer;font-weight:600">Vis eksempel på en guideartikel</summary>
                                <div class="kb-example">## Find din Minecraft-version

Klik på **Version Changer** under Minecraft Tools.

## Slå commandblocks til

1. Gå til Server Properties.
2. Find indstillingen `enable-command-block`.
3. Sæt værdien til `true`.
4. Genstart serveren.

:::info Tip
Ændringen kræver en genstart af serveren.
:::

![Server Properties](/storage/knowledgebase/eksempel.png "Her ændres serverens indstillinger")

---

## Virker det ikke?

- Kontrollér at serveren er genstartet.
- Kontrollér at du bruger den rigtige Minecraft-version.</div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Indstillinger</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Kategori</label>
                        <select class="form-control" name="category_id" required>
                            <option value="">Vælg kategori</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ (string) old('category_id',$article->category_id) === (string) $category->id ? 'selected' : '' }}>{{ $category->icon }} {{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label>Sortering</label><input class="form-control" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order',$article->sort_order ?? 0) }}"></div>
                    <div class="checkbox"><label><input type="hidden" name="published" value="0"><input type="checkbox" name="published" value="1" {{ old('published',$article->exists ? $article->published : true) ? 'checked' : '' }}> Publiceret</label></div>
                    <div class="checkbox"><label><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" {{ old('featured',$article->featured) ? 'checked' : '' }}> Udvalgt artikel</label></div>
                    @if($article->exists)
                    <hr>
                    <div class="text-muted small">Slug: <code>{{ $article->slug }}</code></div>
                    <div class="text-muted small" style="margin-top:7px">Visninger: {{ number_format($article->views,0,',','.') }}</div>
                    @if($article->published)<a class="btn btn-default btn-block" style="margin-top:14px" href="{{ route('knowledgebase.article',$article) }}" target="_blank"><i class="fa fa-external-link"></i> Se offentlig artikel</a>@endif
                    @endif
                </div>
                <div class="box-footer"><button class="btn btn-primary btn-block"><i class="fa fa-save"></i> {{ $article->exists ? 'Gem artikel' : 'Opret artikel' }}</button></div>
            </div>

            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Screenshots</h3></div>
                <div class="box-body">
                    <p class="text-muted small">Tryk på <strong>Billede</strong> i værktøjslinjen. PNG, JPG, WEBP og GIF op til 10 MB uploades automatisk og indsættes dér, hvor markøren står.</p>
                    <p class="text-muted small" style="margin-bottom:0">Du kan efter upload ændre teksten <code>"Billedtekst"</code> for at vise en billedtekst under screenshot'et.</p>
                </div>
            </div>

            @if($article->exists)
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title">Slet artikel</h3></div>
                <div class="box-body"><p class="text-muted">Artiklen fjernes permanent fra vidensbasen.</p></div>
                <div class="box-footer"><button type="submit" form="delete-kb-article" class="btn btn-danger btn-block" onclick="return confirm('Slet denne artikel permanent?')">Slet artikel</button></div>
            </div>
            @endif
        </div>
    </div>
</form>

@if($article->exists)
<form id="delete-kb-article" method="POST" action="{{ route('admin.knowledgebase.articles.delete',$article) }}">@csrf @method('DELETE')</form>
@endif

<script>
(function(){
    var editor=document.getElementById('kb-content');
    var toolbar=document.getElementById('kb-toolbar');
    var imageButton=document.getElementById('kb-image-button');
    var imageInput=document.getElementById('kb-image-input');
    if(!editor||!toolbar)return;

    function setText(text,start,end){
        var before=editor.value.slice(0,start);
        var after=editor.value.slice(end);
        editor.value=before+text+after;
        editor.focus();
        editor.setSelectionRange(start+text.length,start+text.length);
        editor.dispatchEvent(new Event('input',{bubbles:true}));
    }

    function selected(){
        return {
            start:editor.selectionStart,
            end:editor.selectionEnd,
            text:editor.value.slice(editor.selectionStart,editor.selectionEnd)
        };
    }

    function wrap(before,after,fallback){
        var s=selected();
        var value=s.text||fallback;
        var text=before+value+after;
        setText(text,s.start,s.end);
        editor.setSelectionRange(s.start+before.length,s.start+before.length+value.length);
    }

    function prefixLines(prefix,numbered){
        var s=selected();
        var value=s.text||'Nyt punkt';
        var lines=value.split(/\n/);
        var text=lines.map(function(line,index){
            return numbered?(index+1)+'. '+line:prefix+line;
        }).join('\n');
        setText(text,s.start,s.end);
    }

    function block(text){
        var s=selected();
        var before=editor.value.slice(0,s.start);
        var needsBefore=before.length&&!before.endsWith('\n')?'\n':'';
        setText(needsBefore+text,s.start,s.end);
    }

    toolbar.addEventListener('click',function(event){
        var button=event.target.closest('[data-kb]');
        if(!button)return;
        var action=button.getAttribute('data-kb');
        var tick=String.fromCharCode(96);

        if(action==='h2')prefixLines('## ',false);
        if(action==='h3')prefixLines('### ',false);
        if(action==='h4')prefixLines('#### ',false);
        if(action==='bold')wrap('**','**','fed tekst');
        if(action==='italic')wrap('*','*','kursiv tekst');
        if(action==='inline-code')wrap(tick,tick,'kommando');
        if(action==='ul')prefixLines('- ',false);
        if(action==='ol')prefixLines('',true);
        if(action==='quote')prefixLines('> ',false);
        if(action==='divider')block('\n---\n');
        if(action==='code-block'){
            var s=selected();
            block(tick+tick+tick+'\n'+(s.text||'kommando eller kode')+'\n'+tick+tick+tick+'\n');
        }
        if(action==='info'){
            var si=selected();
            block(':::info Tip\n'+(si.text||'Skriv nyttig information her.')+'\n:::\n');
        }
        if(action==='warning'){
            var sw=selected();
            block(':::warning Bemærk\n'+(sw.text||'Skriv vigtig information her.')+'\n:::\n');
        }
        if(action==='link'){
            var sl=selected();
            var link=window.prompt('Indsæt URL','https://');
            if(link)wrap('[',']('+link+')',sl.text||'Linktekst');
        }
    });

    imageButton.addEventListener('click',function(){
        imageInput.click();
    });

    imageInput.addEventListener('change',function(){
        if(!imageInput.files||!imageInput.files[0])return;

        var file=imageInput.files[0];
        var data=new FormData();
        data.append('image',file);

        imageButton.classList.add('kb-editor-uploading');
        imageButton.innerHTML='<i class="fa fa-spinner fa-spin"></i> Uploader...';

        fetch('{{ route('admin.knowledgebase.upload-image') }}',{
            method:'POST',
            headers:{
                'X-CSRF-TOKEN':'{{ csrf_token() }}',
                'Accept':'application/json'
            },
            body:data,
            credentials:'same-origin'
        })
        .then(function(response){
            if(!response.ok){
                return response.json().catch(function(){return {};}).then(function(body){
                    throw new Error(body.message||'Billedet kunne ikke uploades.');
                });
            }
            return response.json();
        })
        .then(function(result){
            var s=selected();
            var markdown='\n'+result.markdown+'\n';
            setText(markdown,s.start,s.end);
        })
        .catch(function(error){
            window.alert(error.message||'Billedet kunne ikke uploades.');
        })
        .finally(function(){
            imageInput.value='';
            imageButton.classList.remove('kb-editor-uploading');
            imageButton.innerHTML='<i class="fa fa-image"></i> Billede';
        });
    });
})();
</script>
@endsection