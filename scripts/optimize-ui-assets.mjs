import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { crc32 } from 'node:zlib';

import sharp from 'sharp';

const root = fileURLToPath(new URL('../', import.meta.url));
const output = path.join(root, 'public/assets/images');
await mkdir(output, { recursive: true });
await mkdir(path.join(root, 'resources/data'), { recursive: true });
const manifest = {};
const sources = [
  {name:'hero-phone', file:'originals/swapfix-gadgets-hero.png', widths:[560,1040], ratio:[1,1]},
  {name:'hero-discover', file:'originals/swapfix-discover-hero.png', widths:[480,800,1200], ratio:[4,3], transparent:true},
  {name:'home-sell', file:'originals/swapfix-home-sell.png', widths:[480,960], ratio:[4,3]},
  {name:'home-trade', file:'originals/swapfix-home-trade.png', widths:[480,960], ratio:[4,3], transparent:true},
  {name:'home-repair', file:'originals/swapfix-home-repair.png', widths:[480,960], ratio:[4,3]},
  {name:'scene-auth', file:'originals/swapfix-scene-auth.png', widths:[480,960], ratio:[4,3], transparent:true},
  {name:'scene-market', file:'originals/swapfix-scene-market.png', widths:[360,720], ratio:[4,3], transparent:true},
  {name:'scene-repair', file:'originals/swapfix-scene-repair.png', widths:[360,720], ratio:[4,3], transparent:true},
  {name:'home-device', file:'originals/swapfix-home-device.png', widths:[400,800], ratio:[4,3], transparent:true},
  {name:'phone-blue', file:'originals/blue-iphone-pro-back.webp', widths:[400,640], ratio:[4,3]},
  {name:'phone-graphite', file:'originals/silver-iphone-pro-back.webp', widths:[400,640], ratio:[4,3]},
  {name:'tablet', file:'originals/space-gray-ipad-pro-back.webp', widths:[400,640], ratio:[4,3]},
  {name:'laptop', file:'originals/acer-predator-laptop-open.webp', widths:[400,640], ratio:[4,3]},
  {name:'product-earbuds', file:'originals/swapfix-product-earbuds.png', widths:[400,640], ratio:[4,3]},
  {name:'product-charger', file:'originals/swapfix-product-charger.png', widths:[400,640], ratio:[4,3]},
  {name:'product-battery', file:'originals/swapfix-product-battery.png', widths:[400,640], ratio:[4,3]},
  {name:'product-logic-board', file:'originals/swapfix-product-logic-board.png', widths:[400,640], ratio:[4,3]},
];

for (const source of sources) {
  const bytes = await readFile(path.join(root, source.file));
  const variants = {};
  for (const width of source.widths) {
    const height = Math.round(width * source.ratio[1] / source.ratio[0]);
    const resized = sharp(bytes).rotate().resize(width,height,source.transparent
      ? {fit:'contain', background:{r:0,g:0,b:0,alpha:0}}
      : {fit:'cover',position:'centre'});
    const optimized = await resized.webp({quality:78,effort:6}).toBuffer();
    const hash = createHash('sha256').update(optimized).digest('hex').slice(0,10);
    const name = `${source.name}-${width}.${hash}.webp`;
    await writeFile(path.join(output,name),optimized);
    variants[width] = `/assets/images/${name}`;
    console.log(`${name}: ${optimized.length} bytes`);
  }
  const width = source.widths.at(-1);
  manifest[source.name] = {width,height:Math.round(width*source.ratio[1]/source.ratio[0]),sources:variants};
}

const logo = await sharp(path.join(output,'swapfix-logo-transparent.png')).trim({threshold:10}).resize({width:700}).png({compressionLevel:9}).toBuffer();
const logoMeta = await sharp(logo).metadata();
if (!logoMeta.hasAlpha) throw new Error('The logo must retain its transparent alpha.');
const hash = createHash('sha256').update(logo).digest('hex').slice(0,10);
const logoName = `swapfix-logo.${hash}.png`;
await writeFile(path.join(output,logoName),logo);
manifest.logo = {width:logoMeta.width,height:logoMeta.height,sources:{700:`/assets/images/${logoName}`}};
// Browser tab icons: the phone-and-wrench mark cut from the logo, on white so it stays visible on dark tabs.
const markArea = await sharp(path.join(output,'swapfix-logo-transparent.png')).extract({left:131,top:0,width:479,height:724}).png().toBuffer();
const mark = await sharp(markArea).trim({threshold:10}).png().toBuffer();
for (const [iconName,size,radius] of [['favicon',64,14],['apple-touch-icon',180,0]]) {
  const inner = Math.round(size*.86);
  const art = await sharp(mark).resize(inner,inner,{fit:'contain',background:{r:0,g:0,b:0,alpha:0}}).png().toBuffer();
  const tile = Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}"><rect width="${size}" height="${size}" rx="${radius}" fill="#fff"/></svg>`);
  const icon = await sharp(tile).composite([{input:art,gravity:'centre'}]).png({compressionLevel:9}).toBuffer();
  const iconFile = `${iconName}.${createHash('sha256').update(icon).digest('hex').slice(0,10)}.png`;
  await writeFile(path.join(output,iconFile),icon);
  manifest[iconName] = {width:size,height:size,sources:{[size]:`/assets/images/${iconFile}`}};
  console.log(`${iconFile}: ${icon.length} bytes`);
}
await writeFile(path.join(root,'resources/data/images.json'),JSON.stringify(manifest,null,2)+'\n');
console.log(`${logoName}: ${logo.length} bytes; transparent alpha preserved`);
const fontHash = crc32(await readFile(path.join(root,'public/assets/fonts/sora-latin.woff2'))).toString(16).padStart(8,'0');
const cssPath = path.join(root,'public/assets/css/app.css');
const css = await readFile(cssPath,'utf8');
await writeFile(cssPath,css.replace(/url\('\/assets\/fonts\/sora-latin\.woff2(?:\?v=[a-f0-9]+)?'\)/,`url('/assets/fonts/sora-latin.woff2?v=${fontHash}')`));
console.log(`Font preload/source version: ${fontHash}`);
