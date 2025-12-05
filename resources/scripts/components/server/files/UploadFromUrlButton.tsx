import { useState } from 'react';

import ActionButton from '@/components/elements/ActionButton';
import Spinner from '@/components/elements/Spinner';
import { Dialog } from '@/components/elements/dialog';

import pullFile from '@/api/server/files/pullFile';

import { ServerContext } from '@/state/server';

import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import { useFlashKey } from '@/plugins/useFlash';

const UploadFromUrlButton = () => {
    const [open, setOpen] = useState(false);
    const [url, setUrl] = useState('');
    const [filename, setFilename] = useState('');
    const [loading, setLoading] = useState(false);
    const { mutate } = useFileManagerSwr();
    const { addError, clearAndAddHttpError } = useFlashKey('files');

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const directory = ServerContext.useStoreState((state) => state.files.directory);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!url.trim()) {
            return addError('Please enter a valid URL.', 'Error');
        }

        // Basic URL validation
        try {
            new URL(url);
        } catch {
            return addError('Please enter a valid URL.', 'Error');
        }

        setLoading(true);
        clearAndAddHttpError();

        try {
            await pullFile(uuid, {
                url: url.trim(),
                directory: directory || undefined,
                filename: filename.trim() || undefined,
            });

            setOpen(false);
            setUrl('');
            setFilename('');
            await mutate();
        } catch (error: any) {
            clearAndAddHttpError(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <ActionButton variant='secondary' onClick={() => setOpen(true)}>
                Upload from URL
            </ActionButton>

            <Dialog
                open={open}
                onClose={() => {
                    if (!loading) {
                        setOpen(false);
                        setUrl('');
                        setFilename('');
                    }
                }}
                title='Upload from URL'
                description='Download a file from a URL and save it to your server. This bypasses web upload limits.'
            >
                <form id='upload-url-form' onSubmit={handleSubmit} className='mt-4 space-y-4'>
                    <div>
                        <label htmlFor='url' className='block text-sm font-medium mb-2'>
                            URL <span className='text-red-400'>*</span>
                        </label>
                        <input
                            id='url'
                            type='url'
                            value={url}
                            onChange={(e) => setUrl(e.target.value)}
                            placeholder='https://example.com/file.zip'
                            className='w-full px-4 py-2 rounded-lg bg-[#ffffff11] border border-[#ffffff12] text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                            required
                            disabled={loading}
                        />
                    </div>

                    <div>
                        <label htmlFor='filename' className='block text-sm font-medium mb-2'>
                            Filename (optional)
                        </label>
                        <input
                            id='filename'
                            type='text'
                            value={filename}
                            onChange={(e) => setFilename(e.target.value)}
                            placeholder='Leave empty to use original filename'
                            className='w-full px-4 py-2 rounded-lg bg-[#ffffff11] border border-[#ffffff12] text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                            disabled={loading}
                        />
                        <p className='mt-1 text-xs text-neutral-400'>
                            If not specified, the filename will be extracted from the URL.
                        </p>
                    </div>
                </form>

                <Dialog.Footer>
                    <ActionButton variant='secondary' onClick={() => setOpen(false)} disabled={loading}>
                        Cancel
                    </ActionButton>
                    <ActionButton
                        variant='primary'
                        disabled={loading || !url.trim()}
                        type='submit'
                        form='upload-url-form'
                    >
                        <div className='flex items-center gap-2'>
                            {loading && <Spinner size='small' />}
                            <span>Upload</span>
                        </div>
                    </ActionButton>
                </Dialog.Footer>
            </Dialog>
        </>
    );
};

export default UploadFromUrlButton;
