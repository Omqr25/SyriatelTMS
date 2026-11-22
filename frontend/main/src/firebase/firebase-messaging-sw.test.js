import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";
import { expect, it, vi } from "vitest";

const source = readFileSync("public/firebase-messaging-sw.js", "utf8");

function worker(owner = "42") {
  const callbacks = {};
  let background;
  const registration = { getNotifications: vi.fn(async () => []), showNotification: vi.fn() };
  const clients = { matchAll: vi.fn(async () => []), openWindow: vi.fn() };
  runInNewContext(source, {
    self: {
      registration,
      clients,
      navigator: { language: "en" },
      location: { origin: "https://tms.example.test" },
      firebaseWorkerConfig: { apiKey: "key", projectId: "project", appId: "app", messagingSenderId: "sender" },
      addEventListener: (name, callback) => { callbacks[name] = callback; },
    },
    caches: { match: async () => ({ text: async () => owner }) },
    firebase: {
      initializeApp: vi.fn(),
      messaging: () => ({ onBackgroundMessage: (callback) => { background = callback; } }),
    },
    importScripts: vi.fn(),
    URL,
  });

  function trigger(name, details) {
    let work;
    callbacks[name]({ ...details, waitUntil: (promise) => { work = promise; } });
    return work;
  }

  return { registration, clients, background, trigger };
}

const payload = { notification_id: "7", recipient_id: "42", inbox_path: "/trainee/notification" };

it("displays once across foreground and background delivery of the same inbox item", async () => {
  const push = worker();
  await Promise.all([
    push.background({ data: payload }),
    push.trigger("message", { data: { type: "show-inbox-push", payload } }),
  ]);
  expect(push.registration.showNotification).toHaveBeenCalledTimes(1);
  expect(push.registration.showNotification).toHaveBeenCalledWith("Syriatel TMS", expect.objectContaining({
    tag: "inbox-42-7", data: { path: "/trainee/notification" },
  }));
});

it("drops pop-ups for the previous account and avoids displaying legacy pushes twice", async () => {
  const push = worker("43");
  await push.background({ data: payload });
  await push.background({ data: { ...payload, recipient_id: "43" }, notification: { title: "Existing pop-up" } });
  expect(push.registration.showNotification).not.toHaveBeenCalled();
});

it("opens only approved same-origin notification destinations", async () => {
  const push = worker();
  const note = { tag: "inbox-42-7", close: vi.fn() };
  await push.trigger("notificationclick", { notification: { ...note, data: { path: "/supervisor/notification" } } });
  expect(push.clients.openWindow).toHaveBeenLastCalledWith("https://tms.example.test/supervisor/notification");
  await push.trigger("notificationclick", { notification: { ...note, data: { path: "https://elsewhere.example" } } });
  expect(push.clients.openWindow).toHaveBeenLastCalledWith("https://tms.example.test/");
});
