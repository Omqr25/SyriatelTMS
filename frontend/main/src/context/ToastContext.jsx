import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { CheckCircle2, Info, XCircle } from 'lucide-react';
import styles from './Toast.module.css';

const ToastContext = createContext(null);
const ICONS = { success: CheckCircle2, info: Info, error: XCircle };

export function ToastProvider({ children }) {
  const [items, setItems] = useState([]);

  const toast = useCallback((message, type = 'success') => {
    const id = Date.now() + Math.random();
    setItems((list) => [...list, { id, message, type }]);
    setTimeout(() => setItems((list) => list.filter((i) => i.id !== id)), 3200);
  }, []);

  const value = useMemo(() => ({ toast }), [toast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className={styles.stack} role="status" aria-live="polite">
        {items.map(({ id, message, type }) => {
          const Icon = ICONS[type] ?? Info;
          return (
            <div key={id} className={`${styles.toast} ${styles[type]}`}>
              <Icon size={18} />
              <span>{message}</span>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}

export const useToast = () => useContext(ToastContext);
