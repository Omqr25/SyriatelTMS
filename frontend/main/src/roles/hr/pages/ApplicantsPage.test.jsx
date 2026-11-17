import React from "react";
import { MemoryRouter, Routes, Route, useLocation } from "react-router-dom";
import {
  render,
  screen,
  cleanup,
  fireEvent,
  waitFor,
} from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import ApplicantsPage from "./ApplicantsPage";
import useApplicants from "../../../hooks/HrHooks/useApplicants";
vi.mock("../../../hooks/HrHooks/useApplicants");
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key) => key, i18n: { dir: () => "ltr" } }),
}));
vi.mock("../components/ApplicantDetailsModal", () => ({
  default: ({ onSchedule }) => (
    <button onClick={onSchedule}>Schedule from details</button>
  ),
}));
vi.mock("../components/AssignDepartmentModal", () => ({ default: () => null }));
vi.mock("../components/ScheduleInterviewModal", () => ({
  default: () => null,
}));
vi.mock("../components/StatusBadge", () => ({ default: () => null }));
afterEach(cleanup);
it.each([
  "pending",
  "assigned",
  "qualified",
  "for interview",
  "interviewed",
  "accepted",
  "active",
  "completed",
  "fired",
  "rejected",
])(
  "restricts rejection for %s and opens details using the API id",
  (status) => {
    const loadApplicant = vi.fn().mockResolvedValue(null);
    const reject = vi.fn();
    useApplicants.mockReturnValue({
      applicants: [
        { id: 42, full_name: "Alice", email: "a@example.com", gpa: 80, status },
      ],
      filters: {},
      pagination: { totalPages: 1 },
      page: 1,
      loadApplicant,
      reject,
    });
    render(
      <MemoryRouter>
        <ApplicantsPage />
      </MemoryRouter>,
    );
    expect(!!screen.queryByRole("button", { name: "applicants.reject" })).toBe(
      [
        "pending",
        "assigned",
        "qualified",
        "for interview",
        "interviewed",
      ].includes(status),
    );
    fireEvent.click(screen.getByRole("button", { name: "Alice" }));
    expect(loadApplicant).toHaveBeenCalledWith(42);
    expect(reject).not.toHaveBeenCalled();
  },
);

it("redirects from applicant details to Assigned Requests with the applicant ID", async () => {
  const applicant = { id: 42, full_name: "Alice", status: "qualified" };
  useApplicants.mockReturnValue({
    applicants: [applicant],
    applicant,
    filters: {},
    pagination: { totalPages: 1 },
    page: 1,
    loadApplicant: vi.fn().mockResolvedValue({ data: applicant }),
  });
  function Target() {
    const location = useLocation();
    return <div>{location.pathname + location.search}</div>;
  }
  render(
    <MemoryRouter initialEntries={["/hr/applicants"]}>
      <Routes>
        <Route path="/hr/applicants" element={<ApplicantsPage />} />
        <Route path="/hr/assigned-requests" element={<Target />} />
      </Routes>
    </MemoryRouter>,
  );
  fireEvent.click(screen.getByRole("button", { name: "Alice" }));
  fireEvent.click(
    await screen.findByRole("button", { name: "Schedule from details" }),
  );
  expect(
    await screen.findByText("/hr/assigned-requests?schedule=42"),
  ).toBeTruthy();
});
