const placeholders = new Set(['trainee_name','program_name','department_name',
  'program_start_date','program_end_date','issue_date','certificate_number']);
const ptToMm = 25.4 / 72;
const epsilon = 1e-8;

function blocks(box, values) {
  let total = 0;
  return box.paragraphs.map(paragraph => {
    const chunks = [];
    let glyphs = [];
    let precedingSpace = false;
    function flush() {
      if (glyphs.length) chunks.push({glyphs, gap:precedingSpace});
      if (glyphs.length) precedingSpace = false;
      glyphs = [];
    }
    for (const node of paragraph) {
      if (node.break === true) {
        flush();
        precedingSpace = false;
        chunks.push(null);
        continue;
      }
      const value = node.token ? values[node.token] : node.text;
      if (node.token && (!placeholders.has(node.token) || !value?.trim())) throw Error('invalid placeholder');
      if (typeof value !== 'string' || [...value].length > 2048) throw Error('invalid or oversized text node');
      total += [...value].length;
      if (total > 4096) throw Error('text box character budget exceeded');
      const style = (node.bold ? 'B' : '') + (node.italic ? 'I' : '') || 'R';
      for (const letter of value) {
        if (letter === ' ' || letter === '\t' || letter === '\n' || letter === '\r') {
          flush();
          precedingSpace = true;
        } else {
          glyphs.push([letter,style]);
          if (letter === '-' || letter === '‐') flush();
        }
      }
    }
    flush();
    return chunks;
  });
}

function shape(glyphs, family) {
  let advance = 0;
  let left = 0;
  let right = 0;
  let minY = 0;
  let maxY = 0;
  let prior = null;
  const positions = [];
  for (const [letter, style] of glyphs) {
    const face = family[style];
    const glyph = face.measurements[letter];
    if (!glyph) throw Error('source glyph outside the probe font repertoire');
    const [width,xMin,yMin,xMax,yMax] = glyph;
    if (prior?.[1] === style) advance += (face.kerning[prior[0]+letter] ?? 0) / face.units;
    positions.push([letter,style,advance]);
    left = Math.min(left, advance + xMin / face.units);
    right = Math.max(right, advance + xMax / face.units);
    minY = Math.min(minY, yMin/face.units);
    maxY = Math.max(maxY, yMax/face.units);
    advance += width / face.units;
    prior = [letter,style];
  }
  return {left,right:Math.max(right,advance),minY,maxY,positions};
}

function fitAt(box, family, paragraphs, size) {
  const mm = size * ptToMm;
  const ascent = Math.max(...Object.values(family).map(face => face.ascent / face.units));
  const descent = Math.max(...Object.values(family).map(face => face.descent / face.units));
  const lineHeight = Math.max(box.leading ?? 1.32, ascent + descent) * mm;
  const baselineOffset = (lineHeight - (ascent+descent)*mm)/2 + ascent*mm;
  const lines = [];
  let top = 0;
  for (const [index, paragraph] of paragraphs.entries()) {
    if (index) top += 1.5;
    let line = [];
    function push(wrapped) {
      const shaped = shape(line,family);
      const width = (shaped.right - shaped.left) * mm;
      const baseline = top + baselineOffset;
      if (width > box.w + epsilon || top + lineHeight > box.h + epsilon ||
          baseline - shaped.maxY*mm < -epsilon || baseline - shaped.minY*mm > box.h + epsilon) return false;
      const spare = Math.max(0,box.w-width);
      const spaces = line.filter(glyph => glyph[0] === ' ').length;
      const justify = box.align === 'justify' && wrapped && spaces ? spare/spaces : 0;
      const offset = box.align === 'center' ? spare/2 : box.align === 'right' ? spare : 0;
      let extra = 0;
      const glyphs = shaped.positions.map(([letter,style,x]) => {
        const result = [letter,style,box.x + offset + (x - shaped.left)*mm + extra];
        if (letter === ' ') extra += justify;
        return result;
      });
      lines.push({y:box.y+baseline,glyphs});
      line = [];
      top += lineHeight;
      return true;
    }
    for (const chunk of paragraph) {
      if (chunk === null) {
        if (!push(false)) return null;
        continue;
      }
      const prefix = line.length && chunk.gap ? [[' ',line.at(-1)[1]]] : [];
      const candidate = [...line,...prefix,...chunk.glyphs];
      const span = shape(candidate,family);
      if (line.length && (span.right-span.left)*mm > box.w + epsilon) {
        if (!push(true)) return null;
        line = chunk.glyphs;
      } else line = candidate;
    }
    if (!push(false)) return null;
  }
  return lines;
}

export function layout(config, metrics) {
  return Object.fromEntries(config.boxes.map(box => {
    if (box.direction !== 'ltr' || !metrics[box.font ?? 'sans']) throw Error('invalid layout configuration');
    const family = metrics[box.font ?? 'sans'];
    const paragraphs = blocks(box,config.values);
    for (let size = box.size; ; size = Math.max(box.min,size-.5)) {
      const lines = fitAt(box,family,paragraphs,size);
      if (lines) return [box.id,{fits:true,size,lines}];
      if (!box.fit || size <= box.min) return [box.id,{fits:false,size,lines:[]}];
    }
  }));
}
