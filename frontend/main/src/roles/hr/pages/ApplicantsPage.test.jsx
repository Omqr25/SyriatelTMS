import React from "react";
import { MemoryRouter, Routes, Route, useLocation } from "react-router-dom";
import {
  render,
  screen,
  cleanup,
  fireEvent,
  waitFor,
  within,
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


it("shows score explanations without flagging eligible scores", () => {
  useApplicants.mockReturnValue({
    applicants: [
      { id: 1, full_name: "Low", gpa: "69.99", english_test_score: "0.00" },
      { id: 2, full_name: "Pending", gpa: "70.00", english_test_score: null },
      { id: 3, full_name: "Eligible", gpa: "70.00", english_test_score: "45.00" },
    ],
    filters: {},
    pagination: { totalPages: 1 },
  });
  render(<MemoryRouter><ApplicantsPage /></MemoryRouter>);

  const detailsLabel = "applicants.admission.scoreDetails";
  const low = within(screen.getByRole("row", { name: /Low/ }));
  const [gpa, english] = low.getAllByRole("button", { name: detailsLabel });
  fireEvent.pointerEnter(gpa);
  expect(screen.getByRole("tooltip").textContent).toBe("applicants.admission.lowGpa");
  fireEvent.keyDown(gpa, { key: "Escape" });
  expect(screen.queryByRole("tooltip")).toBeNull();
  fireEvent.focus(english);
  expect(screen.getByRole("tooltip").textContent).toBe("applicants.admission.lowEnglish");
  expect(low.getByText("0.00")).toBeTruthy();
  fireEvent.blur(english);

  const pending = within(screen.getByRole("row", { name: /Pending/ }));
  fireEvent.click(pending.getByRole("button", { name: detailsLabel }));
  expect(screen.getByRole("tooltip").textContent).toBe("applicants.admission.missingEnglish");
  expect(pending.getByText("—")).toBeTruthy();
  expect(within(screen.getByRole("row", { name: /Eligible/ })).queryByRole("button", { name: detailsLabel }))
    .toBeNull();
});

it("uses separate admission radio groups and marks only the active sortable header", () => {
  const updateFilter = vi.fn();
  const toggleSort = vi.fn();
  const resetFilters = vi.fn();
  useApplicants.mockReturnValue({
    applicants: [],
    filters: { gpa_eligibility: "eligible", english_eligibility: "pending" },
    sort: { key: "gpa", direction: "asc" },
    pagination: { totalPages: 1 },
    updateFilter,
    resetFilters,
    toggleSort,
  });
  render(<MemoryRouter><ApplicantsPage /></MemoryRouter>);

  const admission = screen.getByRole("button", { name: /applicants.admission.label/ });
  expect(admission.textContent).toContain("2");
  fireEvent.click(admission);
  const gpa = within(screen.getByRole("group", { name: "common:label.gpa" }));
  const english = within(screen.getByRole("group", { name: "englishScores:score" }));
  expect(gpa.getByRole("radio", { name: "applicants.admission.eligible" }).checked).toBe(true);
  expect(english.getByRole("radio", { name: "applicants.admission.pending" }).checked).toBe(true);
  fireEvent.click(gpa.getByRole("radio", { name: "applicants.admission.notEligible" }));
  expect(updateFilter).toHaveBeenCalledWith("gpa_eligibility", "not_eligible");
  fireEvent.pointerDown(screen.getByRole("table"));
  expect(admission.getAttribute("aria-expanded")).toBe("false");

  const header = screen.getByRole("columnheader", { name: "label.gpa" });
  expect(header.getAttribute("aria-sort")).toBe("ascending");
  fireEvent.click(within(header).getByRole("button"));
  expect(toggleSort).toHaveBeenCalledWith("gpa");
  for (const label of ["label.email", "label.actions"]) {
    expect(within(screen.getByRole("columnheader", { name: label })).queryByRole("button")).toBeNull();
  }
  fireEvent.click(screen.getByRole("button", { name: "action.reset" }));
  expect(resetFilters).toHaveBeenCalledTimes(1);
});
