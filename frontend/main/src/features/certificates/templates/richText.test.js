import { describe, expect, it } from 'vitest';
import { insertNodes, paragraphText, replaceText, toggleMark } from './richText';

describe('structured certificate paragraphs', () => {
  it('inserts tokens at the caret, preserves adjoining text, and formats each token independently', () => {
    const nodes = insertNodes([{ text: 'For  at Syriatel' }], 4, 4, [{ token: 'trainee_name' }]);
    expect(paragraphText(nodes)).toBe('For {{trainee_name}} at Syriatel');
    expect(toggleMark(nodes, 8, 11, 'italic')).toEqual([
      { text: 'For ' }, { token: 'trainee_name', italic: true }, { text: ' at Syriatel' },
    ]);
  });

  it('preserves partial marks on untouched characters and models explicit line breaks', () => {
    const nodes = toggleMark([{ text: 'Times New Roman' }], 0, 5, 'bold');
    expect(replaceText(nodes, 'Times New Roman\nReady')).toEqual([
      { text: 'Times', bold: true }, { text: ' New Roman' }, { break: true }, { text: 'Ready' },
    ]);
  });

  it('does not promote literal user input or copy a preceding token style to adjacent text', () => {
    const nodes = [{ token: 'trainee_name', bold: true }];
    expect(replaceText(nodes, '{{trainee_name}} {{salutation}}')).toEqual([
      { token: 'trainee_name', bold: true }, { text: ' {{salutation}}' },
    ]);
  });
});
