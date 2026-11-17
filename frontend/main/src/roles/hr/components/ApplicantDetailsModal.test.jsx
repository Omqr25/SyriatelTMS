import React from 'react';
import { render, screen, cleanup } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import ApplicantDetailsModal from './ApplicantDetailsModal';
vi.mock('react-i18next', () => ({ useTranslation: () => ({ t: (key) => key }) }));
vi.mock('../../../components/ui/Modal', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('./StatusBadge', () => ({ default: () => null }));
afterEach(cleanup);
it.each(['pending', 'assigned', 'qualified', 'for interview', 'interviewed', 'accepted', 'active', 'rejected', 'completed', 'fired'])('shows only permitted modal actions for %s', (status) => {
  render(<MemoryRouter><ApplicantDetailsModal applicant={{ status, full_name: 'Applicant', birth_date: '1998-01-01', submission_date: '2026-11-17' }} onClose={() => {}} onAssign={() => {}} onSchedule={() => {}} /></MemoryRouter>);
  expect(!!screen.queryByRole('button', { name: 'applicants.scheduleInterview' })).toBe(status === 'qualified');
  expect(!!screen.queryByRole('button', { name: 'applicants.assignToDept' })).toBe(status === 'pending');
  if (status === 'active') expect(screen.getByRole('link').getAttribute('href')).toBe('/hr/trainees');
});
