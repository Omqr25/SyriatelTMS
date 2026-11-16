import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useTranslation } from "react-i18next";
import Button from "../../components/ui/Button";
import { Field, Input } from "../../components/ui/Field";
import LanguageSwitcher from "../../components/layout/LanguageSwitcher";
import Logo from "../../components/layout/Logo";
import form from "../../components/ui/Form.module.css";
import styles from "./LoginPage.module.css";
import { requestPasswordReset, resetPassword } from "../../Api/passwordResetApi";

export default function PasswordRecoveryPage({ reset = false }) {
  const { t } = useTranslation("auth");
  const [params] = useSearchParams();
  const token = params.get("token") || "";
  const email = params.get("email") || "";
  const invalidLink = reset && (!token || !email);
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event) {
    event.preventDefault();
    if (loading || invalidLink) return;
    const fields = new FormData(event.currentTarget);
    setError("");
    if (reset && fields.get("password") !== fields.get("password_confirmation")) {
      setError(t("recovery.passwordMismatch"));
      return;
    }
    setLoading(true);
    try {
      if (reset) {
        await resetPassword({
          token,
          email,
          password: fields.get("password"),
          password_confirmation: fields.get("password_confirmation"),
        });
      } else {
        await requestPasswordReset(fields.get("email").trim());
      }
      setSuccess(true);
    } catch (err) {
      const data = err.response?.data;
      const validationErrors = data?.errors ? Object.values(data.errors).flat().join(" ") : "";
      setError(validationErrors || data?.message || t("recovery.requestFailed"));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={styles.page}>
      <aside className={styles.hero}>
        <div className={styles.heroText}>
          <h1>{t("heroTitle")}</h1>
          <p>{t("heroText")}</p>
        </div>
      </aside>
      <main className={styles.side}>
        <div className={styles.lang}><LanguageSwitcher /></div>
        <div className={styles.card}>
          <Logo height={64} className={styles.logo} />
          <h2>{t(reset ? "recovery.resetTitle" : "recovery.forgotTitle")}</h2>
          <p className={styles.subtitle}>{t(reset ? "recovery.resetDescription" : "recovery.forgotDescription")}</p>
          {success ? (
            <p className={styles.notice} role="status">
              {t(reset ? "recovery.resetSuccess" : "recovery.emailSent")}
            </p>
          ) : invalidLink ? (
            <p className={styles.error} role="alert">{t("recovery.invalidLink")}</p>
          ) : (
            <form className={form.form} onSubmit={handleSubmit}>
              <Field label={t("label.email", { ns: "common" })}>
                <Input type="email" name="email" autoComplete="email" required
                  defaultValue={reset ? email : ""} readOnly={reset} disabled={loading} />
              </Field>
              {reset && (
                <>
                  <Field label={t("recovery.newPassword")}>
                    <Input type="password" name="password" autoComplete="new-password"
                      minLength={8} required disabled={loading} aria-describedby="password-hint" />
                  </Field>
                  <p id="password-hint" className={styles.hint}>{t("recovery.passwordHint")}</p>
                  <Field label={t("recovery.confirmPassword")}>
                    <Input type="password" name="password_confirmation" autoComplete="new-password"
                      minLength={8} required disabled={loading} />
                  </Field>
                </>
              )}
              {error && <p className={styles.error} role="alert">{error}</p>}
              <Button type="submit" size="lg" fullWidth loading={loading}>
                {t(reset ? "recovery.resetSubmit" : "recovery.sendLink")}
              </Button>
            </form>
          )}
          {reset && !success && (
            <Link className={styles.link} to="/forgot-password">{t("recovery.requestNewLink")}</Link>
          )}
          <Link className={styles.link} to="/login">{t("recovery.backToLogin")}</Link>
        </div>
      </main>
    </div>
  );
}
