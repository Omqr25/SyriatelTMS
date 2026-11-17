import { act, renderHook, waitFor, cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import useApplicants from './useApplicants';
import { searchApplicants, getApplicant, assignApplicant, rejectApplicant } from '../../Api/HrApi/applicantsApi';
vi.mock('../../Api/HrApi/applicantsApi', () => ({ searchApplicants: vi.fn(), getApplicant: vi.fn(), assignApplicant: vi.fn(), rejectApplicant: vi.fn() }));
vi.mock('react-toastify', () => ({ toast: { error: vi.fn() } }));
const response = (name) => ({ data: [{ id: 1, full_name: name }], meta: { total: 1, total_pages: 1 } });
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { promise, resolve }; };
afterEach(cleanup);
beforeEach(() => searchApplicants.mockResolvedValue(response('initial')));
describe('HR applicants requests', () => {
  it('loads once and ignores stale filter responses', async () => {
    const old = deferred();
    searchApplicants.mockReturnValueOnce(old.promise);
    const { result } = renderHook(() => useApplicants());
    expect(searchApplicants).toHaveBeenCalledTimes(1);
    act(() => result.current.updateFilter('city', 'damascus'));
    await waitFor(() => expect(result.current.applicants[0]?.full_name).toBe('initial'));
    await act(async () => old.resolve(response('stale')));
    expect(result.current.applicants[0].full_name).toBe('initial');
    expect(searchApplicants.mock.calls[0][0].signal.aborted).toBe(true);
  });
  it('debounces text search and cancels it on reset', async () => {
    const { result } = renderHook(() => useApplicants());
    await waitFor(() => expect(result.current.loading).toBe(false));
    act(() => result.current.updateFilter('search', 'Alice'));
    act(() => result.current.resetFilters());
    await waitFor(() => expect(searchApplicants).toHaveBeenCalledTimes(2));
    await new Promise(r => setTimeout(r, 350));
    expect(searchApplicants).toHaveBeenCalledTimes(2);
    expect(searchApplicants.mock.calls[1][0].filters.search).toBe('');
  });
  it('refreshes the same filtered page after mutation and blocks double submits', async () => {
    const pending = deferred();
    assignApplicant.mockReturnValue(pending.promise);
    const { result } = renderHook(() => useApplicants());
    await waitFor(() => expect(result.current.loading).toBe(false));
    act(() => result.current.updateFilter('status', 'pending'));
    await waitFor(() => expect(searchApplicants).toHaveBeenCalledTimes(2));
    let mutation;
    act(() => { mutation = result.current.assign(1, 2); result.current.assign(1, 2); });
    expect(assignApplicant).toHaveBeenCalledTimes(1);
    await act(async () => { pending.resolve({ data: { id: 1 } }); await mutation; });
    await waitFor(() => expect(searchApplicants).toHaveBeenCalledTimes(3));
    expect(searchApplicants.mock.calls[2][0].filters.status).toBe('pending');
  });
  it('keeps the newest details when requests complete out of order', async () => {
    const old = deferred();
    getApplicant.mockReturnValueOnce(old.promise).mockResolvedValueOnce({ data: { id: 2 } });
    const { result } = renderHook(() => useApplicants());
    let first;
    act(() => { first = result.current.loadApplicant(1); });
    await act(async () => { await result.current.loadApplicant(2); });
    await act(async () => { old.resolve({ data: { id: 1 } }); await first; });
    expect(result.current.applicant.id).toBe(2);
  });
  it('does not report failed rejection as success', async () => {
    rejectApplicant.mockRejectedValueOnce(new Error('Rejected by API'));
    const { result } = renderHook(() => useApplicants());
    let data;
    await act(async () => { data = await result.current.reject(1); });
    expect(data).toBeNull();
    expect(result.current.actionLoading).toBe(false);
    expect(result.current.error.message).toBe('Rejected by API');
  });
});
