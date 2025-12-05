export type GravatarStyle = 'identicon' | 'monsterid' | 'wavatar' | 'retro' | 'robohash';

/**
 * Generate a Gravatar URL from an email address
 */
export function getGravatarUrl(email: string, size: number = 80, style: GravatarStyle = 'identicon'): string {
    if (!email) {
        return '';
    }

    // Simple MD5 implementation for gravatar
    // Note: This is a basic hash function - for production use, consider using a proper MD5 library
    function simpleHash(str: string): string {
        let hash = 0;
        const normalizedStr = str.toLowerCase().trim();

        for (let i = 0; i < normalizedStr.length; i++) {
            const char = normalizedStr.charCodeAt(i);
            hash = (hash << 5) - hash + char;
            hash = hash & hash; // Convert to 32-bit integer
        }

        // Convert to hex string (32 chars for MD5)
        return Math.abs(hash).toString(16).padStart(32, '0').substring(0, 32);
    }

    const hash = simpleHash(email);
    return `https://www.gravatar.com/avatar/${hash}?s=${size}&d=${style}`;
}
