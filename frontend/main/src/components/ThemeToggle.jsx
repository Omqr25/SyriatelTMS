import { useState } from "react";
import { Moon, Sun } from "lucide-react";
import { useTranslation } from "react-i18next";
import "../styles/header-controls.css";

const storageKey = "syriatel:theme";

export function initializeTheme() {
  let savedTheme;
  try {
    savedTheme = localStorage.getItem(storageKey);
  } catch {
    // The toggle still works when browser storage is unavailable.
  }
  const systemTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  document.documentElement.dataset.theme = savedTheme === "dark" || savedTheme === "light" ? savedTheme : systemTheme;
}

export default function ThemeToggle() {
  const { t } = useTranslation();
  const [dark, setDark] = useState(() => document.documentElement.dataset.theme === "dark");

  function toggleTheme() {
    const next = dark ? "light" : "dark";
    document.documentElement.dataset.theme = next;
    setDark(next === "dark");
    try {
      localStorage.setItem(storageKey, next);
    } catch {
      // Keep the selection for this page.
    }
  }

  return (
    <button type="button" className="headerControl themeToggle" onClick={toggleTheme}
      aria-label={t("theme.darkMode")} aria-pressed={dark}
      title={t(dark ? "theme.switchLight" : "theme.switchDark")}>
      <span className="themeIcons" aria-hidden="true">
        <Moon className="themeMoon" size={19} />
        <Sun className="themeSun" size={20} />
      </span>
    </button>
  );
}
