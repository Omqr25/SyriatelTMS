export const pointsPerMillimeter = 72 / 25.4;
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
      if (node.token && !value?.trim()) throw Error('invalid placeholder');
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
    if (!glyph) throw Error('Unsupported certificate font character');
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
  const fontSize = size;
  const ascent = Math.max(...Object.values(family).map(face => face.ascent / face.units));
  const descent = Math.max(...Object.values(family).map(face => face.descent / face.units));
  const lineHeight = Math.max(box.leading ?? 1.32, ascent + descent) * fontSize;
  const baselineOffset = (lineHeight - (ascent+descent)*fontSize)/2 + ascent*fontSize;
  const lines = [];
  let top = 0;
  for (const [index, paragraph] of paragraphs.entries()) {
    if (index) top += 1.5 * pointsPerMillimeter;
    let line = [];
    function push(wrapped) {
      const shaped = shape(line,family);
      const width = (shaped.right - shaped.left) * fontSize;
      const baseline = top + baselineOffset;
      if (width > box.w + epsilon || top + lineHeight > box.h + epsilon ||
          baseline - shaped.maxY*fontSize < -epsilon || baseline - shaped.minY*fontSize > box.h + epsilon) return false;
      const spare = Math.max(0,box.w-width);
      const spaces = line.filter(glyph => glyph[0] === ' ').length;
      const justify = box.align === 'justify' && wrapped && spaces ? spare/spaces : 0;
      const offset = box.align === 'center' ? spare/2 : box.align === 'right' ? spare : 0;
      let extra = 0;
      const glyphs = shaped.positions.map(([letter,style,x]) => {
        const result = [letter,style,box.x + offset + (x - shaped.left)*fontSize + extra];
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
      if (line.length && (span.right-span.left)*fontSize > box.w + epsilon) {
        if (!push(true)) return null;
        line = chunk.glyphs;
      } else line = candidate;
    }
    if (!push(false)) return null;
  }
  return lines;
}

// Persistence uses millimetres. The painter and layout contract use physical PDF points.
export function layoutBox(input, values, metrics) {
  const box = {...input};
  for (const key of ['x', 'y', 'w', 'h']) box[key] *= pointsPerMillimeter;
  const family = metrics[box.font];
  if (!family) throw Error('Unknown certificate font');
  const paragraphs = blocks(box, values);
  for (let size = box.size; ; size = Math.max(box.min, size - .5)) {
    const lines = fitAt(box, family, paragraphs, size);
    if (lines) return {fits: true, size, lines};
    if (!box.fit || size <= box.min) return {fits: false, size, lines: []};
  }
}
