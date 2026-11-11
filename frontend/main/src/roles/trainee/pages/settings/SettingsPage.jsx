
import { useTranslation } from "react-i18next";
import {
  Eye,
  EyeOff,
  LockKeyhole,
  ShieldCheck,
} from "lucide-react";
import Button from "../../../../components/ui/Button.jsx"

import styles from "./SettingsPage.module.css";
import SettingsHooks from "../../../../hooks/TraineeHooks/settingsHooks";

export default function SettingsPage({
  loading = false,
}) {
  const { t, i18n } = useTranslation('trainee');
  const {
        loadingAction,
        showCurrentPassword,
        showNewPassword,
        showConfirmPassword,
        setShowCurrentPassword,
        setShowNewPassword,
        setShowConfirmPassword,
        errors,
        formData,
        setFormData,
        setErrors,
        handleSubmit
    }=SettingsHooks()

  const updateField = (field, value) => {
    setFormData((previous) => ({
      ...previous,
      [field]: value,
    }));

    setErrors((previous) => ({
      ...previous,
      [field]: "",
      ...(field === "newPassword" ? { confirmPassword: "" } : {}),
    }));

  };


  const passwordFields = [
    {
      name: "currentPassword",
      label: t("settings.currentPassword", {
        defaultValue: "Current password",
      }),
      placeholder: t("settings.currentPlaceholder", {
        defaultValue: "Enter your current password",
      }),
      visible: showCurrentPassword,
      toggle: () => setShowCurrentPassword((value) => !value),
    },
    {
      name: "newPassword",
      label: t("settings.newPassword", {
        defaultValue: "New password",
      }),
      placeholder: t("settings.newPlaceholder", {
        defaultValue: "Enter your new password",
      }),
      visible: showNewPassword,
      toggle: () => setShowNewPassword((value) => !value),
    },
    {
      name: "confirmPassword",
      label: t("settings.confirmPassword", {
        defaultValue: "Confirm new password",
      }),
      placeholder: t("settings.confirmPlaceholder", {
        defaultValue: "Re-enter your new password",
      }),
      visible: showConfirmPassword,
      toggle: () =>
        setShowConfirmPassword((value) => !value),
    },
  ];

  return (
  <main className={styles.page} dir={i18n.dir()}>
    <header className={styles.pageHeader}>
      <div className={styles.headerIcon}>
        <LockKeyhole size={25} />
      </div>

      <div>
        <h1 className={styles.pageTitle}>
          {t("settings.title", {
            defaultValue: "Settings",
          })}
        </h1>

        <p className={styles.pageDescription}>
          {t("settings.description", {
            defaultValue:
              "Manage your account security and password.",
          })}
        </p>
      </div>
    </header>

    <div className={styles.layout}>
      {/* <aside className={styles.sidebar}>
        <p className={styles.sidebarTitle}>
          {t("settings.account", {
            defaultValue: "Account",
          })}
        </p>

        <Button
          type="Button"
          className={`${styles.tab} `}
          aria-current="page"
        >
          <LockKeyhole size={18} />
          <span>
            {t("settings.changePassword", {
              defaultValue: "Change Password",
            })}
          </span>
        </Button>
      </aside> */}

      <section className={styles.content}>
        <div className={styles.cardHeader}>
          <div>
            <h2>
              {t("settings.changePassword", {
                defaultValue: "Change Password",
              })}
            </h2>
            <p>
              {t("settings.passwordDescription", {
                defaultValue:
                  "Update your password to keep your account secure.",
              })}
            </p>
          </div>
          <div className={styles.securityIcon}>
            <ShieldCheck size={23} />
          </div>
        </div>

        <div className={styles.divider} />
          <form
            className={styles.form}
            onSubmit={(event) => {
              event.preventDefault();
              handleSubmit();
            }}
          >
            {passwordFields.map((field) => (
              <div className={styles.field} key={field.name + field.label}>
                <label htmlFor={field.name}>
                  {t(field.label, {
                    defaultValue: field.label,
                  })}
                  <span className={styles.required}>*</span>
                </label>

                <div
                  className={`${styles.inputWrapper} ${
                    errors[field.name] ? styles.inputError : ""
                  }`}
                >
                  <input
                    id={field.name}
                    name={field.name}
                    type={field.visible ? "text" : "password"}
                    value={formData[field.name]}
                    onChange={(event) =>
                      updateField(field.name, event.target.value)
                    }
                    placeholder={t(field.placeholder, {
                      defaultValue: field.placeholder,
                    })}
                    autoComplete={
                      field.name === "currentPassword"
                        ? "current-password"
                        : "new-password"
                    }
                    aria-invalid={Boolean(errors[field.name])}
                    aria-describedby={
                      errors[field.name]
                        ? `${field.name}-error`
                        : undefined
                    }
                    disabled={loading}
                    required
                  />

                  <button
                    type="button"
                    className={styles.visibilityButton}
                    onClick={field.toggle}
                    aria-label={
                      field.visible
                        ? t("settings.hidePassword", {
                            defaultValue: "Hide password",
                          })
                        : t("settings.showPassword", {
                            defaultValue: "Show password",
                          })
                    }
                    disabled={loading}
                  >
                    {field.visible ? (
                      <EyeOff size={19} />
                    ) : (
                      <Eye size={19} />
                    )}
                  </button>
                </div>

                {errors[field.name] && (
                  <p
                    className={styles.errorText}
                    id={`${field.name}-error`}
                    role="alert"
                  >
                    {t(errors[field.name], {
                      defaultValue: errors[field.name],
                    })}
                  </p>
                )}
              </div>
            ))}

            <p className={styles.passwordHint}>
              {t("settings.passwordHint", {
                defaultValue:
                  "Use at least 8 characters. Avoid reusing your current password.",
              })}
            </p>

            <div className={styles.formActions}>
              <Button
                type="submit"
                className={styles.saveButton}
                disabled={loadingAction}
                loading={loadingAction}
              >
                {t("settings.saveChanges")}
              </Button>
            </div>
          </form>
      </section>
    </div>
  </main>
);
}
