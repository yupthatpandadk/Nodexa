import test from 'node:test';import assert from 'node:assert/strict';import {csv,escalation} from '../src/policy.js';
test('csv parses IDs and domains',()=>assert.deepEqual(csv('1, 2,example.com'),['1','2','example.com']));
test('warn escalation parses timeout',()=>assert.deepEqual(escalation(3,{warn_3_action:'timeout:30'}),{action:'timeout',value:30}));
test('warn escalation can ban',()=>assert.deepEqual(escalation(10,{warn_10_action:'ban'}),{action:'ban',value:0}));
