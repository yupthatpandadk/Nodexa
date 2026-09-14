import os from 'node:os';export function runtimeInfo(){return {node:process.version,platform:process.platform,arch:process.arch,hostname:os.hostname(),pid:process.pid}}
