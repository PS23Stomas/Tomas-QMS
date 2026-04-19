import ExcelJS from 'exceljs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const txtPath = path.join(__dirname, 'attached_assets/Pasted-PAAI-KINIMAI-1-router-php-Tai-sistemos-pa-to-d-ut-Kiekv_1776620012061.txt');
const content = fs.readFileSync(txtPath, 'utf-8');

const lines = content.split('\n').filter(line => line.trim());

const entries = [];
let currentEntry = null;

for (const line of lines) {
  const match = line.match(/^(\d+)\.\s+(\S+(?:\s*\/\s*\S+)?)\s+[—–-]?\s*(.*)/);
  if (match) {
    if (currentEntry) entries.push(currentEntry);
    const nr = parseInt(match[1]);
    const file = match[2].trim();
    const desc = match[3].trim();
    currentEntry = { nr, file, desc };
  } else if (currentEntry && line.trim()) {
    currentEntry.desc += ' ' + line.trim();
  }
}
if (currentEntry) entries.push(currentEntry);

const workbook = new ExcelJS.Workbook();
const sheet = workbook.addWorksheet('Paaiškinimai');

sheet.columns = [
  { header: 'Nr.', key: 'nr', width: 6 },
  { header: 'Failas', key: 'file', width: 40 },
  { header: 'Aprašymas', key: 'desc', width: 80 },
];

const headerRow = sheet.getRow(1);
headerRow.eachCell(cell => {
  cell.font = { bold: true };
});

for (const entry of entries) {
  const row = sheet.addRow({ nr: entry.nr, file: entry.file, desc: entry.desc });
  row.getCell('desc').alignment = { wrapText: true };
}

const outPath = path.join(__dirname, 'paaiskinimai.xlsx');
await workbook.xlsx.writeFile(outPath);

console.log(`Generated ${outPath}`);
console.log(`Total entries: ${entries.length}`);
entries.forEach(e => console.log(`  ${e.nr}. ${e.file}`));
