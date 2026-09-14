export function retained(items,days=0){if(!days)return items;const min=Date.now()-Number(days)*86400000;return items.filter(x=>Date.parse(x.created_at||x.timestamp)>=min)}
