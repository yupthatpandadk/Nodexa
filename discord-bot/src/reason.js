export function reason(value,required=true){const v=String(value||'').trim();if(required&&!v)throw new Error('Moderation reason is required');return v.slice(0,512)||'Ingen årsag'}
