export const normalize=s=>String(s||'').normalize('NFKC').replace(/[\u200B-\u200D\uFEFF]/g,'').toLowerCase();
