import {  useState } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import Button from "../../components/ui/Button";
import { Field, Input } from "../../components/ui/Field";
import form from "../../components/ui/Form.module.css";
import LanguageSwitcher from "../../components/layout/LanguageSwitcher";
import Logo from "../../components/layout/Logo";
import { useAuth } from "../../context/AuthContext";
import {  roleRegistry, ROLES } from "../../roles";
import styles from "./LoginPage.module.css";
import { toast } from "react-toastify";
import { requestForToken } from "../../firebase/firebase";
import {registerNotificationTokenApi} from '../../Api/notificationApi';

export default function LoginPage() {
  const { t } = useTranslation("auth");
  const c = (key) => t(key, { ns: "common" });
  const { user, login } = useAuth();
  const navigate = useNavigate();
  const [role, setRole] = useState(ROLES.HR);
  const [loading, setLoading] = useState(false);

  const [token, setToken] = useState("");

  if (user)
    return (
      <Navigate to={roleRegistry[user.roles[0]]?.basePath ?? "/"} replace />
    );

  const handleSubmit = async (e) => {
    e.preventDefault();
    const email = new FormData(e.currentTarget).get("email");
    const password = new FormData(e.currentTarget).get("password");
    setLoading(true);
    try {
      const user = await login({
        email,
        password,
      });
      /*
       * التوجيه حسب الدور
       */
      const module = roleRegistry[user.roles[0]];
      console.log("Navigated to module:", module);
      if (!module) {
        throw new Error("هذا الدور غير مدعوم حاليًا");
      }

      const getToken = async () => {
        const permission = await Notification.requestPermission();
        if (permission === "granted") {
          const token = await requestForToken();
          if (token) {
            // console.log("notification Token :", token);
            setToken(token);
            try {
              await registerNotificationTokenApi(token);
              console.log("Notification token registered successfully");
            } catch (error) {
              console.error(
                "Failed to register notification token:",
                error.response?.data || error.message,
              );
            }
          }
        }
      };

      getToken();

      navigate(module.basePath, {
        replace: true,
      });
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "invalid credentials";
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className={styles.page}>
      <aside className={styles.hero}>
        <svg
          className={styles.arc}
          viewBox="0 0 600 900"
          preserveAspectRatio="xMidYMax slice"
          aria-hidden="true"
        >
          <path
            d="M-40 860 C 60 380, 380 120, 660 40"
            fill="none"
            stroke="rgba(255,255,255,.55)"
            strokeWidth="3"
          />
          <path
            d="M-40 900 C 80 430, 400 170, 660 90"
            fill="none"
            stroke="rgba(255,255,255,.22)"
            strokeWidth="26"
            strokeLinecap="round"
          />
        </svg>
        <div className={styles.heroText}>
          <h1>{t("heroTitle")}</h1>
          <p>{t("heroText")}</p>
        </div>
      </aside>

      <main className={styles.side}>
        <div className={styles.lang}>
          <LanguageSwitcher />
        </div>

        <div className={styles.card}>
          <Logo height={64} className={styles.logo} />
          <h2>{t("title")}</h2>
          <p className={styles.subtitle}>{t("subtitle")}</p>

          <form className={form.form} onSubmit={handleSubmit}>
         

            <Field label={c("label.email")}>
              <Input
                type="email"
                name="email"
                required
                defaultValue="training_team@syriatel.test"
              />
            </Field>
            <Field label={c("label.password")}>
              <Input
                type="password"
                name="password"
                required
                defaultValue="12345678"
              />
            </Field>
            <Button type="submit" size="lg" fullWidth loading={loading}>
              {t("submit")}
            </Button>
          </form>
        </div>
      </main>
    </div>
  );
}
