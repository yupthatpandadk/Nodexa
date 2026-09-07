#!/usr/bin/env bash
set -Eeuo pipefail
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
LAYOUT="$PANEL_DIR/resources/views/layouts/admin.blade.php"
[[ -f "$LAYOUT" ]] || exit 0
python3 - "$LAYOUT" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1])
s=p.read_text()
marker='nodexa-update-menu-dedupe'
if marker in s:
    raise SystemExit(0)
script='''<script id="nodexa-update-menu-dedupe">
(function(){
  function cleanUpdateMenu(){
    var menu=document.querySelector('.sidebar-menu');
    if(!menu)return;
    var links=Array.from(menu.querySelectorAll('a'));
    var updateLinks=links.filter(function(a){
      var href=(a.getAttribute('href')||'').toLowerCase();
      var text=(a.textContent||'').trim().toLowerCase();
      return href.indexOf('/admin/updates')!==-1 || text==='update center' || text==='opdateringer';
    });
    if(!updateLinks.length)return;
    var keep=updateLinks.find(function(a){return (a.textContent||'').trim().toLowerCase()==='update center';}) || updateLinks[0];
    var label=keep.querySelector('span');
    if(label)label.textContent='Update Center'; else {
      var icon=keep.querySelector('i');
      keep.textContent=' Update Center';
      if(icon)keep.insertBefore(icon,keep.firstChild);
    }
    updateLinks.forEach(function(a){if(a!==keep){var li=a.closest('li');if(li)li.remove();}});
  }
  document.addEventListener('DOMContentLoaded',function(){
    cleanUpdateMenu();
    var menu=document.querySelector('.sidebar-menu');
    if(menu)new MutationObserver(cleanUpdateMenu).observe(menu,{childList:true,subtree:true});
  });
})();
</script>'''
if '</body>' in s:
    s=s.replace('</body>',script+'</body>',1)
else:
    s+=script
p.write_text(s)
PY
echo "[Nodexa] Update Center menu deduplicated."
