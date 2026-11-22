import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import * as api from "../../../Api/HrApi/hrApi";
import { getDepartmentsApi } from "../../../Api/HrApi/departmentsApi";
import AnnouncementsPage from "./AnnouncementsPage";

vi.mock("../../../Api/HrApi/hrApi");
vi.mock("../../../Api/HrApi/departmentsApi");
vi.mock("react-toastify", () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock("react-i18next", () => {
  const t = (key) => key;
  return { useTranslation: () => ({ t, i18n: { language: "en-GB" } }) };
});

const announcement = {
  id: 7, title: "Schedule", description: "Meet at nine.", department_id: 1, department_name: "Engineering",
  created_at: "2026-11-22T10:30:00+03:00", updated_at: "2026-11-22T10:30:00+03:00",
};

beforeEach(() => {
  vi.resetAllMocks();
  getDepartmentsApi.mockResolvedValue({ data: [{ id: 1, name: "Engineering" }] });
  api.getAllAnnouncementsApi.mockResolvedValue({ data: [announcement] });
});

afterEach(cleanup);

it("prefills the announcement and replaces the saved card while preserving publication time", async () => {
  api.updateAnnouncementApi.mockResolvedValue({
    data: { ...announcement, title: "Updated schedule", updated_at: "2026-11-22T11:15:00+03:00" },
  });
  render(<AnnouncementsPage />);
  fireEvent.click(await screen.findByRole("button", { name: "announcements.edit" }));
  const dialog = within(screen.getByRole("dialog"));
  expect(dialog.getByLabelText("announcements.title").value).toBe("Schedule");
  expect(dialog.getByLabelText("announcements.details").value).toBe("Meet at nine.");
  expect(dialog.getByLabelText("announcements.target").value).toBe("1");
  fireEvent.change(dialog.getByLabelText("announcements.title"), { target: { value: "Updated schedule" } });
  fireEvent.click(dialog.getByRole("button", { name: "announcements.saveChanges" }));
  expect(await screen.findByRole("heading", { name: "Updated schedule" })).toBeTruthy();
  expect(screen.queryByRole("heading", { name: "Schedule" })).toBeNull();
  expect(screen.getByText(/10:30/)).toBeTruthy();
  expect(screen.getByText(/11:15/)).toBeTruthy();
  expect(api.updateAnnouncementApi).toHaveBeenCalledWith(7, {
    title: "Updated schedule", description: "Meet at nine.", department_id: 1,
  });
  expect(api.getAllAnnouncementsApi).toHaveBeenCalledTimes(1);
});

it("requires confirmation for delete and removes the card only after persistence", async () => {
  api.deleteAnnouncementApi.mockResolvedValue({ data: null });
  render(<AnnouncementsPage />);
  const remove = await screen.findByRole("button", { name: "announcements.delete" });
  fireEvent.click(remove);
  fireEvent.click(screen.getByRole("button", { name: "common:action.cancel" }));
  expect(api.deleteAnnouncementApi).not.toHaveBeenCalled();
  fireEvent.click(remove);
  fireEvent.click(screen.getByRole("button", { name: "announcements.confirmDelete" }));
  await waitFor(() => expect(screen.queryByRole("heading", { name: "Schedule" })).toBeNull());
  expect(api.deleteAnnouncementApi).toHaveBeenCalledWith(7);
});
