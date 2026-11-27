import { useRef } from 'react';
import Button from '../../../components/ui/Button';
import { Field, Input, Select } from '../../../components/ui/Field';
import { insertNodes, paragraphText, replaceText, toggleMark } from './richText';
import styles from './Templates.module.css';

function Paragraph({ nodes, onChange, remove, placeholders, t, index }) {
  const input = useRef(null);
  const range = useRef([0, 0]);
  const readRange = event => { range.current = [event.target.selectionStart, event.target.selectionEnd]; };
  function formatting(mark) {
    onChange(toggleMark(nodes, ...range.current, mark));
    input.current?.focus();
  }
  function addToken(event) {
    if (!event.target.value) return;
    const token = event.target.value;
    onChange(insertNodes(nodes, ...range.current, [{ token }]));
    const start = range.current[0] + token.length + 4;
    requestAnimationFrame(() => {
      input.current?.focus();
      input.current?.setSelectionRange(start, start);
      range.current = [start, start];
    });
  }

  return <div className={styles.paragraph}>
    <label className={styles.paragraphLabel}>
      {t('paragraph', { number: index + 1 })}
      <textarea ref={input} dir="ltr" lang="en" rows="3" maxLength={4096} value={paragraphText(nodes)}
        onChange={event => { readRange(event); onChange(replaceText(nodes, event.target.value)); }} onSelect={readRange} />
    </label>
    <div className={styles.actions}>
      <Button size="sm" variant="soft" onMouseDown={event => event.preventDefault()} onClick={() => formatting('bold')}>{t('bold')}</Button>
      <Button size="sm" variant="soft" onMouseDown={event => event.preventDefault()} onClick={() => formatting('italic')}>{t('italic')}</Button>
      <Select aria-label={t('insertPlaceholder')} value="" onChange={addToken}>
        <option value="" disabled>{t('insertPlaceholder')}</option>
        {placeholders.map(token => <option key={token} value={token}>{t(`placeholders.${token}`)}</option>)}
      </Select>
      {remove && <Button size="sm" variant="secondary" onClick={remove}>{t('removeParagraph')}</Button>}
    </div>
  </div>;
}

export default function TemplateInspector({ box, page, change, remove, placeholders, t }) {
  if (!box) return <p className={styles.muted}>{t('selectBox')}</p>;

  function changeNumber(key, minimum, maximum, value) {
    change({ [key]: Math.min(Math.max(Number(value), minimum), maximum) });
  }

  return <div className={styles.stack}>
    <div className={styles.controls}>
      {[
        ['x', 0, page.width - box.w], ['y', 0, page.height - box.h],
        ['w', 1, page.width - box.x], ['h', 1, page.height - box.y],
        ['size', box.min, 72], ['leading', 1, 3], ['min', 6, box.size],
      ].map(([key, min, max]) => <Field key={key} label={t(`box.${key}`)}>
        <Input key={`${key}-${Number(box[key].toFixed(2))}`} type="number" min={min} max={max} step=".01"
          defaultValue={Number(box[key].toFixed(2))} onBlur={event => changeNumber(key, min, max, event.target.value)} />
      </Field>)}
      <Field label={t('font')}><Select aria-label={t('font')} value={box.font} onChange={event => change({ font: event.target.value })}>
        <option value="sans">DejaVu Sans</option><option value="serif">DejaVu Serif</option>
      </Select></Field>
      <Field label={t('color')}><Input type="color" value={box.color} onChange={event => change({ color: event.target.value })} /></Field>
      <Field label={t('alignment')}><Select aria-label={t('alignment')} value={box.align} onChange={event => change({ align: event.target.value })}>
        {['left', 'center', 'right', 'justify'].map(value => <option key={value} value={value}>{t(`align.${value}`)}</option>)}
      </Select></Field>
      <label className={styles.check}><input type="checkbox" checked={box.fit} onChange={event => change({ fit: event.target.checked })} />{t('autoFit')}</label>
    </div>
    <p className={styles.muted}>{t('formatHint')}</p>
    {box.paragraphs.map((nodes, index) => <Paragraph key={index} index={index} nodes={nodes} placeholders={placeholders} t={t}
      onChange={next => change({ paragraphs: box.paragraphs.map((paragraph, i) => i === index ? next : paragraph) })}
      remove={box.paragraphs.length > 1 ? () => change({ paragraphs: box.paragraphs.filter((_, i) => i !== index) }) : undefined} />)}
    <div className={styles.actions}>
      <Button variant="soft" size="sm" disabled={box.paragraphs.length >= 20} onClick={() => change({ paragraphs: [...box.paragraphs, []] })}>{t('addParagraph')}</Button>
      <Button variant="secondary" size="sm" onClick={remove}>{t('deleteBox')}</Button>
    </div>
  </div>;
}
