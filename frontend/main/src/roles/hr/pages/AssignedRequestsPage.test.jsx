import React from "react";
import { MemoryRouter, useLocation } from "react-router-dom";
import {
  render,
  screen,
  fireEvent,
  waitFor,
  cleanup,
} from "@testing-library/react";
import { beforeEach, afterEach, expect, it, vi } from "vitest";
import AssignedRequestsPage from "./AssignedRequestsPage";
import useAssign from "../../../hooks/HrHooks/useAssigned";
import { createInterviewApi } from "../../../Api/HrApi/interviewApi";
import { toast } from "react-toastify";
vi.mock("../../../hooks/HrHooks/useAssigned");
vi.mock("../../../Api/HrApi/interviewApi", () => ({
  createInterviewApi: vi.fn(),
}));
vi.mock("react-toastify", () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key) => key }),
}));
const row = {
  id: 42,
  name: "Alice",
  status: "qualified",
  supervisors: { id: 7, name: "Supervisor" },
};
let refresh;
beforeEach(() => {
  refresh = vi.fn().mockResolvedValue({ data: [] });
  useAssign.mockReturnValue({
    loading: false,
    assigned: [row],
    error: null,
    getAssigned: refresh,
  });
});
afterEach(cleanup);
function Location() {
  const location = useLocation();
  return (
    <div data-testid="location">{location.pathname + location.search}</div>
  );
}
function renderPage(url = "/hr/assigned-requests?schedule=42") {
  return render(
    <MemoryRouter initialEntries={[url]}>
      <Location />
      <AssignedRequestsPage />
    </MemoryRouter>,
  );
}
it("opens the selected qualified applicant and consumes the scheduling link", async () => {
  renderPage();
  expect(await screen.findByRole("dialog")).toBeTruthy();
  expect(screen.getByLabelText("applicants.supervisorName").value).toBe("7");
  expect(screen.getByTestId("location").textContent).toBe(
    "/hr/assigned-requests",
  );
  fireEvent.click(screen.getByRole("button", { name: "action.close" }));
  await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
});
it.each(["assigned", "accepted", "active", "missing"])(
  "does not open stale scheduling links for %s",
  async (status) => {
    useAssign.mockReturnValue({
      loading: false,
      assigned: status === "missing" ? [] : [{ ...row, status }],
      error: null,
      getAssigned: refresh,
    });
    renderPage();
    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith("assigned.scheduleUnavailable"),
    );
    expect(screen.queryByRole("dialog")).toBeNull();
  },
);
it("waits for the assigned list before selecting the applicant", async () => {
  useAssign.mockReturnValue({
    loading: true,
    assigned: [],
    error: null,
    getAssigned: refresh,
  });
  const view = renderPage();
  expect(toast.error).not.toHaveBeenCalled();
  useAssign.mockReturnValue({
    loading: false,
    assigned: [row],
    error: null,
    getAssigned: refresh,
  });
  view.rerender(
    <MemoryRouter initialEntries={["/hr/assigned-requests?schedule=42"]}>
      <Location />
      <AssignedRequestsPage />
    </MemoryRouter>,
  );
  expect(await screen.findByRole("dialog")).toBeTruthy();
});
it("retains the form on conflict, then saves the selected appointment and refreshes the list", async () => {
  createInterviewApi
    .mockRejectedValueOnce({
      response: { data: { message: "Supervisor has another appointment" } },
    })
    .mockResolvedValueOnce({ message: "Scheduled" });
  renderPage();
  await screen.findByRole("dialog");
  fireEvent.change(screen.getByLabelText("applicants.interviewDate"), {
    target: { value: "2026-12-01" },
  });
  fireEvent.change(screen.getByLabelText("applicants.interviewTime"), {
    target: { value: "10:00" },
  });
  fireEvent.change(screen.getByLabelText("applicants.interviewLocation"), {
    target: { value: "HQ" },
  });
  fireEvent.click(screen.getByRole("button", { name: "action.save" }));
  await waitFor(() =>
    expect(toast.error).toHaveBeenCalledWith(
      "Supervisor has another appointment",
    ),
  );
  expect(screen.getByLabelText("applicants.interviewLocation").value).toBe(
    "HQ",
  );
  fireEvent.click(screen.getByRole("button", { name: "action.save" }));
  await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
  expect(createInterviewApi).toHaveBeenLastCalledWith(42, {
    trainer_id: "7",
    time: "2026-12-01 10:00",
    location: "HQ",
  });
  expect(refresh).toHaveBeenCalledWith(true);
  expect(toast.success).toHaveBeenCalledWith("Scheduled");
});
