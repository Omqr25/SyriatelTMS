// @vitest-environment jsdom

import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, expect, test, vi } from "vitest";
import i18n from "../../../i18n";
import { getAbsentAndSearchApi } from "../../../Api/HrApi/hrApi";
import { getVacationsRequests, canceltVacation, updateVacation } from "../../../Api/Trainee/vacationApi";
import { AcceptOrRejectVacationRequestApi, getAllAbsentApi, getEligibleAbsenceTrainees, getVacationRequestsApi } from "../../../Api/SupervisorApi/supervisorApi";
import AttendancePage from "../../hr/pages/AttendancePage";
import VacationPage from "../../trainee/pages/vacation/VacationPage";
import AbsentPage from "./AbsentPage";
import VacationRequest from "./VacationRequest";

vi.mock("../../../Api/HrApi/hrApi", () => ({ getAbsentAndSearchApi: vi.fn() }));
vi.mock("../../../Api/Trainee/vacationApi", () => ({
  getVacationsRequests: vi.fn(), canceltVacation: vi.fn(), updateVacation: vi.fn(), requestVacation: vi.fn(),
}));
vi.mock("../../../Api/SupervisorApi/supervisorApi", () => ({
  AcceptOrRejectVacationRequestApi: vi.fn(), getVacationRequestsApi: vi.fn(),
  getAllAbsentApi: vi.fn(), getEligibleAbsenceTrainees: vi.fn(),
}));
vi.mock("react-toastify", () => ({ toast: { success: vi.fn(), error: vi.fn(), warn: vi.fn() } }));

const leave = {
  id: 31,
  trainee: { id: 42, name: "Test Trainee" },
  reason: "Appointment",
  start_date: "2026-11-26",
  end_date: "2026-11-26",
  submitted_at: "2026-11-25T10:00:00+03:00",
  status: "pending",
  can_edit: true,
  can_cancel: true,
  can_decide: true,
};

const leaveBounds = {
  can_request: true,
  earliest_leave_date: "2026-11-26",
  latest_leave_date: "2026-12-15",
};

const page = (data, current = 1, last = 1, extraData = leaveBounds) => ({
  data,
  meta: { current_page: current, last_page: last, total: last > 1 ? 26 : data.length },
  extraData,
});

beforeEach(async () => {
  vi.resetAllMocks();
  await i18n.changeLanguage("en");
});
afterEach(cleanup);

test("renders authoritative leave actions and date bounds, and submits a single-day edit", async () => {
  getVacationsRequests.mockResolvedValue(page([
    leave,
    { ...leave, id: 32, reason: "Earlier request", can_edit: false, can_cancel: false, status: "accepted" },
  ]));
  updateVacation.mockResolvedValue({ message: "Updated" });

  render(<VacationPage />);

  const update = await screen.findByRole("button", { name: "Update" });
  expect(screen.getAllByRole("button", { name: "Cancel Vacation" })).toHaveLength(1);
  expect(screen.getByText("Accepted").className).toContain("green");
  expect(screen.getByText("Pending").className).toContain("amber");

  fireEvent.click(update);
  const start = screen.getByLabelText("Start Date");
  const end = screen.getByLabelText("End Date");
  expect([start.value, end.value, start.min, end.max]).toEqual([
    "2026-11-26", "2026-11-26", "2026-11-26", "2026-12-15",
  ]);

  fireEvent.submit(start.closest("form"));
  await waitFor(() => expect(updateVacation).toHaveBeenCalledWith(31, {
    reason: "Appointment", start_date: "2026-11-26", end_date: "2026-11-26",
  }));
});

test("after cancellation, falls back to the previous page if the current page disappeared", async () => {
  getVacationsRequests.mockResolvedValueOnce(page([{ ...leave, id: 30, reason: "First page" }], 1, 2))
    .mockResolvedValueOnce(page([leave], 2, 2))
    .mockResolvedValueOnce(page([], 2, 1))
    .mockResolvedValueOnce(page([{ ...leave, id: 30, reason: "First page" }]));
  canceltVacation.mockResolvedValue({ message: "Cancelled" });

  render(<VacationPage />);
  fireEvent.click(await screen.findByRole("button", { name: "2" }));
  await screen.findByText("Appointment");

  fireEvent.click(screen.getByRole("button", { name: "Cancel Vacation" }));
  fireEvent.click(screen.getByRole("button", { name: "Cancel Request" }));
  await screen.findByText("First page");

  expect(canceltVacation).toHaveBeenCalledWith(31);
  expect(getVacationsRequests.mock.calls.map(([index]) => index)).toEqual([1, 2, 2, 1]);
});

test("shows Arabic status labels for supervisors and prevents a competing click during a decision", async () => {
  await i18n.changeLanguage("ar");
  getVacationRequestsApi.mockResolvedValueOnce(page([leave]))
    .mockResolvedValueOnce(page([{ ...leave, status: "rejected", can_decide: false }]));
  let complete;
  AcceptOrRejectVacationRequestApi.mockImplementation(() => new Promise((resolve) => { complete = resolve; }));
  render(<VacationRequest />);

  const reject = await screen.findByRole("button", { name: "رفض طلب الإجازة" });
  const approve = screen.getByRole("button", { name: "قبول طلب الإجازة" });
  expect(screen.getByText("قيد الانتظار").className).toContain("amber");
  fireEvent.click(reject);
  expect(approve.disabled).toBe(true);
  expect(reject.disabled).toBe(true);
  fireEvent.click(approve);
  expect(AcceptOrRejectVacationRequestApi.mock.calls).toEqual([[31, "rejected"]]);

  await act(async () => complete({ message: "Rejected" }));
  expect((await screen.findByText("مرفوض")).className).toContain("red");
  expect(screen.queryByRole("button", { name: "قبول طلب الإجازة" })).toBeNull();
});

test("paginates HR attendance with the applied search, independent of edits to the search input", async () => {
  const row = { id: 1, trainee_name: "First result", date: "25-11-2026", reason: "Test only", status: "absent" };
  getAbsentAndSearchApi.mockResolvedValueOnce(page([row], 1, 2))
    .mockResolvedValueOnce(page([{ ...row, trainee_name: "Filtered result" }], 1, 2))
    .mockResolvedValueOnce(page([{ ...row, trainee_name: "Next filtered result" }], 2, 2));
  render(<AttendancePage />);
  await screen.findByText("First result");

  const search = screen.getByRole("textbox");
  fireEvent.change(search, { target: { value: "First" } });
  fireEvent.click(screen.getByRole("button", { name: "Search" }));
  await screen.findByText("Filtered result");
  fireEvent.change(search, { target: { value: "Unapplied" } });
  fireEvent.click(screen.getByRole("button", { name: "2" }));
  await screen.findByText("Next filtered result");

  expect(getAbsentAndSearchApi.mock.calls).toEqual([[1], [1, "First"], [2, "First"]]);
});

test("loads the attendance-specific trainee picker when opening the absence form", async () => {
  getAllAbsentApi.mockResolvedValue(page([]));
  getEligibleAbsenceTrainees.mockResolvedValue({ data: [{ id: 42, name: "Training Trainee" }] });

  render(<AbsentPage />);
  fireEvent.click(await screen.findByRole("button", { name: i18n.t("attendance.add", { ns: "hr" }) }));

  const option = await screen.findByRole("option", { name: /Training Trainee/ });
  expect(option.value).toBe("42");
  expect(getEligibleAbsenceTrainees).toHaveBeenCalledTimes(1);
});
