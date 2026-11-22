import { afterEach, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { getApplicantAnswers } from "../../../Api/HrApi/applicantsApi";
import { getMyAssignApi } from "../../../Api/SupervisorApi/assignedApi";
import AssignedRequestsPage from "./AssignedRequestsPage";

vi.mock("../../../Api/HrApi/applicantsApi", () => ({ getApplicantAnswers: vi.fn() }));
vi.mock("../../../Api/SupervisorApi/assignedApi", () => ({
  getMyAssignApi: vi.fn(),
  TrainerAcceptOrRejectApplicationApi: vi.fn(),
}));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key) => key }) }));

afterEach(cleanup);

it("shows the submitted question and choice in the assigned application details", async () => {
  getMyAssignApi.mockResolvedValue({ data: [{
    id: 42, full_name: "Sample Applicant", status: "assigned", gpa: 85,
    birth_date: "2001-01-01", submission_date: "2030-01-06",
  }] });
  getApplicantAnswers.mockResolvedValue({ application_form_id: 18, answers: [
    { question_id: 3, prompt: "Preferred topic", numeric_value: null, text_value: null, options: [
      { id: 8, label: "Networks" },
    ] },
  ] });

  render(<AssignedRequestsPage />);
  fireEvent.click(await screen.findByRole("button", { name: "action.viewDetails" }));

  const details = within(await screen.findByRole("dialog"));
  expect(await details.findByText("Preferred topic")).toBeTruthy();
  expect(details.getByText("Networks")).toBeTruthy();
  expect(getApplicantAnswers).toHaveBeenCalledWith(42, expect.any(AbortSignal));
});
