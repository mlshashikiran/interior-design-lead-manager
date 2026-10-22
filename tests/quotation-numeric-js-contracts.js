/* Builder: Shashi. Dependency-free golden vectors for production decimal helpers. */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/quotations.js'), 'utf8');
const helpers = source.split('// QUO008 integer decimal helpers. Keep rounding identical to the PHP calculator.')[1].split('// End QUO008 integer decimal helpers.')[0];
const context = vm.createContext({});
vm.runInContext(helpers, context);
let checked = 0;
function check(actual, expected) { assert.equal(actual, expected); checked++; }
const amount = 999999999999, measure = 99999999999;
for (const [text, expected] of [['.005',1],['1.005',101],['2.675',268],['1.335',134],['0',0],['',0],['9999999999.99',amount],['9999999999.995',null],['1e2',null],['1e309',null],['-1',null],['INF',null],['NaN',null],['1x',null]]) { check(context.decimalUnits(text,0,amount), expected); }
check(context.decimalUnits('999999999.99',0,measure),measure);
check(context.decimalUnits('1000000000',0,measure),null);
check(context.decimalUnits('.001',1,measure),1);
check(context.decimalProduct(15,10,amount),2);
check(context.decimalProduct(134,100000,amount),134000);
check(context.decimalProduct(measure,measure,measure),null);
check(context.decimalProduct(amount,101,amount),null);
const tinyQuantity = Math.max(1,context.decimalProduct(1,1,measure));
check(tinyQuantity,1); check(context.decimalProduct(tinyQuantity,10000,amount),100);
check(context.decimalPercentage(202,1250),25);
check(context.decimalPercentage(177,1000),18);
check(context.decimalPercentage(293,1800),53);
check(context.decimalPercentage(amount,10000),amount);
check(context.decimalPercentage(amount,1800),180000000000);
console.log(checked + ' JavaScript golden vectors PASS');
