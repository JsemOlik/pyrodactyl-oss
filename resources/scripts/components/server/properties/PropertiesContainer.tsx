import { useEffect, useState } from 'react';

import ActionButton from '@/components/elements/ActionButton';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';

import getFileContents from '@/api/server/files/getFileContents';
import saveFileContents from '@/api/server/files/saveFileContents';

import { ServerContext } from '@/state/server';

interface Property {
    key: string;
    value: string;
    comment?: string;
    isComment: boolean;
}

const PropertiesContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [properties, setProperties] = useState<Property[]>([]);
    const [originalContent, setOriginalContent] = useState<string>('');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');

    const parsePropertiesFile = (content: string): Property[] => {
        const lines = content.split('\n');
        const parsed: Property[] = [];
        let currentComment = '';

        for (const line of lines) {
            const trimmed = line.trim();

            // Skip empty lines but preserve them
            if (trimmed === '') {
                parsed.push({ key: '', value: '', isComment: false });
                currentComment = ''; // Reset comment on empty line
                continue;
            }

            // Handle comments
            if (trimmed.startsWith('#')) {
                const commentText = trimmed.substring(1).trim();
                // If this is a comment before a property, store it for the next property
                // Otherwise, it's a standalone comment
                currentComment = commentText;
                parsed.push({ key: '', value: '', comment: commentText, isComment: true });
                continue;
            }

            // Handle key=value pairs
            const equalIndex = trimmed.indexOf('=');
            if (equalIndex > 0) {
                const key = trimmed.substring(0, equalIndex).trim();
                const value = trimmed.substring(equalIndex + 1).trim();
                parsed.push({
                    key,
                    value,
                    comment: currentComment || undefined,
                    isComment: false,
                });
                currentComment = ''; // Reset comment after using it
            } else {
                // Line doesn't match expected format, preserve as-is
                parsed.push({ key: '', value: trimmed, isComment: false });
                currentComment = ''; // Reset comment
            }
        }

        return parsed;
    };

    const formatPropertiesFile = (props: Property[]): string => {
        const lines: string[] = [];

        for (const prop of props) {
            if (prop.isComment) {
                if (prop.comment) {
                    lines.push(`#${prop.comment}`);
                } else {
                    lines.push('#');
                }
            } else if (prop.key) {
                // Add comment before property if it exists
                if (prop.comment) {
                    lines.push(`#${prop.comment}`);
                }
                lines.push(`${prop.key}=${prop.value}`);
            } else if (prop.value) {
                lines.push(prop.value);
            } else {
                lines.push('');
            }
        }

        return lines.join('\n');
    };

    const fetchProperties = async () => {
        if (!uuid) {
            setError('Server UUID not available');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const content = await getFileContents(uuid, 'server.properties');
            setOriginalContent(content);
            const parsed = parsePropertiesFile(content);
            setProperties(parsed);
        } catch (err: any) {
            setError(err.message || 'Failed to load server.properties file');
        } finally {
            setLoading(false);
        }
    };

    const saveProperties = async () => {
        if (!uuid) return;

        setSaving(true);
        setError(null);
        setSuccess(false);

        try {
            const formatted = formatPropertiesFile(properties);
            await saveFileContents(uuid, 'server.properties', formatted);
            setOriginalContent(formatted);
            setSuccess(true);
            setTimeout(() => setSuccess(false), 3000);
        } catch (err: any) {
            setError(err.message || 'Failed to save server.properties file');
        } finally {
            setSaving(false);
        }
    };

    const updateProperty = (index: number, value: string) => {
        const updated = [...properties];
        updated[index] = { ...updated[index], value };
        setProperties(updated);
    };

    const formatPropertyKey = (key: string): string => {
        return key
            .split('-')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    };

    const hasChanges = formatPropertiesFile(properties) !== originalContent;

    // Filter properties based on search term
    const filteredProperties = properties.filter((prop) => {
        if (prop.isComment || !prop.key) return false;
        const searchLower = searchTerm.toLowerCase();
        return (
            prop.key.toLowerCase().includes(searchLower) ||
            formatPropertyKey(prop.key).toLowerCase().includes(searchLower) ||
            prop.value.toLowerCase().includes(searchLower) ||
            (prop.comment && prop.comment.toLowerCase().includes(searchLower))
        );
    });

    useEffect(() => {
        fetchProperties();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [uuid]);

    return (
        <ServerContentBlock title={'Properties'} showFlashKey={'properties'}>
            <ErrorBoundary>
                <MainPageHeader
                    title={'Server Properties'}
                    description={
                        'Manage your Minecraft server.properties file. Changes require a server restart to take effect.'
                    }
                >
                    <div className='flex items-center gap-2'>
                        {hasChanges && <span className='text-sm text-yellow-400'>You have unsaved changes</span>}
                        <ActionButton
                            variant='primary'
                            onClick={saveProperties}
                            disabled={saving || loading || !hasChanges}
                        >
                            {saving ? (
                                <span className='flex items-center gap-2'>
                                    <Spinner size='small' />
                                    Saving...
                                </span>
                            ) : (
                                'Save Changes'
                            )}
                        </ActionButton>
                    </div>
                </MainPageHeader>

                <div className='mt-6'>
                    {error && (
                        <div className='mb-4 p-4 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400'>
                            {error}
                        </div>
                    )}

                    {success && (
                        <div className='mb-4 p-4 bg-green-500/10 border border-green-500/20 rounded-lg text-green-400'>
                            Properties saved successfully! Restart your server for changes to take effect.
                        </div>
                    )}

                    {/* Search Box */}
                    {!loading && properties.some((p) => !p.isComment && p.key) && (
                        <div className='mb-4'>
                            <input
                                type='text'
                                placeholder='Search properties...'
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className='w-full px-4 py-2 rounded-lg bg-[#ffffff11] border border-[#ffffff12] text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                            />
                        </div>
                    )}

                    {loading ? (
                        <div className='flex items-center justify-center py-12'>
                            <Spinner size='large' />
                        </div>
                    ) : (
                        <div className='grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4'>
                            {filteredProperties.map((prop, index) => {
                                // Skip comment-only lines and empty lines
                                if (prop.isComment || !prop.key) {
                                    return null;
                                }

                                const actualIndex = properties.findIndex((p) => p === prop);

                                return (
                                    <div
                                        key={`${prop.key}-${actualIndex}`}
                                        className='p-4 bg-[#ffffff09] border border-[#ffffff12] rounded-lg hover:border-[#ffffff20] transition-colors'
                                    >
                                        <label className='block text-sm font-medium mb-2 text-neutral-300'>
                                            {formatPropertyKey(prop.key)}
                                            {prop.comment && (
                                                <span className='block text-xs text-neutral-400 mt-1 font-normal'>
                                                    {prop.comment}
                                                </span>
                                            )}
                                        </label>
                                        {prop.key === 'online-mode' ? (
                                            <select
                                                value={prop.value}
                                                onChange={(e) => updateProperty(actualIndex, e.target.value)}
                                                className='w-full px-3 py-2 rounded-lg bg-[#ffffff11] border border-[#ffffff12] text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                            >
                                                <option value='true'>True</option>
                                                <option value='false'>False</option>
                                            </select>
                                        ) : (
                                            <input
                                                type='text'
                                                value={prop.value}
                                                onChange={(e) => updateProperty(actualIndex, e.target.value)}
                                                className='w-full px-3 py-2 rounded-lg bg-[#ffffff11] border border-[#ffffff12] text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                                placeholder='Value'
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </ErrorBoundary>
        </ServerContentBlock>
    );
};

export default PropertiesContainer;
