import { useCallback, useEffect, useId, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Clock, TriangleAlert } from "lucide-react";
import styles from "./ApplicantScore.module.css";

export default function ApplicantScore({ value, formattedValue, minimum, warning, pending, iconLabel }) {
  const hasScore = value !== null && value !== undefined && value !== "";
  const message = hasScore ? (Number(value) < minimum ? warning : null) : pending;
  const display = <bdi>{hasScore ? formattedValue : "—"}</bdi>;

  if (!message) return display;

  return (
    <span className={`${styles.score} ${hasScore ? styles.warning : styles.pending}`}>
      {display}
      <ScoreTooltip icon={hasScore ? TriangleAlert : Clock} label={iconLabel} message={message} />
    </span>
  );
}

function ScoreTooltip({ icon: Icon, label, message }) {
  const id = useId();
  const button = useRef(null);
  const [position, setPosition] = useState(null);

  const show = useCallback(() => {
    const bounds = button.current.getBoundingClientRect();
    if (bounds.bottom < 0 || bounds.top > window.innerHeight || bounds.right < 0 || bounds.left > window.innerWidth) {
      setPosition(null);
      return;
    }
    const above = window.innerHeight - bounds.bottom < 100;

    setPosition({
      left: `clamp(7.5rem, ${bounds.left + bounds.width / 2}px, calc(100vw - 7.5rem))`,
      [above ? "bottom" : "top"]: above ? window.innerHeight - bounds.top + 6 : bounds.bottom + 6,
    });
  }, []);

  const dismissWhenUnfocused = (event) => {
    if (event.pointerType !== "touch" && document.activeElement !== button.current) setPosition(null);
  };

  const visible = position !== null;
  useEffect(() => {
    if (!visible) return;

    const close = () => setPosition(null);
    const closeOutside = (event) => {
      if (!button.current?.contains(event.target)) close();
    };
    const closeOnEscape = (event) => {
      if (event.key === "Escape") close();
    };

    document.addEventListener("pointerdown", closeOutside);
    document.addEventListener("keydown", closeOnEscape);
    window.addEventListener("scroll", show, true);
    window.addEventListener("resize", show);
    return () => {
      document.removeEventListener("pointerdown", closeOutside);
      document.removeEventListener("keydown", closeOnEscape);
      window.removeEventListener("scroll", show, true);
      window.removeEventListener("resize", show);
    };
  }, [show, visible]);

  return (
    <>
      <button
        ref={button}
        type="button"
        className={styles.icon}
        aria-label={label}
        aria-describedby={position ? id : undefined}
        onFocus={show}
        onBlur={() => setPosition(null)}
        onPointerEnter={(event) => {
          if (event.pointerType !== "touch") show();
        }}
        onPointerLeave={dismissWhenUnfocused}
        onClick={show}
      >
        <Icon size={16} aria-hidden="true" />
      </button>
      {position && createPortal(
        <span
          id={id}
          role="tooltip"
          className={styles.tooltip}
          style={position}
        >
          {message}
        </span>,
        document.body,
      )}
    </>
  );
}
