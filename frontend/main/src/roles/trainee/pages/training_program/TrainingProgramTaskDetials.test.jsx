import React from 'react';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import TaskDetailsPage from './TrainingProgramTaskDetials';
import api from '../../../../Api/axios';
import { startTask, submitEvidence } from '../../../../Api/Trainee/trainingProgramApi';

vi.mock('react-i18next', () => ({ useTranslation: () => ({ t: key => key, i18n: { language: 'en' } }) }));
vi.mock('react-router-dom', async importOriginal => ({ ...await importOriginal(), useParams: () => ({ taskId: '7' }) }));
vi.mock('../../../../Api/axios', () => ({ default: { get: vi.fn() } }));
vi.mock('../../../../Api/Trainee/trainingProgramApi', () => ({ startTask: vi.fn(), submitEvidence: vi.fn() }));

let task;
const show = () => render(<MemoryRouter><TaskDetailsPage /></MemoryRouter>);
beforeEach(() => {
  vi.clearAllMocks();
  task = { id: 7, name: 'Build attendance API', is_mine: true, status: 'pending', can_start: true,
    can_submit: false, deadline_iso: new Date(Date.now() + 86400000).toISOString(),
    trainee: { name: 'Leen' }, attachments: [], subtasks: [], evidences: [] };
  api.get.mockImplementation(async () => ({ data: { data: { task } } }));
});
afterEach(cleanup);

describe('trainee task workspace', () => {
  it('starts an assigned task and reloads the authoritative actions', async () => {
    startTask.mockImplementation(async () => { task = { ...task, status: 'in progress', can_start: false, can_submit: true }; });
    show();
    fireEvent.click(await screen.findByRole('button', { name: 'start' }));
    await screen.findByRole('button', { name: 'submit' });
    expect(startTask).toHaveBeenCalledWith('7');
    expect(screen.queryByRole('button', { name: 'start' })).toBeNull();
    expect(api.get).toHaveBeenCalledTimes(2);
  });

  it('validates PDFs, submits for review, and retains the revision feedback', async () => {
    const old = { id: 1, submitted_at: '2026-11-17T10:00:00Z', feedback: { status: 'need fix', feedback: 'Add validation tests.' } };
    task = { ...task, status: 'need fix', can_start: false, can_submit: true, evidences: [old] };
    submitEvidence.mockImplementation(async () => { task = { ...task, status: 'for review', can_submit: false,
      evidences: [old, { id: 2, submitted_at: '2026-11-17T11:00:00Z', feedback: null }] }; });
    show();
    const input = await screen.findByLabelText('choose');
    fireEvent.change(input, { target: { files: [new File(['text'], 'work.txt', { type: 'text/plain' })] } });
    expect(await screen.findByRole('alert')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'resubmit' }).disabled).toBe(true);
    const pdf = new File(['%PDF-1.4'], 'corrected.pdf', { type: 'application/pdf' });
    fireEvent.change(input, { target: { files: [pdf] } });
    expect(screen.getByRole('button', { name: 'resubmit' }).disabled).toBe(false);
    // jsdom does not populate a native file input value from its FileList.
    fireEvent.submit(input.closest('form'));
    await screen.findByText('submission 2');
    expect(submitEvidence).toHaveBeenCalledWith('7', pdf);
    expect(screen.getByText('Add validation tests.')).toBeTruthy();
    expect(screen.getAllByRole('button', { name: 'download' })).toHaveLength(2);
    expect(screen.queryByLabelText('choose')).toBeNull();
  });

  it('keeps team tasks read only and hides private evidence', async () => {
    task = { ...task, is_mine: false, can_start: false, evidences: [{ id: 9, feedback: { feedback: 'Private feedback' } }] };
    show();
    await screen.findByText('readOnly');
    expect(screen.queryByRole('button', { name: 'start' })).toBeNull();
    expect(screen.queryByText('Private feedback')).toBeNull();
    expect(screen.queryByLabelText('choose')).toBeNull();
  });

  it('locks expired work even when action data is stale', async () => {
    task = { ...task, deadline_iso: '2000-01-01T00:00:00Z' };
    show();
    await screen.findByText('overdue');
    expect(screen.queryByRole('button', { name: 'start' })).toBeNull();
  });

  it('recovers from a load error with Retry', async () => {
    api.get.mockRejectedValueOnce({ response: { data: { message: 'Service unavailable' } } });
    show();
    await screen.findByText('Service unavailable');
    fireEvent.click(screen.getByRole('button', { name: 'retry' }));
    await waitFor(() => expect(screen.getByRole('heading', { name: task.name })).toBeTruthy());
  });
});
