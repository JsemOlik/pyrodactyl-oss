/**
 * Hook to generate user initials from a name
 */
export function useInitials() {
    return (name: string): string => {
        if (!name) {
            return '?';
        }

        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) {
            return parts[0].charAt(0).toUpperCase();
        }

        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    };
}
