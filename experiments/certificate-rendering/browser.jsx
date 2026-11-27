import React from 'react';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';

const values = ['trainee_name', 'program_name', 'department_name', 'program_start_date',
  'program_end_date', 'issue_date', 'certificate_number'];
const pxToMm = 25.4 / 96;
const scale = new URL(location.href).searchParams.get('zoom') || 1;
const baseFont = new URL(location.href).searchParams.get('font') || 'Original';
const runStyle = { whiteSpace: 'normal' };

function resolve(node, data) {
  if (node.break === true) return null;
  if (node.token && (!values.includes(node.token) || !data[node.token]?.trim())) throw Error('bad variable');
  const result = node.token ? data[node.token] : node.text;
  if (typeof result !== 'string' || result.length > 2048) throw Error('bad text');
  return result;
}

function Content({box, config}) {
  return box.paragraphs.map((runs, paragraph) =>
    <p key={paragraph} dir={box.direction}>{runs.map((run, index) => {
      const value = resolve(run, config.values);
      return value === null ? <br key={index}/> : <span key={index} data-run={index}
        style={{...runStyle, fontWeight: run.bold ? 700 : 400,
          fontStyle: run.italic ? 'italic' : 'normal'}}>{value}</span>;
    })}</p>);
}

function Page({config, sizes}) {
  const [w, h] = config.page;
  document.querySelector('#page-size').textContent = `@page { size: ${w}mm ${h}mm; margin:0 }`;
  const background = config.background.replace(/\.pdf$/, '-preview.png');
  return <main style={{width:`${w}mm`,height:`${h}mm`}} id="sheet">
    <img alt="" src={`/fixtures/${background}`} id="background"/>
    {config.boxes.map(box => <section key={box.id} id={box.id} dir={box.direction}
      style={{left:`${box.x}mm`,top:`${box.y}mm`,width:`${box.w}mm`,
        height:`${box.h}mm`,fontSize:`${sizes[box.id]}pt`,fontFamily:baseFont,
        textAlign:box.align}}>
      <Content box={box} config={config}/>
    </section>)}
  </main>;
}

function probe(box) {
  const section = document.getElementById(box.id);
  const rect = section.getBoundingClientRect();
  const page = document.getElementById('sheet').getBoundingClientRect();
  const mm = n => n * pxToMm / scale;
  const baselines = [];
  const words = [];
  for (const [paragraph, p] of [...section.querySelectorAll('p')].entries()) {
    const ruler = document.createElement('i');
    ruler.className = 'ruler';
    p.prepend(ruler);
    baselines.push(mm(ruler.getBoundingClientRect().bottom - page.top));
    ruler.remove();
    for (const span of p.querySelectorAll('[data-run]')) {
      const text = span.firstChild;
      if (!text) continue;
      for (const match of text.data.matchAll(/[^\s]+/gu)) {
        const range = document.createRange();
        range.setStart(text, match.index);
        range.setEnd(text, match.index + match[0].length);
        for (const r of range.getClientRects()) words.push([match[0], mm(r.x-page.x), mm(r.y-page.y),
          mm(r.right-page.x), mm(r.bottom-page.y), paragraph, +span.dataset.run, match.index]);
      }
    }
  }
  const bounds = [mm(rect.x-page.x),mm(rect.y-page.y),mm(rect.right-page.x),mm(rect.bottom-page.y)];
  const used = section.lastElementChild.getBoundingClientRect().bottom - rect.top;
  const fit = mm(used - rect.height) < .03 &&
    words.every(w => w[1] >= bounds[0] - .04 && w[3] <= bounds[2] + .04);
  return { bounds, firstParagraphBaselines:baselines, words, usedMm:mm(used), fit };
}

const wrapper = document.getElementById('root');
wrapper.style.zoom = scale;
const root = createRoot(wrapper);
window.evaluateFixture = async config => {
  for (const spec of ['400 15pt','700 15pt','italic 400 15pt','italic 700 15pt'])
    await document.fonts.load(`${spec} ${baseFont}`, 'Aa سلامٌ');
  let sizes = Object.fromEntries(config.boxes.map(box => [box.id,box.size]));
  const start = performance.now();
  flushSync(() => root.render(<Page config={config} sizes={sizes}/>));
  const iterations = {};
  for (const box of config.boxes) {
    const pending = iterations[box.id] = [];
    while (true) {
      pending.push({pt:sizes[box.id],...probe(box)});
      if (pending.at(-1).fit || !box.fit || sizes[box.id] <= box.min) break;
      sizes = {...sizes,[box.id]:Math.max(box.min,sizes[box.id] - .5)};
      flushSync(() => root.render(<Page config={config} sizes={sizes}/>));
    }
  }
  await document.getElementById('background').decode();
  return {sizes, iterations, elapsedMs:performance.now()-start, pixelRatio:devicePixelRatio};
};
