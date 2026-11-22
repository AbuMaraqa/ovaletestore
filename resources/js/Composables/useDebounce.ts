export function useDebounce<A extends unknown[]>(fn: (...args: A) => void, wait = 300): (...args: A) => void {
    let timer: ReturnType<typeof setTimeout> | undefined;
    return (...args: A) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}
