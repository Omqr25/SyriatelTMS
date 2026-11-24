import { useEffect, useId, useRef, useState } from "react";
import { EllipsisVertical } from "lucide-react";
import { useTranslation } from "react-i18next";
import styles from "./ProgramActions.module.css";

export default function ProgramActions({ program, actions }) {
  const { t } = useTranslation("hr");
  const [open, setOpen] = useState(false);
  const container = useRef(null);
  const trigger = useRef(null);
  const menu = useRef(null);
  const enterAtEnd = useRef(false);
  const id = useId();

  function close(restore = true) {
    setOpen(false);
    if (restore) trigger.current?.focus();
  }

  useEffect(() => {
    if (!open) return;
    const items = Array.from(menu.current.querySelectorAll('[role="menuitem"]'));
    (enterAtEnd.current ? items.at(-1) : items[0])?.focus();
    enterAtEnd.current = false;
    function outside(event) {
      if (!container.current?.contains(event.target)) close(false);
    }
    document.addEventListener("pointerdown", outside);
    return () => document.removeEventListener("pointerdown", outside);
  }, [open]);

  function navigate(event) {
    const items = Array.from(menu.current.querySelectorAll('[role="menuitem"]'));
    const index = items.indexOf(document.activeElement);
    const keys = { ArrowDown: (index + 1) % items.length, ArrowUp: (index - 1 + items.length) % items.length,
      Home: 0, End: items.length - 1 };
    if (event.key in keys) {
      event.preventDefault();
      items[keys[event.key]]?.focus();
    } else if (event.key === "Escape" || event.key === "Tab") {
      if (event.key === "Escape") event.preventDefault();
      close();
    }
  }

  return (
    <div className={styles.anchor} ref={container}>
      <button type="button" ref={trigger} className={styles.trigger}
        aria-label={t("registration.programActions", { name: program.name })} aria-haspopup="menu"
        aria-expanded={open} aria-controls={open ? id : undefined}
        onClick={() => open ? close() : setOpen(true)}
        onKeyDown={(event) => {
          if (!open && ["ArrowDown", "ArrowUp"].includes(event.key)) {
            event.preventDefault();
            enterAtEnd.current = event.key === "ArrowUp";
            setOpen(true);
          }
        }}>
        <EllipsisVertical size={19} aria-hidden="true" />
      </button>
      {open && (
        <div id={id} className={styles.menu} role="menu" aria-label={t("registration.programActions", { name: program.name })}
          ref={menu} onKeyDown={navigate}>
          {actions.map(({ label, icon: Icon, run, destructive, reason }) => (
            <div key={label} role="none" className={destructive ? styles.destructive : undefined}>
              <button type="button" role="menuitem" tabIndex={-1} aria-disabled={reason ? true : undefined}
                className={styles.action} aria-describedby={reason ? `${id}-reason` : undefined}
                onClick={() => { if (!reason) { close(); run(); } }}>
                <Icon size={16} aria-hidden="true" />{label}
              </button>
              {reason && <small id={`${id}-reason`} className={styles.reason}>{reason}</small>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
