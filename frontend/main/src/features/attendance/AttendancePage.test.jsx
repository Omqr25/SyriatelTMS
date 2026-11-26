// @vitest-environment jsdom

import { act, cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, beforeEach, expect, test, vi } from "vitest";
import { MemoryRouter } from "react-router-dom";
import i18n from "../../i18n";
import { AttendancePage } from "./AttendancePage";
import { getAttendance, attendanceActions } from "./attendanceApi";

vi.mock("./attendanceApi", async (original) => ({
  ...(await original()),
  getAttendance: vi.fn(),
  attendanceActions: Object.fromEntries(
    ["recordAbsence", "editAbsence", "deleteAbsence", "requestLeave", "editLeave", "cancelLeave", "cancelApproved", "approve", "reject"]
      .map((name) => [name, vi.fn()]),
  ),
}));
vi.mock("react-toastify", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const counts = { total_absences: 2, excused_absences: 1, unexcused_absences: 1 };
const zero = { id: 42, name: "Zero Trainee", total_absences: 0, excused_absences: 0, unexcused_absences: 0 };
const absence = {
  id: 71, trainee_name: "Test Trainee", trainee_id: 31, reason: "Reported absence", date: "25-11-2026",
  classification: "unexcused", can_manage: true,
};
const leave = {
  id: 61, trainee: { id: 31, name: "Test Trainee" }, reason: "Appointment",
  start_date: "2026-11-26", end_date: "2026-11-26", submitted_at: "2026-11-25T10:00:00+03:00",
  status: "pending", can_edit: true, can_cancel: true, can_decide: true,
};
const bounds = { can_request: true, earliest_leave_date: "2026-11-25", latest_leave_date: "2026-12-15" };

function packet(rows, extraData = null, current = 1, last = 1) {
  return { data: rows, meta: { current_page: current, last_page: last }, extraData };
}

function mount(role, url = "/attendance") {
  return render(
    <MemoryRouter initialEntries={[url]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <AttendancePage role={role} />
    </MemoryRouter>,
  );
}

function clickedTab(name) {
  fireEvent.click(screen.getByRole("tab", { name }));
}

beforeEach(async () => {
  vi.resetAllMocks();
  await i18n.changeLanguage("en");
  getAttendance.mockImplementation(async (path) => {
    if (path === "attendance/options") return { data: { programs: [{ id: 19, name: "Test program" }], supervisors: [] } };
    if (path === "attendance/absences") return packet([absence], { counts });
    if (path === "attendance/trainees") return packet([zero]);
    if (path === "attendance/trainees/42") return { data: zero };
    if (path === "attendance/leaves") return packet([]);
    if (path === "vacation_request/listForTrainer") return packet([leave]);
    if (path === "vacation_request/listForTrainee") return packet([leave], bounds);
    if (path === "absence/eligible-trainees") return { data: [{ id: 31, name: "Test Trainee" }] };
    throw new Error(`Unexpected request ${path}`);
  });
});
afterEach(cleanup);

test.each([
  ["SUPERVISOR", ["Absences", "Leave Requests", "Trainee Records"], true],
  ["HR", ["Absences", "Approved Leaves", "Trainee Records"], false],
  ["TRAINEE", ["My Absences", "My Leave Requests"], false],
])("loads just the active tab and shows the approved sections for %s", async (role, tabs, canRecord) => {
  mount(role);
  await screen.findByText("Reported absence");
  expect(screen.getAllByRole("tab").map((tab) => tab.textContent)).toEqual(tabs);
  expect(Boolean(screen.queryByRole("button", { name: "Record absence" }))).toBe(canRecord);
  expect(Boolean(screen.queryByRole("button", { name: "Edit reason" }))).toBe(canRecord);
  expect(getAttendance.mock.calls.map(([path]) => path).sort()).toEqual(
    role === "TRAINEE" ? ["attendance/absences"] : ["attendance/absences", "attendance/options"],
  );
  if (role === "TRAINEE") expect(screen.getByText("Total recorded absences").nextElementSibling.textContent).toBe("2");
});

test("HR sees only read actions and loads an individual trainee's accepted-leave history on demand", async () => {
  mount("HR");
  await screen.findByText("Reported absence");
  clickedTab("Trainee Records");
  await screen.findByText(zero.name);
  expect(screen.getAllByText("0")).toHaveLength(3);
  expect(screen.getByLabelText("All supervisors")).toBeTruthy();
  fireEvent.change(screen.getByLabelText("All training programs"), { target: { value: "19" } });
  fireEvent.click(screen.getByRole("button", { name: "Total recorded absences" }));
  await waitFor(() => expect(getAttendance.mock.calls.some(([path, query]) => path === "attendance/trainees"
    && new URLSearchParams(query).get("program_id") === "19"
    && new URLSearchParams(query).get("sort") === "total_absences")).toBe(true));

  fireEvent.click(screen.getByRole("button", { name: "View record" }));
  await screen.findByText("Email address");
  await waitFor(() => expect(getAttendance.mock.calls.some(([path, query]) =>
    path === "attendance/leaves" && query.includes("trainee_id=42"))).toBe(true));
  expect(screen.queryByRole("button", { name: "Edit reason" })).toBeNull();
  expect(screen.queryByRole("button", { name: "Cancel approved leave" })).toBeNull();
  fireEvent.click(screen.getByRole("button", { name: "Back to list" }));
  await screen.findByText(zero.name);
  expect(screen.getByLabelText("All training programs").value).toBe("19");
  expect(screen.getByRole("columnheader", { name: "Total recorded absences" }).getAttribute("aria-sort")).toBe("ascending");
});

test("keeps focus during debounced HR search and discards an out-of-order response", async () => {
  const defaultGet = getAttendance.getMockImplementation();
  let finishSlow;
  getAttendance.mockImplementation((path, query, signal) => {
    const search = new URLSearchParams(query).get("search");
    if (search === "slow") return new Promise((resolve) => { finishSlow = resolve; });
    if (search === "fast") return Promise.resolve(packet([{ ...absence, reason: "Latest match" }]));
    return defaultGet(path, query, signal);
  });
  mount("HR");
  await screen.findByText("Reported absence");
  const field = screen.getByRole("searchbox");
  field.focus();
  fireEvent.change(field, { target: { value: "slow" } });
  expect(finishSlow).toBeUndefined();
  await waitFor(() => expect(finishSlow).toBeTypeOf("function"));
  fireEvent.change(field, { target: { value: "fast" } });
  await screen.findByText("Latest match");
  await act(async () => finishSlow(packet([{ ...absence, reason: "Stale match" }])));
  expect(screen.queryByText("Stale match")).toBeNull();
  expect(document.activeElement).toBe(field);
  fireEvent.click(screen.getByRole("button", { name: "Reset" }));
  await screen.findByText("Reported absence");
  expect(field.value).toBe("");
});

test("loads eligible trainees on demand and submits an individual absence without a date picker", async () => {
  attendanceActions.recordAbsence.mockResolvedValue({});
  mount("SUPERVISOR");
  await screen.findByText("Reported absence");
  expect(getAttendance.mock.calls.some(([path]) => path === "absence/eligible-trainees")).toBe(false);
  fireEvent.click(screen.getByRole("button", { name: "Record absence" }));
  const dialog = screen.getByRole("dialog");
  await within(dialog).findByRole("option", { name: "Test Trainee" });
  expect(dialog.querySelector('input[type="date"]')).toBeNull();
  fireEvent.change(within(dialog).getByLabelText("Trainee name"), { target: { value: "31" } });
  fireEvent.change(within(dialog).getByLabelText("Reason"), { target: { value: "An individual reason" } });
  fireEvent.click(within(dialog).getByRole("button", { name: "Save" }));
  await waitFor(() => expect(attendanceActions.recordAbsence).toHaveBeenCalledWith("31", { reason: "An individual reason" }));
});

test("uses authoritative leave actions and bounds and submits a single-day edit", async () => {
  attendanceActions.editLeave.mockResolvedValue({});
  mount("TRAINEE");
  clickedTab("My Leave Requests");
  fireEvent.click(await screen.findByRole("button", { name: "Edit request" }));
  const dialog = screen.getByRole("dialog");
  const start = within(dialog).getByLabelText("Start date");
  const end = within(dialog).getByLabelText("End date");
  expect([start.value, end.value, start.min, end.max]).toEqual([
    "2026-11-26", "2026-11-26", "2026-11-25", "2026-12-15",
  ]);
  fireEvent.click(within(dialog).getByRole("button", { name: "Save" }));
  await waitFor(() => expect(attendanceActions.editLeave).toHaveBeenCalledWith(61, {
    reason: "Appointment", start_date: "2026-11-26", end_date: "2026-11-26",
  }));
});

test("returns to the previous page after pending cancellation removes the last filtered item", async () => {
  let cancelled = false;
  getAttendance.mockImplementation(async (path, query) => {
    if (path === "vacation_request/listForTrainee") {
      const page = Number(new URLSearchParams(query).get("page") || "1");
      return packet(cancelled && page === 2 ? [] : [{ ...leave, reason: page === 1 ? "First page" : "Last pending" }],
        bounds, page, cancelled ? 1 : 2);
    }
    return packet([]);
  });
  attendanceActions.cancelLeave.mockImplementation(async () => { cancelled = true; });
  mount("TRAINEE", "/attendance?tab=leave&status=pending");
  fireEvent.click(await screen.findByRole("button", { name: "2" }));
  await screen.findByText("Last pending");
  fireEvent.click(screen.getByRole("button", { name: "Cancel request" }));
  fireEvent.click(within(screen.getByRole("dialog")).getByRole("button", { name: "Cancel request" }));
  await screen.findByText("First page");
  expect(getAttendance.mock.calls.filter(([path]) => path === "vacation_request/listForTrainee")
    .map(([, query]) => new URLSearchParams(query).get("status"))).toEqual(["pending", "pending", "pending", "pending"]);
});

test("supervisor Arabic status reflects the completed decision and prevents a competing click", async () => {
  await i18n.changeLanguage("ar");
  const defaultGet = getAttendance.getMockImplementation();
  let finish;
  let rejected = false;
  getAttendance.mockImplementation((path, query, signal) => rejected && path === "vacation_request/listForTrainer"
    ? Promise.resolve(packet([{ ...leave, status: "rejected", can_decide: false, can_cancel: false }]))
    : defaultGet(path, query, signal));
  attendanceActions.reject.mockImplementation(() => new Promise((resolve) => { finish = resolve; }));
  mount("SUPERVISOR", "/attendance?tab=leave");
  const reject = await screen.findByRole("button", { name: "رفض" });
  const accept = screen.getByRole("button", { name: "قبول" });
  expect(document.documentElement.dir).toBe("rtl");
  const table = within(screen.getByRole("table"));
  expect(table.getByText("قيد الانتظار").className).toContain("amber");
  fireEvent.click(reject);
  expect(accept.disabled).toBe(true);
  fireEvent.click(accept);
  expect(attendanceActions.approve).not.toHaveBeenCalled();
  rejected = true;
  await act(async () => finish({}));
  expect((await table.findByText("مرفوض")).className).toContain("red");
  expect(screen.queryByRole("button", { name: "قبول" })).toBeNull();
});

test("a supervisor must confirm cancellation of an accepted leave", async () => {
  const defaultGet = getAttendance.getMockImplementation();
  getAttendance.mockImplementation((path, query, signal) => path === "vacation_request/listForTrainer"
    ? Promise.resolve(packet([{ ...leave, status: "accepted", can_edit: false, can_decide: false }]))
    : defaultGet(path, query, signal));
  attendanceActions.cancelApproved.mockResolvedValue({});
  mount("SUPERVISOR", "/attendance?tab=leave");
  fireEvent.click(await screen.findByRole("button", { name: "Cancel approved leave" }));
  expect(attendanceActions.cancelApproved).not.toHaveBeenCalled();
  fireEvent.click(within(screen.getByRole("dialog")).getByRole("button", { name: "Cancel approved leave" }));
  await waitFor(() => expect(attendanceActions.cancelApproved).toHaveBeenCalledWith(61, {}));
});

test("can retry a failed request and displays the empty state", async () => {
  getAttendance.mockRejectedValueOnce(new Error("Offline")).mockResolvedValue(packet([], { counts }));
  mount("TRAINEE");
  expect((await screen.findByRole("alert")).textContent).toContain("Records could not be loaded");
  fireEvent.click(screen.getByRole("button", { name: "Try again" }));
  await screen.findByText("No recorded absences found.");
});
