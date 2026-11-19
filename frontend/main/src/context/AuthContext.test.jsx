import { act, cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import api, { ACCESS_TOKEN_KEY } from "../Api/axios";
import { apiBaseUrl } from "../Api/apiBaseUrl";
import { revokeBrowserRegistration } from "../firebase/registration";
import { AuthProvider, useAuth } from "./AuthContext";

vi.mock("../firebase/registration", () => ({ revokeBrowserRegistration: vi.fn() }));

afterEach(() => {
  cleanup();
  localStorage.clear();
  vi.clearAllMocks();
});

function showSession() {
  function Account() {
    const { user } = useAuth();
    return <output>{user?.sub ?? "signed out"}</output>;
  }
  render(<AuthProvider><Account /></AuthProvider>);
}

function session(userId) {
  const expires = Math.floor(Date.now() / 1000) + 1000;
  return `header.${btoa(JSON.stringify({ sub: userId, exp: expires, roles: ["HR"] }))}.signature`;
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
