import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import Registration from "./Registration";
import * as exportApi from "../../../Api/HrApi/applicantExportsApi";
import * as api from "../../../Api/HrApi/registrationApi";
import { toast } from "react-toastify";

vi.mock("../../../Api/HrApi/registrationApi");
vi.mock("../../../Api/HrApi/applicantExportsApi");
vi.mock("react-toastify", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key) => key, i18n: { resolvedLanguage: "en" } }) }));

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
  for (const date of ["1/2/30", "31/3/30", "1/1/30", "31/1/30"]) {
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
  expect(dialog.getAllByPlaceholderText("D/M/YY")).toHaveLength(4);
  const values = { name: "Spring", startDate: "1/2/31", endDate: "31/3/31", startDateRegistration: "1/1/31", endDateRegistration: "31/1/31" };
  for (const [label, value] of Object.entries(values)) {
    fireEvent.change(dialog.getByLabelText(`registration.${label}`), { target: { value } });
  }
  let resolve;
  api.createProgramsApi.mockImplementation(() => new Promise((done) => { resolve = done; }));
  const form = dialog.getByRole("button", { name: "registration.publishNow" }).form;
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

it("saves a new program as a draft and shows it in the draft list", async () => {
  await openList(true);
  fireEvent.click(button("registration.add"));
  const dialog = within(screen.getByRole("dialog"));
  const values = {
    name: "Spring draft", startDate: "1/2/31", endDate: "31/3/31", startDateRegistration: "1/1/31", endDateRegistration: "31/1/31",
  };
  for (const [label, value] of Object.entries(values)) {
    fireEvent.change(dialog.getByLabelText(`registration.${label}`), { target: { value } });
  }
  api.createProgramsApi.mockResolvedValue([
    program, { ...program, id: 8, name: "Spring draft", phase: "archived", archived_at: "2030-01-02", delete_block: "archived" },
  ]);
  fireEvent.click(dialog.getByRole("button", { name: "registration.saveDraft" }));
  expect(await screen.findByRole("heading", { name: "Spring draft" })).toBeTruthy();
  expect(api.createProgramsApi).toHaveBeenCalledWith(expect.objectContaining({ draft: true }));
  expect(screen.getByRole("combobox").value).toBe("archived");
  expect(button("registration.restore")).toBeTruthy();
});

it("prefills the edit form, warns for changed dates, and updates the card after confirmation", async () => {
  await openList(false, { has_applicants: true });
  api.updateProgramApi.mockResolvedValue({ ...program, start_date: "2030-02-03", revision: 2 });
  fireEvent.click(button("registration.edit"));
  const start = screen.getByLabelText("registration.startDate");
  expect(start.value).toBe("1/2/30");
  fireEvent.change(start, { target: { value: "2030-02-02" } });
  expect(button("common:action.save").disabled).toBe(true);
  fireEvent.click(screen.getByRole("checkbox"));
  fireEvent.change(start, { target: { value: "2030-02-03" } });
  expect(button("common:action.save").disabled).toBe(true);
  fireEvent.click(screen.getByRole("checkbox"));
  fireEvent.click(button("common:action.save"));
  expect(await screen.findByText("3/2/30")).toBeTruthy();
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
  expect(screen.getByLabelText("registration.endDate").value).toBe("30/4/30");
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
  expect(screen.getByLabelText("registration.startDate").value).toBe("4/2/30");
});

it("flags an impossible date before saving and shows shorter dates after blur", async () => {
  await openList();
  fireEvent.click(button("registration.edit"));
  const end = screen.getByLabelText("registration.endDate");
  fireEvent.change(end, { target: { value: "29/2/30" } });
  fireEvent.click(button("common:action.save"));
  expect(screen.getByRole("alert").textContent).toBe("registration.invalidDate");
  expect(api.updateProgramApi).not.toHaveBeenCalled();

  fireEvent.change(end, { target: { value: "05/03/2030" } });
  fireEvent.blur(end);
  expect(end.value).toBe("5/3/30");
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

it("saves ordered choice questions and publishes the persisted revision", async () => {
  const draft = { form: null, revision: 0, published_at: null, can_edit: true, requires_history_confirmation: true };
  const question = { id: 25, prompt: "Preferred area", type: "multiple_choice", required: true, min_value: null, max_value: null };
  const saved = { ...draft, revision: 1, form: { id: 8, questions: [{
    ...question, options: [{ id: 4, label: "Operations" }, { id: 3, label: "Support" }],
  }] } };
  api.getApplicationForm.mockResolvedValue(draft);
  api.saveApplicationForm.mockResolvedValue(saved);
  api.publishApplicationForm.mockResolvedValue({ ...saved, can_edit: false, published_at: "2030-01-01T00:00:00Z" });

  await openList();
  fireEvent.click(button("applicationForm.manage"));
  const dialog = within(screen.getByRole("dialog"));
  fireEvent.click(await dialog.findByRole("button", { name: "applicationForm.addQuestion" }));
  fireEvent.change(dialog.getByLabelText("applicationForm.prompt"), { target: { value: question.prompt } });
  fireEvent.change(dialog.getByLabelText("applicationForm.type"), { target: { value: question.type } });
  fireEvent.click(dialog.getByLabelText("applicationForm.required"));
  fireEvent.click(dialog.getByRole("button", { name: "applicationForm.addOption" }));
  fireEvent.click(dialog.getByRole("button", { name: "applicationForm.addOption" }));
  const optionInputs = dialog.getAllByRole("textbox", { name: "applicationForm.option" });
  fireEvent.change(optionInputs[0], { target: { value: "Support" } });
  fireEvent.change(optionInputs[1], { target: { value: "Operations" } });
  fireEvent.click(dialog.getAllByRole("button", { name: "applicationForm.moveUp" })[1]);

  expect(dialog.getByRole("button", { name: "applicationForm.publish" }).disabled).toBe(true);
  fireEvent.click(dialog.getByRole("button", { name: "applicationForm.saveDraft" }));
  await waitFor(() => expect(api.saveApplicationForm).toHaveBeenCalledWith(7, {
    expected_revision: 0,
    questions: [{
      prompt: question.prompt, type: question.type, required: true,
      min_value: null, max_value: null, options: ["Operations", "Support"],
    }],
  }, "en"));
  await waitFor(() => expect(dialog.getByRole("button", { name: "applicationForm.publish" }).disabled).toBe(false));
  fireEvent.click(dialog.getByRole("button", { name: "applicationForm.publish" }));
  const confirm = dialog.getByRole("button", { name: "applicationForm.confirmButton" });
  expect(confirm.disabled).toBe(true);
  fireEvent.click(dialog.getByLabelText("applicationForm.confirmUnopened"));
  fireEvent.click(confirm);
  await waitFor(() => expect(api.publishApplicationForm).toHaveBeenCalledWith(7, {
    expected_revision: 1, confirm_unopened: true,
  }, "en"));
  await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
});


it("reopens the selected program’s persisted export and downloads the ready part independently", async () => {
  const snapshot = {
    id: "snapshot-7", applicant_count: 3, capture_state: "ready", excel_state: "none",
    documents_state: "ready", document_warnings: 1, operation: null,
    created_at: "2030-01-01T12:00:00+03:00", expires_at: "2030-01-02T12:00:00+03:00",
    tasks: [{ id: 53, kind: "zip", part: 1, state: "ready", bytes: 90000 }],
  };
  exportApi.listApplicantExports.mockResolvedValue([snapshot]);
  await openList();
  fireEvent.click(button("applicantExports.open"));
  expect(await screen.findByRole("button", { name: "download" })).toBeTruthy();
  expect(exportApi.listApplicantExports).toHaveBeenCalledWith(7, expect.any(AbortSignal));
  fireEvent.click(button("download"));
  expect(exportApi.downloadApplicantArtifact).toHaveBeenCalledWith("snapshot-7", 53);
  fireEvent.click(button("action.close"));
  fireEvent.click(button("applicantExports.open"));
  expect(await screen.findByRole("button", { name: "download" })).toBeTruthy();
  exportApi.requestApplicantExport.mockResolvedValue("snapshot-7");
  fireEvent.click(button("excel"));
  await waitFor(() => expect(exportApi.requestApplicantExport).toHaveBeenCalledWith(
    7, { kind: "excel", fresh: false, snapshot_id: "snapshot-7" },
  ));
});
