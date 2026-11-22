import { cleanup, render, screen, waitFor } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import { getApplicantAnswers } from "../../../Api/HrApi/applicantsApi";
import ApplicantAnswers from "./ApplicantAnswers";

vi.mock("../../../Api/HrApi/applicantsApi", () => ({ getApplicantAnswers: vi.fn() }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key) => key === "applicationForm.optionSeparator" ? ", " : key }) }));

afterEach(cleanup);

it("renders saved prompts and choice labels while preserving numeric zero", async () => {
  getApplicantAnswers.mockResolvedValue({ application_form_id: 18, answers: [
    { question_id: 1, prompt: "Experience", numeric_value: "0.0000", text_value: null, options: [] },
    { question_id: 2, prompt: "Preferred areas", numeric_value: null, text_value: null, options: [
      { id: 1, label: "Support" }, { id: 2, label: "Operations" },
    ] },
  ] });

  render(<ApplicantAnswers applicationId={5} />);
  expect(await screen.findByText("Experience")).toBeTruthy();
  expect(screen.getByText("0.0000")).toBeTruthy();
  expect(screen.getByText("Support, Operations")).toBeTruthy();
});

it("leaves pre-feature records without an additional-answer section", async () => {
  getApplicantAnswers.mockResolvedValue({ application_form_id: null, answers: [] });
  render(<ApplicantAnswers applicationId={4} />);
  await waitFor(() => expect(screen.queryByRole("status")).toBeNull());
  expect(screen.queryByRole("region")).toBeNull();
});
