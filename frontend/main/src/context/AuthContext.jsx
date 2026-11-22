

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";

import api, { ACCESS_TOKEN_KEY as STORAGE_KEY } from "../Api/axios";
import { revokeBrowserRegistration } from "../firebase/registration";
import { decodeJwt, isTokenExpired } from "../features/auth/auth.utils";

const AuthContext = createContext(null);


/*
 * قراءة JWT من localStorage عند تشغيل التطبيق
 */
function getStoredToken() {
  const token = localStorage.getItem(STORAGE_KEY);

  if (!token) {
    return null;
  }

  // إذا انتهت صلاحية JWT نحذفه
  if (isTokenExpired(token)) {
    void revokeBrowserRegistration(token);
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }

  return token;
}

export function AuthProvider({ children }) {
  const [token, setToken] = useState(getStoredToken);
  

  /*
   * استخراج بيانات المستخدم من JWT
   */
  const user = useMemo(() => {
    if (!token) {
      return null;
    }

    return decodeJwt(token);
  }, [token]);

  /*
   * تسجيل الدخول
   */
  const login = useCallback(async ({ email, password }) => {
    const response = await api.post("auth/login", {
      email,
      password,
    });
    const accessToken = response.data.data.token;
    if (!accessToken) {
      throw new Error("Access token is missing");
    }

    /*
     * التأكد أن JWT صالح وغير منتهي
     */
    if (isTokenExpired(accessToken)) {
      throw new Error("Access token is expired");
    }

    /*
     * استخراج بيانات المستخدم
     */
    const decodedUser = decodeJwt(accessToken);

    if (!decodedUser) {
      throw new Error("Invalid access token");
    }

    if (!decodedUser.roles[0]) {
      throw new Error("User role is missing");
    }

    /*
     * تخزين JWT
     */
    const previous = localStorage.getItem(STORAGE_KEY);
    if (previous && String(decodeJwt(previous)?.sub) !== String(decodedUser.sub)) {
      // Queue cleanup before the new session can register this browser.
      void revokeBrowserRegistration(previous);
    }
    localStorage.setItem(STORAGE_KEY, accessToken);

    /*
     * تحديث React state
     */
    setToken(accessToken);

    return decodedUser;
  }, []);

  /*
   * تسجيل الخروج
   */
  const logout = useCallback(() => {
    const previous = localStorage.getItem(STORAGE_KEY);
    if (previous) void revokeBrowserRegistration(previous);
    localStorage.removeItem(STORAGE_KEY);
    setToken(null);
  }, []);


  useEffect(() => {
    const handleForbidden = () => {
      const current = localStorage.getItem(STORAGE_KEY);
      if (!current || isTokenExpired(current) || !decodeJwt(current)?.roles?.[0]) logout();
    };
    const syncSession = (event) => {
      if (event.key === STORAGE_KEY || event.key === null) setToken(getStoredToken());
    };

    window.addEventListener("auth:unauthorized", logout);
    window.addEventListener("auth:forbidden", handleForbidden);
    window.addEventListener("storage", syncSession);
    return () => {
      window.removeEventListener("auth:unauthorized", logout);
      window.removeEventListener("auth:forbidden", handleForbidden);
      window.removeEventListener("storage", syncSession);
    };
  }, [logout]);


  const value = useMemo(
    () => ({
      token,
      user,
      isAuthenticated: Boolean(token && user),
      login,
      logout,
    }),
    [token, user, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}




export const useAuth = () => {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider");
  }

  return context;
};
