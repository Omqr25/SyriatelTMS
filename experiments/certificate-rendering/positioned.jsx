import React from 'react';
import {createRoot} from 'react-dom/client';
import {flushSync} from 'react-dom';
import {layout} from './layout.js';

const root = createRoot(document.getElementById('root'));
const ptMm = 25.4 / 72;
const pxMm = 25.4 / 96;
const zoom = Number(new URL(location.href).searchParams.get('zoom') || 1);
document.body.style.zoom = zoom;
document.documentElement.dir = new URL(location.href).searchParams.get('ui') === 'ar' ? 'rtl' : 'ltr';
const faces = ['R','B','I','BI'];
const fontCss = ['sans','serif'].flatMap(family => faces.map(style =>
  `@font-face{font-family:'${family}-${style}';src:url('/fonts/${family}-${style}.ttf');font-display:block}`)).join('');
document.querySelector('#fonts').textContent = fontCss;

function Sheet({config, placement}) {
  const [w,h] = config.page;
  const background = config.background.replace(/\.pdf$/, '-preview.png');
  return <main id="sheet" dir="ltr" style={{width:`${w}mm`,height:`${h}mm`}}>
    <img alt="" src={`/fixtures/${background}`} id="background"/>
    <svg width="100%" height="100%" viewBox={`0 0 ${w} ${h}`}>
      {config.boxes.map(box => <g key={box.id} id={box.id} fill={box.color ?? '#123ca2'}>
        <rect x={box.x} y={box.y} width={box.w} height={box.h} fill="none"
              stroke="#e717aa" strokeWidth=".15"/>
        {placement[box.id].lines.map((line,index) => <g key={index} data-line={index}>
          {line.glyphs.map(([letter,style,x],i) => <text key={i} x={x} y={line.y} data-mark={style}
            fontSize={placement[box.id].size * ptMm}
            fontFamily={`${box.font ?? 'sans'}-${style}`}>{letter}</text>)}
        </g>)}
      </g>)}
    </svg>
    {Object.entries(placement).map(([id,box]) => !box.fits &&
      <output key={id} data-overflow={id} role="alert">Text does not fit</output>)}
  </main>;
}

window.positionFixture = async (config, metrics) => {
  const [w,h] = config.page;
  document.querySelector('#page-size').textContent = `@page{size:${w}mm ${h}mm;margin:0}`;
  await Promise.all([...new Set(config.boxes.map(box=>box.font ?? 'sans'))].flatMap(family=>
    faces.map(style=>document.fonts.load(`15px '${family}-${style}'`,'Alpha éç'))));
  const started = performance.now();
  const placement = layout(config,metrics);
  const layoutMs = performance.now()-started;
  flushSync(()=>root.render(<Sheet config={config} placement={placement}/>));
  const sheet = document.querySelector('#sheet');
  await sheet.querySelector('img').decode();
  const finish = performance.now();
  const sheetBounds = sheet.getBoundingClientRect();
  const report = {layoutMs,previewMs:finish-started, placement, page:[sheetBounds.width,sheetBounds.height].map(v=>v*pxMm/zoom),
    uiDirection:document.documentElement.dir, sheetDirection:getComputedStyle(sheet).direction,
    overflow:[...sheet.querySelectorAll('[data-overflow]')].map(x=>x.dataset.overflow), boxes:{}};
  for (const box of config.boxes) {
    const group=sheet.querySelector(`g#${box.id}`);
    const rect=group.querySelector('rect').getBoundingClientRect();
    const unit = v => v * pxMm / zoom;
    report.boxes[box.id]={
      edges:[unit(rect.left-sheetBounds.left),unit(rect.top-sheetBounds.top),
             unit(rect.right-sheetBounds.left),unit(rect.bottom-sheetBounds.top)],
      lines:[...group.querySelectorAll('[data-line]')].map(row => [...row.querySelectorAll('text')]
        .filter(element=>element.textContent !== ' ')
        .map(element => {
          const point = element.getStartPositionOfChar(0);
          const matrix = element.getScreenCTM();
          const origin = point.matrixTransform(matrix);
          const ink = element.getBBox();
          return {char:element.textContent, mark:element.dataset.mark,x:unit(origin.x-sheetBounds.x),
            y:unit(origin.y-sheetBounds.y), pt:parseFloat(getComputedStyle(element).fontSize)*unit(matrix.d)/ptMm,
            family:getComputedStyle(element).fontFamily,fill:getComputedStyle(element).fill,
            apiExtent:[ink.x,ink.y,ink.x+ink.width,ink.y+ink.height]};
        })),
    };
  }
  return report;
};
