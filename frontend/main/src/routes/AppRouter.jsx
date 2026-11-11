import { Navigate, Route, Routes } from 'react-router-dom';
import AppShell from '../components/layout/AppShell';
import { useAuth } from '../context/AuthContext';
import LoginPage from '../features/auth/LoginPage';
import { roleRegistry } from '../roles';

/** يمنع الوصول إلا لمن يملك الدور المطلوب */
function RequireRole({ role, children }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.roles[0] !== role) return <Navigate to={roleRegistry[user.roles[0]]?.basePath ?? '/login'} replace />;
  return children;
}

function HomeRedirect() {
  const { user } = useAuth();
  const target = user ? roleRegistry[user.roles[0]]?.basePath : '/login';
  return <Navigate to={target ?? '/login'} replace />;
}

export default function AppRouter() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      {Object.values(roleRegistry).map((module) => (
        <Route
          key={module.role}
          path={module.basePath}
          element={
            <RequireRole role={module.role}>
              <AppShell module={module} />
            </RequireRole>
          }
        >

          <Route index element={<Navigate to={module.nav[0].path} replace />} />
          {module.nav.map(({ path, Component }) => (
            <Route key={path} path={path} element={<Component />} />

          ))}

          {(module.extraRoutes || []).map(({ path, Component }) => (
            <Route
              key={path}
              path={path}
              element={<Component />}
            />
          ))}
        </Route>
      ))}

      <Route path="*" element={<HomeRedirect />} />
    </Routes>
  );
}
