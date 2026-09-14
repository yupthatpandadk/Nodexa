export const csv=v=>String(v||'').split(',').map(x=>x.trim()).filter(Boolean);
export function exempt(message,c){if(csv(c.exempt_users).includes(message.author?.id))return true;if(csv(c.exempt_channels).includes(message.channel?.id))return true;return message.member?.roles?.cache?.some(r=>csv(c.exempt_roles).includes(r.id))||false}
export function staffAllowed(member,c){const ids=[c.moderator_role_id,c.senior_moderator_role_id,c.admin_role_id].filter(Boolean);return member.permissions.has('Administrator')||!ids.length||member.roles.cache.some(r=>ids.includes(r.id))}
export function escalation(count,c){const rule=c[`warn_${count}_action`];if(!rule)return null;const [action,value]=String(rule).toLowerCase().split(':');return {action,value:Number(value||0)}}
