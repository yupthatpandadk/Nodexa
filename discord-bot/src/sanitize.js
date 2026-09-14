export function safe(value,max=1000){return String(value??'').replace(/@everyone/g,'@ everyone').replace(/@here/g,'@ here').slice(0,max)}
