// Only typed nodes cross the API boundary. The text input labels tokens without parsing user text as tokens.
const display = node => node.break ? '\n' : node.token ? `{{${node.token}}}` : node.text;

export function paragraphText(nodes) {
  return nodes.map(display).join('');
}

function indexed(nodes) {
  let offset = 0;
  return nodes.map(node => {
    const start = offset;
    offset += display(node).length;
    return { node, start, end: offset };
  });
}

function expandTokens(parts, from, to) {
  for (const { node, start, end } of parts) {
    if (!node.token) continue;
    if (start < from && from < end) from = start;
    if (start < to && to < end) to = end;
  }
  return [from, to];
}

function slice(parts, from, to) {
  return parts.flatMap(({ node, start, end }) => {
    if (end <= from || start >= to) return [];
    if (!('text' in node)) return [node];
    return [{ ...node, text: node.text.slice(Math.max(0, from - start), to - start) }];
  });
}

function compact(nodes) {
  const result = [];
  for (const node of nodes) {
    if (node.text === '') continue;
    const previous = result.at(-1);
    if (previous?.text && node.text && Boolean(previous.bold) === Boolean(node.bold)
        && Boolean(previous.italic) === Boolean(node.italic)) {
      result[result.length - 1] = { ...previous, text: previous.text + node.text };
    } else result.push(node);
  }
  return result;
}

export function insertNodes(nodes, from, to, replacement) {
  const parts = indexed(nodes);
  [from, to] = expandTokens(parts, from, to);
  return compact([...slice(parts, 0, from), ...replacement, ...slice(parts, to, Infinity)]);
}

export function replaceText(nodes, updated) {
  const old = paragraphText(nodes);
  let from = 0;
  while (from < Math.min(old.length, updated.length) && old[from] === updated[from]) from++;
  let suffix = 0;
  while (suffix < Math.min(old.length, updated.length) - from
      && old[old.length - suffix - 1] === updated[updated.length - suffix - 1]) suffix++;
  const fromNode = indexed(nodes).find(part => part.start <= from && from <= part.end)?.node;
  const marks = fromNode?.text ? fromNode : {};
  const text = updated.slice(from, updated.length - suffix);
  const replacement = text.split('\n').flatMap((value, index) => [
    ...(index ? [{ break: true }] : []),
    { text: value, ...(marks.bold && { bold: true }), ...(marks.italic && { italic: true }) },
  ]);
  return insertNodes(nodes, from, old.length - suffix, replacement);
}

export function toggleMark(nodes, from, to, mark) {
  if (from === to) return nodes;
  const parts = indexed(nodes);
  [from, to] = expandTokens(parts, from, to);
  const affected = slice(parts, from, to);
  const enable = !affected.every(node => node.break || node[mark]);
  return insertNodes(nodes, from, to, affected.map(node => node.break ? node : { ...node, [mark]: enable }));
}
