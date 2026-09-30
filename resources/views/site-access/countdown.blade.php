<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#050b14">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} — Nodexa</title>
    <style>
        :root{--bg:#050b14;--panel:#091725;--line:#19324c;--text:#f4f8ff;--muted:#89a0ba;--blue:#3d8dff;--purple:#7657ff}
        *{box-sizing:border-box}html,body{min-height:100%;margin:0}body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(900px 500px at 50% -10%,rgba(61,141,255,.22),transparent 70%),radial-gradient(800px 500px at 90% 90%,rgba(118,87,255,.13),transparent 70%),var(--bg);color:var(--text);display:grid;place-items:center;padding:24px}
        body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(90,150,205,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(90,150,205,.05) 1px,transparent 1px);background-size:48px 48px;mask-image:linear-gradient(to bottom,#000,transparent 90%)}
        .wrap{width:min(760px,100%);position:relative;z-index:1}.brand{display:flex;align-items:center;justify-content:center;gap:11px;margin-bottom:24px;font-weight:900}.logo{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;background:linear-gradient(145deg,var(--blue),var(--purple));box-shadow:0 15px 45px rgba(77,86,255,.28)}
        .card{border:1px solid var(--line);border-radius:24px;background:rgba(7,19,31,.88);box-shadow:0 28px 90px rgba(0,0,0,.38);backdrop-filter:blur(20px);padding:clamp(28px,6vw,58px);text-align:center}.status{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border:1px solid #244361;border-radius:999px;background:#0b1d2e;color:#9bc9f7;font-size:11px;font-weight:900;letter-spacing:.11em;text-transform:uppercase}.dot{width:7px;height:7px;border-radius:50%;background:#5ad8ff;box-shadow:0 0 16px currentColor}
        h1{font-size:clamp(36px,8vw,64px);line-height:1.02;letter-spacing:-.045em;margin:22px 0 16px}.message{max-width:580px;margin:0 auto;color:var(--muted);font-size:clamp(15px,2.5vw,18px);line-height:1.7}.login{display:inline-flex;margin-top:28px;color:#87bfff;font-size:12px;text-decoration:none;font-weight:800}.login:hover{color:#fff}
        .countdown{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:34px auto 4px;max-width:560px}.unit{padding:18px 8px;border-radius:15px;background:#0b1c2d;border:1px solid #1c3854}.unit strong{font-size:clamp(24px,6vw,40px);display:block;letter-spacing:-.04em}.unit span{display:block;color:#69839f;font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;margin-top:3px}
        .maintenanceIcon{width:82px;height:82px;border-radius:22px;margin:30px auto 0;display:grid;place-items:center;background:linear-gradient(145deg,#182b40,#271f35);border:1px solid #314760;font-size:34px}
        @media(max-width:520px){.card{border-radius:19px;padding:30px 18px}.countdown{gap:6px}.unit{padding:14px 4px;border-radius:12px}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><span class="logo">N</span><span>Nodexa</span></div>
    <main class="card">
        <div class="status"><span class="dot"></span>Åbner snart</div>
        
        <h1>{{ $title }}</h1>
        <p class="message">{{ $message }}</p>
        @if($target)
        <div class="countdown" id="countdown" data-target="{{ $target }}" data-server-now="{{ $serverNow }}">
            <div class="unit"><strong id="days">--</strong><span>Dage</span></div>
            <div class="unit"><strong id="hours">--</strong><span>Timer</span></div>
            <div class="unit"><strong id="minutes">--</strong><span>Minutter</span></div>
            <div class="unit"><strong id="seconds">--</strong><span>Sekunder</span></div>
        </div>
        @endif
        <a class="login" href="{{ route('auth.admin-login') }}">Administrator-login →</a>
    </main>
</div>
<script>
(function(){
    var box=document.getElementById('countdown'); if(!box)return;

    var target=new Date(box.dataset.target).getTime();
    var serverNow=new Date(box.dataset.serverNow).getTime();
    var clockOffset=Date.now()-serverNow;
    var timer=null;
    var reloading=false;

    function serverTime(){
        return Date.now()-clockOffset;
    }

    function render(diff){
        var days=Math.floor(diff/86400000); diff%=86400000;
        var hours=Math.floor(diff/3600000); diff%=3600000;
        var minutes=Math.floor(diff/60000); diff%=60000;
        var seconds=Math.floor(diff/1000);
        document.getElementById('days').textContent=String(days).padStart(2,'0');
        document.getElementById('hours').textContent=String(hours).padStart(2,'0');
        document.getElementById('minutes').textContent=String(minutes).padStart(2,'0');
        document.getElementById('seconds').textContent=String(seconds).padStart(2,'0');
    }

    function tick(){
        var remaining=target-serverTime();

        if(remaining<=0){
            render(0);
            if(timer) clearInterval(timer);

            if(!reloading){
                reloading=true;
                // Give the server a brief moment to cross the exact target time.
                // On reload SiteAccessGate persists countdown_enabled=0 and serves
                // the normal website immediately.
                setTimeout(function(){
                    window.location.reload();
                },750);
            }
            return;
        }

        render(remaining);
    }

    tick();
    if(!reloading) timer=setInterval(tick,250);
})();
</script>
</body>
</html>
