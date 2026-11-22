import { useEffect, useId, useRef, useState } from "react";
import { ChevronDown } from "lucide-react";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import styles from "./AdmissionFilters.module.css";

export default function AdmissionFilters({ filters, updateFilter, minimums }) {
  const { t } = useTranslation("hr");
  const label = (key, options) => t(`applicants.admission.${key}`, options);
  const id = useId();
  const panel = useRef(null);
  const [open, setOpen] = useState(false);

  const groups = [
    { key: "gpa_eligibility", title: t("common:label.gpa"), minimum: `${minimums.gpa}%` },
    { key: "english_eligibility", title: t("englishScores:score"), minimum: minimums.english, pending: true },
  ];
  const activeCount = groups.filter(({ key }) => filters[key]).length;

  useEffect(() => {
    if (!open) return;

    const closeOutside = (event) => {
      if (!panel.current?.contains(event.target)) setOpen(false);
    };
    document.addEventListener("pointerdown", closeOutside);
    return () => document.removeEventListener("pointerdown", closeOutside);
  }, [open]);

  const closeOnEscape = (event) => {
    if (event.key !== "Escape" || !open) return;
    setOpen(false);
    event.currentTarget.querySelector("button")?.focus();
  };

  return (
    <div className={styles.root} ref={panel} onKeyDown={closeOnEscape}>
      <Button
        size="sm"
        variant="secondary"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen((value) => !value)}
      >
        <span className={styles.trigger}>
          {label("label")}
          {activeCount > 0 && <span className={styles.count}>{activeCount}</span>}
          <ChevronDown size={14} aria-hidden="true" />
        </span>
      </Button>

      <div id={id} className={styles.panel} hidden={!open}>
        {groups.map(({ key, title, minimum, pending }) => {
          const options = [
            ["", t("common:label.all")],
            ["eligible", label("eligible", { minimum })],
            ["not_eligible", label("notEligible", { minimum })],
          ];
          if (pending) options.push(["pending", label("pending")]);

          return (
            <fieldset key={key} className={styles.group}>
              <legend>{title}</legend>
              {options.map(([value, optionLabel]) => (
                <label key={value} className={styles.option}>
                  <input
                    type="radio"
                    name={`${id}-${key}`}
                    value={value}
                    checked={(filters[key] ?? "") === value}
                    onChange={() => updateFilter(key, value)}
                  />
                  {optionLabel}
                </label>
              ))}
            </fieldset>
          );
        })}
      </div>
    </div>
  );
}
