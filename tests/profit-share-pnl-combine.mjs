// Runs the browser P&L combine() from profit-and-loss/pnl.js on fixture JSON so
// tests/profit-share-test.php can prove the PHP port returns identical net profit.
// Usage: node tests/profit-share-pnl-combine.mjs <sales.json> <accounting.json>
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const pnlPath = path.join(root, 'profit-and-loss', 'pnl.js');
const source = fs.readFileSync(pnlPath, 'utf8');
const start = source.indexOf('  const numeric = (row, keys) =>');
const end = source.indexOf('  const sumRows = ');
if (start < 0 || end < start) throw new Error('Unable to locate combine() in pnl.js.');
const packing = source.match(/const PACKING_COST_PER_SOLD_UNIT = (\d+);/);
if (!packing) throw new Error('Unable to locate PACKING_COST_PER_SOLD_UNIT in pnl.js.');
const { separatePnlSalesRevenue } = await import(pathToFileURL(pnlPath).href);
const combine = new Function(
  'separatePnlSalesRevenue',
  'PACKING_COST_PER_SOLD_UNIT',
  `${source.slice(start, end)}\nreturn combine;`
)(separatePnlSalesRevenue, Number(packing[1]));
const [salesFile, accountingFile] = process.argv.slice(2);
const sales = JSON.parse(fs.readFileSync(salesFile, 'utf8'));
const accounting = JSON.parse(fs.readFileSync(accountingFile, 'utf8'));
process.stdout.write(JSON.stringify(combine(sales, accounting).map(({ month, revenue, cogs, packing: pack, opex, netProfit }) => ({ month, revenue, cogs, packing: pack, opex, netProfit }))));
