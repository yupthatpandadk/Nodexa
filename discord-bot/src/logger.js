export function logger(level,event,data={}){const row={time:new Date().toISOString(),level,event,...data};const out=JSON.stringify(row);if(level==='error')console.error(out);else console.log(out)}
