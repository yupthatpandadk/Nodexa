export function graceful(client){for(const sig of ['SIGTERM','SIGINT'])process.once(sig,async()=>{try{client.destroy()}finally{process.exit(0)}})}
