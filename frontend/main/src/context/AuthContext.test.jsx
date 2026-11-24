import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import { toast } from "react-toastify";
import api, { ACCESS_TOKEN_KEY } from "../Api/axios";
import { apiBaseUrl } from "../Api/apiBaseUrl";
import { revokeBrowserRegistration } from "../firebase/registration";
import { AuthProvider, useAuth } from "./AuthContext";

vi.mock("react-toastify", () => ({ toast: { warning: vi.fn() } }));
vi.mock("../firebase/registration", () => ({ revokeBrowserRegistration: vi.fn() }));

afterEach(() => {
  cleanup();
  localStorage.clear();
  vi.restoreAllMocks();
  vi.clearAllMocks();
});

function showSession() {
  function Account() {
    const { user, logout } = useAuth();
    return <><output>{user?.sub ?? "signed out"}</output>{user && <button onClick={logout}>Sign out</button>}</>;
  }
  render(<AuthProvider><Account /></AuthProvider>);
}

function session(userId, role = "HR") {
  const expires = Math.floor(Date.now() / 1000) + 1000;
  return `header.${btoa(JSON.stringify({ sub: userId, exp: expires, roles: [role] }))}.signature`;
}

function unauthorized(config) {
  return Promise.reject(Object.assign(new Error("Unauthorized"), { config, response: { status: 401 } }));
}

it("uses a root API path when no URL is configured and rejects invalid explicit paths", () => {
  expect(apiBaseUrl()).toBe("/api/v1");
  expect(apiBaseUrl("/service/api/v1/")).toBe("/service/api/v1");
  expect(apiBaseUrl(" https://api.example.test/api/v1/ ")).toBe("https://api.example.test/api/v1");
  expect(() => apiBaseUrl("//api.example.test/api/v1")).toThrow("VITE_API_URL");
  expect(() => apiBaseUrl("https://api.example.test/")).toThrow("VITE_API_URL");
});

it("signs out and revokes the device on a 401 from the current session", async () => {
  const token = session(42);
  localStorage.setItem(ACCESS_TOKEN_KEY, token);
  showSession();

  await act(async () => {
    await expect(api.get("notification/unread-count", { adapter: unauthorized })).rejects.toThrow("Unauthorized");
  });

  expect(screen.getByText("signed out")).toBeTruthy();
  expect(revokeBrowserRegistration).toHaveBeenCalledWith(token);
});

it("keeps the current session on a stale 401 or an invalid login", async () => {
  const current = session(42);
  localStorage.setItem(ACCESS_TOKEN_KEY, current);
  showSession();

  await expect(api.get("notification/unread-count", {
    headers: { Authorization: `Bearer ${session(10)}` }, adapter: unauthorized,
  })).rejects.toThrow("Unauthorized");
  await expect(api.post("auth/login", {}, { adapter: unauthorized })).rejects.toThrow("Unauthorized");

  expect(screen.getByText("42")).toBeTruthy();
  expect(localStorage.getItem(ACCESS_TOKEN_KEY)).toBe(current);
  expect(revokeBrowserRegistration).not.toHaveBeenCalled();
});

it.each(["HR", "SUPERVISOR", "TRAINEE"])("revokes the server session on explicit %s sign-out", async (role) => {
  const token = session(42, role);
  localStorage.setItem(ACCESS_TOKEN_KEY, token);
  const request = vi.spyOn(api, "get").mockResolvedValue({ status: 200 });
  showSession();
  fireEvent.click(screen.getByRole("button", { name: "Sign out" }));
  expect(screen.getByText("signed out")).toBeTruthy();
  await waitFor(() => expect(request).toHaveBeenCalledWith("auth/logout", {
    headers: { Authorization: `Bearer ${token}` }, timeout: 8000,
  }));
  expect(revokeBrowserRegistration.mock.invocationCallOrder[0]).toBeLessThan(request.mock.invocationCallOrder[0]);
  expect(request).toHaveBeenCalledTimes(1);
});

it("keeps sign-out local and warns when server revocation cannot be confirmed", async () => {
  localStorage.setItem(ACCESS_TOKEN_KEY, session(42));
  const request = vi.spyOn(api, "get").mockRejectedValue(new Error("Network error"));
  showSession();
  fireEvent.click(screen.getByRole("button", { name: "Sign out" }));
  await waitFor(() => expect(toast.warning).toHaveBeenCalled());
  expect(screen.getByText("signed out")).toBeTruthy();
  expect(request).toHaveBeenCalledTimes(1);
});
