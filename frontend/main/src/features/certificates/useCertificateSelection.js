import { useState } from 'react';

export default function useCertificateSelection(total, eligibleIds) {
  const [selection, setSelection] = useState(() => ({ all: false, ids: new Set() }));
  const isSelected = id => selection.all ? !selection.ids.has(id) : selection.ids.has(id);
  const selected = !selection.all ? selection.ids.size
    : eligibleIds ? eligibleIds.filter(isSelected).length : Math.max(0, total - selection.ids.size);
  const allChecked = total > 0 && selected === total && (!eligibleIds || eligibleIds.every(isSelected));
  const selectedIds = [...selection.ids];
  return {
    selected, allChecked,
    isSelected,
    all: selection.all, ids: selectedIds,
    clear: () => setSelection({ all: false, ids: new Set() }),
    toggleAll: () => setSelection({ all: !allChecked, ids: new Set() }),
    toggle: id => setSelection(current => {
      const ids = new Set(current.ids);
      if (ids.has(id)) ids.delete(id); else ids.add(id);
      return { all: current.all, ids };
    }),
    payload: selection.all ? { selection: 'all', except_ids: selectedIds }
      : { selection: 'explicit', certificate_ids: selectedIds },
  };
}
