// Validate the actual `vercel deploy --dry --json` artifact, not just local source.
import { readFile,readdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../',import.meta.url));
const report = JSON.parse((await readFile(process.argv[2] || '.tools/vercel-dry.json','utf8')).replace(/^\uFEFF/,''));
const paths = new Set(report.files.map(file => file.path.replaceAll('\\','/')));
async function required(directory) {
  let names = [];
  for (const entry of await readdir(resolve(root,directory),{withFileTypes:true})) {
    const path = `${directory}/${entry.name}`;
    if (entry.isDirectory()) names.push(...await required(path));
    else if (!entry.name.endsWith('.md')) names.push(path);
  }
  return names;
}
const expected = ['Dockerfile','Caddyfile','composer.json','composer.lock','vercel.json'];
for (const directory of ['app','bootstrap','config','routes','resources','public']) expected.push(...await required(directory));
const missing = expected.filter(path => !paths.has(path));
const privateFiles = [...paths].filter(path => /(^|\/)(\.env[^/]*|\.git|\.vercel|\.tools|vendor|node_modules)(\/|$)/.test(path));
if (missing.length || privateFiles.length) {
  for (const path of missing) console.error('Missing runtime file: ' + path);
  for (const path of privateFiles) console.error('Private deployment input: ' + path);
  process.exit(1);
}
console.log(`Deployment inputs verified: ${paths.size} files, all runtime files present, no private configuration.`);
