import { afterEach, vi } from 'vitest';

afterEach(() => {
    vi.restoreAllMocks();
    vi.clearAllMocks();
    window.localStorage.clear();
});
