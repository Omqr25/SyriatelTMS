import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { BellRing, LogOut, Menu } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../context/AuthContext';
import Badge from '../ui/Badge';
import Logo from './Logo';
import LanguageSwitcher from './LanguageSwitcher';
import styles from './AppShell.module.css';

import { useNavigate } from "react-router-dom";
/**
 * الهيكل المشترك لكل الأدوار: شريط جانبي + شريط علوي + محتوى الصفحة.
 * يُغذّى بـ module (انظر src/roles/index.js) فيعرض قائمة الدور المناسب.
 */
export default function AppShell({ module }) {
  const { t } = useTranslation();
  const { logout } = useAuth();
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);
  // console.log("module:",module)
  useEffect(() => setMenuOpen(false), [pathname]);

  const current = module?.nav?.find(
  (item) =>
    item &&
    typeof item.path === "string" &&
    pathname.startsWith(`${module.basePath}/${item.path}`)
);
  const roleName = t(`role.${module.role}`);

  return (
    <div className={styles.shell}>
      {menuOpen && <div className={styles.backdrop} onClick={() => setMenuOpen(false)} />}

      <aside className={`${styles.sidebar} ${menuOpen ? styles.sidebarOpen : ''}`}>
        <div className={styles.brand}>
          <Logo height={50} />
          <span className={styles.brandName}>{t('appTitle')}</span>
        </div>

        <nav className={styles.nav}>
          {module.nav.map(({ path, icon: Icon, labelKey }) => (
            <NavLink
              key={path}
              to={`${module.basePath}/${path}`}
              className={({ isActive }) => `${styles.link} ${isActive ? styles.active : ''}`}
            >
              <Icon size={19} />
              <span>{t(labelKey)}</span>
            </NavLink>
          ))}
        </nav>

        <button type="button" className={styles.logout} onClick={logout}>
          <LogOut size={19} />
          <span>{t('action.logout')}</span>
        </button>
      </aside>

      <div className={styles.main}>
        <header className={styles.topbar}>
          <div className={styles.titleGroup}>
            <button
              type="button"
              className={styles.menuBtn}
              onClick={() => setMenuOpen(true)}
              aria-label={t('action.openMenu')}
            >
              <Menu size={22} />
            </button>
            <h2>{current ? t(current.labelKey) : ''}</h2>
            <Badge tone="red">{roleName}</Badge>
          </div>

          <div className={styles.tools}>
              <button    onClick={() => navigate("notification") } className={styles.BellRing}  >
                    <BellRing  ></BellRing>
              </button>
            <LanguageSwitcher />
            <div className={styles.user}>
              <span className={styles.avatar}>{module.role.slice(0, 2)}</span>
              <span className={styles.userName}>{roleName}</span>
            </div>
            <div>
            
            </div>
          </div>
        </header>

        <main className={styles.content}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
