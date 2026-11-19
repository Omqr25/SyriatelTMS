import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import Registration from "./Registration";
import * as api from "../../../Api/HrApi/registrationApi";
import { toast } from "react-toastify";

vi.mock("../../../Api/HrApi/registrationApi");
vi.mock("react-toastify", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key) => key }) }));

const program = {
  id: 7, name: "Winter Training", start_date: "2030-02-01", end_date: "2030-03-31",
  application_start_date: "2030-01-01", application_end_date: "2030-01-31",
  phase: "upcoming", has_applicants: false, delete_block: null, revision: 1,
};

function openList(canArchive = false, fields = {}) {
  api.getProgramsApi.mockResolvedValue({ can_manage: true, can_archive: canArchive, programs: [{ ...program, ...fields }] });
  render(<Registration />);
  return screen.findByRole("heading", { name: program.name });
}

const button = (name) => screen.getByRole("button", { name });

beforeEach(() => vi.resetAllMocks());
afterEach(cleanup);

it("shows creation, editing, and deletion to HR without archive permission", async () => {
  await openList(false);
  expect(button("registration.add")).toBeTruthy();
  expect(within(screen.getByRole("heading", { name: program.name }).closest("section")).getByText("registration.phases.upcoming")).toBeTruthy();
  for (const date of ["2/1/30", "3/31/30", "1/1/30", "1/31/30"]) {
    expect(screen.getByText(date)).toBeTruthy();
  }
  expect(button("registration.edit").disabled).toBe(false);
  expect(button("registration.delete").disabled).toBe(false);
  expect(screen.queryByRole("button", { name: "registration.archive" })).toBeNull();
});

it("creates from the existing form once and updates the list on persistence", async () => {
  await openList(false);
  fireEvent.click(button("registration.add"));
  const dialog = within(screen.getByRole("dialog"));
  const values = { name: "Spring", startDate: "2/1/31", endDate: "3/31/31", startDateRegistration: "1/1/31", endDateRegistration: "1/31/31" };
  for (const [label, value] of Object.entries(values)) {
    fireEvent.change(dialog.getByLabelText(`registration.${label}`), { target: { value } });
  }
  let resolve;
  api.createProgramsApi.mockImplementation(() => new Promise((done) => { resolve = done; }));
  const form = dialog.getByRole("button", { name: "common:action.create" }).form;
  fireEvent.submit(form);
  fireEvent.submit(form);
  expect(api.createProgramsApi).toHaveBeenCalledTimes(1);
  expect(api.createProgramsApi).toHaveBeenCalledWith({
    name: "Spring", start_date: "2031-02-01", end_date: "2031-03-31",
    application_start_date: "2031-01-01", application_end_date: "2031-01-31",
  });
  resolve([{ ...program, id: 8, name: "Spring" }, program]);
  expect(await screen.findByRole("heading", { name: "Spring" })).toBeTruthy();
});

it("prefills the edit form, warns for changed dates, and updates the card after confirmation", async () => {
  await openList(false, { has_applicants: true });
  api.updateProgramApi.mockResolvedValue({ ...program, start_date: "2030-02-03", revision: 2 });
  fireEvent.click(button("registration.edit"));
  const start = screen.getByLabelText("registration.startDate");
  expect(start.value).toBe("2/1/30");
  fireEvent.change(start, { target: { value: "2030-02-02" } });
  expect(button("common:action.save").disabled).toBe(true);
  fireEvent.click(screen.getByRole("checkbox"));
  fireEvent.change(start, { target: { value: "2030-02-03" } });
  expect(button("common:action.save").disabled).toBe(true);
  fireEvent.click(screen.getByRole("checkbox"));
  fireEvent.click(button("common:action.save"));
  expect(await screen.findByText("2/3/30")).toBeTruthy();
  expect(api.updateProgramApi).toHaveBeenCalledWith(7, expect.objectContaining({ expected_revision: 1, confirm_date_change: true }));
  expect(screen.queryByRole("dialog")).toBeNull();
});

it("keeps the form open when a new applicant registers during an edit", async () => {
  await openList();
  api.updateProgramApi.mockRejectedValueOnce({ response: { status: 409, data: { data: { reason: "confirm_dates" } } } });
  fireEvent.click(button("registration.edit"));
  fireEvent.change(screen.getByLabelText("registration.endDate"), { target: { value: "2030-04-30" } });
  fireEvent.click(button("common:action.save"));
  expect(await screen.findByRole("checkbox")).toBeTruthy();
  expect(screen.getByLabelText("registration.endDate").value).toBe("4/30/30");
  expect(button("common:action.save").disabled).toBe(true);
});

it("shows chronology errors and field-level server errors without discarding edits", async () => {
  await openList();
  fireEvent.click(button("registration.edit"));
  const start = screen.getByLabelText("registration.startDate");
  fireEvent.change(start, { target: { value: "2030-01-31" } });
  fireEvent.click(button("common:action.save"));
  const alert = screen.getByRole("alert");
  expect(alert.textContent).toContain("registration.trainingAfterRegistration");
  expect(alert.className).toContain("error");
  expect(start.getAttribute("aria-invalid")).toBe("true");
  expect(start.getAttribute("aria-describedby")).toBe(alert.id);
  expect(api.updateProgramApi).not.toHaveBeenCalled();
  fireEvent.change(start, { target: { value: "2030-02-04" } });
  api.updateProgramApi.mockRejectedValueOnce({ response: { status: 422, data: { errors: { name: ["Duplicate program"] } } } });
  fireEvent.click(button("common:action.save"));
  expect(await screen.findByText("Duplicate program")).toBeTruthy();
  expect(screen.getByLabelText("registration.startDate").value).toBe("2/4/30");
});

it("flags an impossible date before saving and shows shorter dates after blur", async () => {
  await openList();
  fireEvent.click(button("registration.edit"));
  const end = screen.getByLabelText("registration.endDate");
  fireEvent.change(end, { target: { value: "2/29/30" } });
  fireEvent.click(button("common:action.save"));
  expect(screen.getByRole("alert").textContent).toBe("registration.invalidDate");
  expect(api.updateProgramApi).not.toHaveBeenCalled();

  fireEvent.change(end, { target: { value: "03/05/2030" } });
  fireEvent.blur(end);
  expect(end.value).toBe("3/5/30");
  expect(screen.queryByRole("alert")).toBeNull();
});

it.each(["opened", "linked", "unknown_history"])("disables deletion with the reason %s", async (reason) => {
  await openList(false, { delete_block: reason });
  expect(button("registration.edit").disabled).toBe(false);
  expect(button("registration.delete").disabled).toBe(true);
  expect(screen.getByText(`registration.blocks.${reason}`)).toBeTruthy();
});

it("requires a separate delete confirmation and removes only after success", async () => {
  await openList();
  fireEvent.click(button("registration.delete"));
  fireEvent.click(button("common:action.cancel"));
  expect(api.deleteProgramApi).not.toHaveBeenCalled();
  fireEvent.click(button("registration.delete"));
  fireEvent.click(within(screen.getByRole("dialog")).getByRole("button", { name: "registration.delete" }));
  await waitFor(() => expect(screen.queryByRole("heading", { name: program.name })).toBeNull());
  expect(api.deleteProgramApi).toHaveBeenCalledWith(7, 1);
  expect(toast.success).toHaveBeenCalledWith("registration.deleted");
});

it("searches names and filters the lifecycle or archived programs without refetching", async () => {
  api.getProgramsApi.mockResolvedValue({ can_manage: true, programs: [
    program, { ...program, id: 8, name: "Summer Training", phase: "completed" },
    { ...program, id: 9, name: "Old Training", phase: "archived", archived_at: "2028-01-03", delete_block: "archived" },
  ] });
  render(<Registration />);
  await screen.findByRole("heading", { name: program.name });
  const search = screen.getByRole("searchbox");
  fireEvent.change(search, { target: { value: " SUMMER " } });
  expect(screen.getByRole("heading", { name: "Summer Training" })).toBeTruthy();
  expect(screen.queryByRole("heading", { name: program.name })).toBeNull();
  fireEvent.change(screen.getByRole("combobox"), { target: { value: "upcoming" } });
  expect(screen.getByRole("status").textContent).toContain("registration.noMatches");
  fireEvent.change(search, { target: { value: "" } });
  fireEvent.change(screen.getByRole("combobox"), { target: { value: "archived" } });
  expect(screen.getByRole("heading", { name: "Old Training" })).toBeTruthy();
  expect(button("registration.delete").disabled).toBe(true);
  expect(api.getProgramsApi).toHaveBeenCalledTimes(1);
});

it("archives and restores the card through separate confirmations", async () => {
  await openList(true);
  const archived = { ...program, archived_at: "2030-01-02", delete_block: "archived", phase: "archived", revision: 2 };
  api.setProgramArchivedApi.mockResolvedValueOnce(archived).mockResolvedValueOnce({ ...program, revision: 3 });
  fireEvent.click(button("registration.archive"));
  expect(api.setProgramArchivedApi).not.toHaveBeenCalled();
  fireEvent.click(within(screen.getByRole("dialog")).getByRole("button", { name: "registration.archive" }));
  await waitFor(() => expect(screen.queryByRole("heading", { name: program.name })).toBeNull());
  expect(api.setProgramArchivedApi).toHaveBeenCalledWith(7, true, 1);
  fireEvent.change(screen.getByRole("combobox"), { target: { value: "archived" } });
  fireEvent.click(button("registration.restore"));
  fireEvent.click(within(screen.getByRole("dialog")).getByRole("button", { name: "registration.restore" }));
  await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
  expect(api.setProgramArchivedApi).toHaveBeenCalledWith(7, false, 2);
  fireEvent.change(screen.getByRole("combobox"), { target: { value: "current" } });
  expect(screen.getByRole("heading", { name: program.name })).toBeTruthy();
});
