import { useMemo, useRef, useState } from 'react';
import { layoutBox, pointsPerMillimeter as scale } from './layout';
import { certificateFont } from './previewAssets';
import styles from './Templates.module.css';

function clamp(value, minimum, maximum) {
  return Math.min(Math.max(value, minimum), maximum);
}

export default function CertificateCanvas({ page, boxes, selected, select, move, background, assets, sample, zoom, editable, t }) {
  const svg = useRef(null);
  const interaction = useRef(null);
  const [position, setPosition] = useState(null);
  const placed = useMemo(() => boxes.map((box, index) => {
    const active = position?.index === index ? { ...box, ...position.patch } : box;
    try { return layoutBox(active, assets.samples[sample], assets.fonts); }
    catch { return { fits: false, lines: [] }; }
  }), [boxes, assets, sample, position]);

  function point(event) {
    const { width, height, left, top } = svg.current.getBoundingClientRect();
    return [(event.clientX - left) * page.width / width, (event.clientY - top) * page.height / height];
  }
  function start(event, index, resize) {
    if (!editable || event.button !== 0) return;
    event.stopPropagation();
    const target = event.currentTarget;
    target.setPointerCapture(event.pointerId);
    interaction.current = { index, resize, start: point(event), origin: boxes[index] };
    select(index);
  }
  function drag(event) {
    if (!interaction.current) return;
    const { index, resize, start, origin } = interaction.current;
    const [x, y] = point(event);
    const dx = x - start[0], dy = y - start[1];
    const patch = resize ? {
      w: clamp(origin.w + dx, 1, page.width - origin.x),
      h: clamp(origin.h + dy, 1, page.height - origin.y),
    } : {
      x: clamp(origin.x + dx, 0, page.width - origin.w),
      y: clamp(origin.y + dy, 0, page.height - origin.h),
    };
    interaction.current.patch = patch;
    setPosition({ index, patch });
  }
  function stop(cancel = false) {
    const current = interaction.current;
    interaction.current = null;
    setPosition(null);
    if (!cancel && current?.patch) move(current.index, current.patch);
  }

  return <div className={styles.canvasRegion} dir="ltr">
    <svg ref={svg} aria-label={t('canvas')} lang="en" data-testid="certificate-canvas"
      className={styles.canvas} viewBox={`0 0 ${page.width * scale} ${page.height * scale}`}
      style={{ width: `${zoom}%` }}>
      {background && <image href={background} width={page.width * scale} height={page.height * scale} preserveAspectRatio="none" />}
      {boxes.map((box, index) => {
        const active = position?.index === index ? { ...box, ...position.patch } : box;
        const layout = placed[index];
        const rect = { x: active.x * scale, y: active.y * scale, width: active.w * scale, height: active.h * scale };
        return <g key={index} data-box={index} data-overflow={!layout.fits || undefined}>
          {editable && <rect {...rect} fill="transparent" stroke={layout.fits ? (selected === index ? '#2563eb' : '#7b8798') : '#bd1b31'}
            strokeWidth=".65" vectorEffect="non-scaling-stroke" className={styles.box}
            onPointerDown={event => start(event, index, false)} onPointerMove={drag}
            onPointerUp={() => stop()} onPointerCancel={() => stop(true)} />}
          <g className={styles.glyphs} fill={box.color} pointerEvents="none">
            {layout.lines.map((line, row) => <g key={row} data-line={row}>
              {line.glyphs.map(([letter, mark, x], column) =>
                <text key={column} x={x} y={line.y} fontSize={layout.size}
                  data-mark={mark} fontFamily={certificateFont(box.font, mark, assets.fonts[box.font][mark].sha256)}>{letter}</text>
              )}
            </g>)}
          </g>
          {editable && selected === index &&
            <rect x={rect.x + rect.width - 4} y={rect.y + rect.height - 4} width="8" height="8"
              fill="#2563eb" className={styles.handle} data-testid="certificate-resize-handle"
              onPointerDown={event => start(event, index, true)} onPointerMove={drag}
              onPointerUp={() => stop()} onPointerCancel={() => stop(true)} />}
        </g>;
      })}
    </svg>
    {placed.some(box => !box.fits) && <p role="alert" dir="auto" className={styles.warning}>
      {t('overflow', { boxes: placed.flatMap((box, index) => box.fits ? [] : [index + 1]).join(', ') })}
    </p>}
  </div>;
}
