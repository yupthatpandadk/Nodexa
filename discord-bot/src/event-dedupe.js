const seen=new Map();export function first(id,ttl=60000){const now=Date.now();for(const [k,t] of seen)if(now-t>ttl)seen.delete(k);if(seen.has(id))return false;seen.set(id,now);return true}
