import { Eye, EyeSlash, Key, Plus, TrashBin } from '@gravity-ui/icons';
import { format } from 'date-fns';
import { Actions, useStoreActions } from 'easy-peasy';
import { Field, Form, Formik, FormikHelpers } from 'formik';
import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { object, string } from 'yup';

import FlashMessageRender from '@/components/FlashMessageRender';
import ApiKeyModal from '@/components/dashboard/ApiKeyModal';
import ConfigureTwoFactorForm from '@/components/dashboard/forms/ConfigureTwoFactorForm';
import UpdateEmailAddressForm from '@/components/dashboard/forms/UpdateEmailAddressForm';
import UpdatePasswordForm from '@/components/dashboard/forms/UpdatePasswordForm';
import ActionButton from '@/components/elements/ActionButton';
import Code from '@/components/elements/Code';
import ContentBox from '@/components/elements/ContentBox';
import FormikFieldWrapper from '@/components/elements/FormikFieldWrapper';
import Input from '@/components/elements/Input';
import PageContentBlock from '@/components/elements/PageContentBlock';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { Dialog } from '@/components/elements/dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

import createApiKey from '@/api/account/createApiKey';
import deleteApiKey from '@/api/account/deleteApiKey';
import getAccountData, { AccountData } from '@/api/account/getAccountData';
import getApiKeys, { ApiKey } from '@/api/account/getApiKeys';
import { createSSHKey, deleteSSHKey, useSSHKeys } from '@/api/account/ssh-keys';
import updateGravatarStyle from '@/api/account/updateGravatarStyle';
import { httpErrorToHuman } from '@/api/http';

import { getGravatarUrl, GravatarStyle } from '@/lib/gravatar';
import { useInitials } from '@/hooks/use-initials';

import { ApplicationStore } from '@/state';

import { useFlashKey } from '@/plugins/useFlash';

interface CreateApiValues {
    description: string;
    allowedIps: string;
}

interface CreateSSHValues {
    name: string;
    publicKey: string;
}

const AccountOverviewContainer = () => {
    const { state } = useLocation();
    const getInitials = useInitials();

    // Account data state
    const [accountData, setAccountData] = useState<AccountData | null>(null);
    const [accountDataLoading, setAccountDataLoading] = useState(true);

    // API Keys state
    const [deleteApiIdentifier, setDeleteApiIdentifier] = useState('');
    const [apiKeys, setApiKeys] = useState<ApiKey[]>([]);
    const [apiKeysLoading, setApiKeysLoading] = useState(true);
    const [showCreateApiModal, setShowCreateApiModal] = useState(false);
    const [apiKey, setApiKey] = useState('');
    const [showApiKeys, setShowApiKeys] = useState<Record<string, boolean>>({});

    // SSH Keys state
    const [deleteSSHKeyState, setDeleteSSHKeyState] = useState<{ name: string; fingerprint: string } | null>(null);
    const [showCreateSSHModal, setShowCreateSSHModal] = useState(false);
    const [showSSHKeys, setShowSSHKeys] = useState<Record<string, boolean>>({});

    const { clearAndAddHttpError: clearApiError } = useFlashKey('api-keys');
    const { clearAndAddHttpError: clearSSHError } = useFlashKey('account:ssh-keys');
    const { addError, clearFlashes } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const {
        data: sshKeys,
        isValidating: sshKeysValidating,
        error: sshKeysError,
        mutate: mutateSSHKeys,
    } = useSSHKeys({
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    useEffect(() => {
        getApiKeys()
            .then((keys) => setApiKeys(keys))
            .then(() => setApiKeysLoading(false))
            .catch((error) => clearApiError(error));
    }, [clearApiError]);

    useEffect(() => {
        clearSSHError(sshKeysError);
    }, [sshKeysError, clearSSHError]);

    useEffect(() => {
        getAccountData()
            .then((data) => {
                setAccountData(data);
                setAccountDataLoading(false);
            })
            .catch((error) => {
                console.error('Failed to load account data:', error);
                setAccountDataLoading(false);
            });
    }, []);

    const doApiDeletion = (identifier: string) => {
        setApiKeysLoading(true);
        clearApiError();
        deleteApiKey(identifier)
            .then(() => setApiKeys((s) => [...(s || []).filter((key) => key.identifier !== identifier)]))
            .catch((error) => clearApiError(error))
            .then(() => {
                setApiKeysLoading(false);
                setDeleteApiIdentifier('');
            });
    };

    const submitCreateApi = (values: CreateApiValues, { setSubmitting, resetForm }: FormikHelpers<CreateApiValues>) => {
        clearFlashes('account:api-keys');
        createApiKey(values.description, values.allowedIps)
            .then(({ secretToken, ...key }) => {
                resetForm();
                setSubmitting(false);
                setApiKey(`${key.identifier}${secretToken}`);
                setApiKeys((s) => [...s!, key]);
                setShowCreateApiModal(false);
            })
            .catch((error) => {
                console.error(error);
                addError({ key: 'account:api-keys', message: httpErrorToHuman(error) });
                setSubmitting(false);
            });
    };

    const toggleApiKeyVisibility = (identifier: string) => {
        setShowApiKeys((prev) => ({
            ...prev,
            [identifier]: !prev[identifier],
        }));
    };

    const doSSHDeletion = () => {
        if (!deleteSSHKeyState) return;

        clearSSHError();
        Promise.all([
            mutateSSHKeys(
                (data) => data?.filter((value) => value.fingerprint !== deleteSSHKeyState.fingerprint),
                false,
            ),
            deleteSSHKey(deleteSSHKeyState.fingerprint),
        ])
            .catch((error) => {
                mutateSSHKeys(undefined, true).catch(console.error);
                clearSSHError(error);
            })
            .finally(() => {
                setDeleteSSHKeyState(null);
            });
    };

    const submitCreateSSH = (values: CreateSSHValues, { setSubmitting, resetForm }: FormikHelpers<CreateSSHValues>) => {
        clearFlashes('account:ssh-keys');
        createSSHKey(values.name, values.publicKey)
            .then((key) => {
                resetForm();
                setSubmitting(false);
                mutateSSHKeys((data) => (data || []).concat(key));
                setShowCreateSSHModal(false);
            })
            .catch((error) => {
                console.error(error);
                addError({ key: 'account:ssh-keys', message: httpErrorToHuman(error) });
                setSubmitting(false);
            });
    };

    const toggleSSHKeyVisibility = (fingerprint: string) => {
        setShowSSHKeys((prev) => ({
            ...prev,
            [fingerprint]: !prev[fingerprint],
        }));
    };

    return (
        <PageContentBlock title={'Your Settings'}>
            <FlashMessageRender byKey='account:api-keys' />
            <FlashMessageRender byKey='account:ssh-keys' />
            <ApiKeyModal visible={apiKey.length > 0} onModalDismissed={() => setApiKey('')} apiKey={apiKey} />

            {/* Create API Key Modal */}
            {showCreateApiModal && (
                <Dialog.Confirm
                    open={showCreateApiModal}
                    onClose={() => setShowCreateApiModal(false)}
                    title='Create API Key'
                    confirm='Create Key'
                    onConfirmed={() => {
                        const form = document.getElementById('create-api-form') as HTMLFormElement;
                        if (form) {
                            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
                            if (submitButton) submitButton.click();
                        }
                    }}
                >
                    <Formik
                        onSubmit={submitCreateApi}
                        initialValues={{ description: '', allowedIps: '' }}
                        validationSchema={object().shape({
                            allowedIps: string(),
                            description: string().required().min(4),
                        })}
                    >
                        {({ isSubmitting }) => (
                            <Form id='create-api-form' className='space-y-4'>
                                <SpinnerOverlay visible={isSubmitting} />

                                <FormikFieldWrapper
                                    label='Description'
                                    name='description'
                                    description='A description of this API key.'
                                >
                                    <Field name='description' as={Input} className='w-full' />
                                </FormikFieldWrapper>

                                <FormikFieldWrapper
                                    label='Allowed IPs'
                                    name='allowedIps'
                                    description='Leave blank to allow any IP address to use this API key, otherwise provide each IP address on a new line. Note: You can also use CIDR ranges here.'
                                >
                                    <Field name='allowedIps' as={Input} className='w-full' />
                                </FormikFieldWrapper>

                                <button type='submit' className='hidden' />
                            </Form>
                        )}
                    </Formik>
                </Dialog.Confirm>
            )}

            {/* Create SSH Key Modal */}
            {showCreateSSHModal && (
                <Dialog.Confirm
                    open={showCreateSSHModal}
                    onClose={() => setShowCreateSSHModal(false)}
                    title='Add SSH Key'
                    confirm='Add Key'
                    onConfirmed={() => {
                        const form = document.getElementById('create-ssh-form') as HTMLFormElement;
                        if (form) {
                            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
                            if (submitButton) submitButton.click();
                        }
                    }}
                >
                    <Formik
                        onSubmit={submitCreateSSH}
                        initialValues={{ name: '', publicKey: '' }}
                        validationSchema={object().shape({
                            name: string().required('SSH Key Name is required'),
                            publicKey: string().required('Public Key is required'),
                        })}
                    >
                        {({ isSubmitting }) => (
                            <Form id='create-ssh-form' className='space-y-4'>
                                <SpinnerOverlay visible={isSubmitting} />

                                <FormikFieldWrapper
                                    label='SSH Key Name'
                                    name='name'
                                    description='A name to identify this SSH key.'
                                >
                                    <Field name='name' as={Input} className='w-full' />
                                </FormikFieldWrapper>

                                <FormikFieldWrapper
                                    label='Public Key'
                                    name='publicKey'
                                    description='Enter your public SSH key.'
                                >
                                    <Field name='publicKey' as={Input} className='w-full' />
                                </FormikFieldWrapper>

                                <button type='submit' className='hidden' />
                            </Form>
                        )}
                    </Formik>
                </Dialog.Confirm>
            )}

            <Dialog.Confirm
                title={'Delete API Key'}
                confirm={'Delete Key'}
                open={!!deleteApiIdentifier}
                onClose={() => setDeleteApiIdentifier('')}
                onConfirmed={() => doApiDeletion(deleteApiIdentifier)}
            >
                All requests using the <Code>{deleteApiIdentifier}</Code> key will be invalidated.
            </Dialog.Confirm>

            <Dialog.Confirm
                title={'Delete SSH Key'}
                confirm={'Delete Key'}
                open={!!deleteSSHKeyState}
                onClose={() => setDeleteSSHKeyState(null)}
                onConfirmed={doSSHDeletion}
            >
                Removing the <Code>{deleteSSHKeyState?.name}</Code> SSH key will invalidate its usage across the Panel.
            </Dialog.Confirm>

            <div className='w-full h-full min-h-full flex-1 flex flex-col px-2 sm:px-0'>
                {/* Profile Header */}
                {!accountDataLoading && accountData && (
                    <div
                        className='transform-gpu skeleton-anim-2 mb-4 sm:mb-6'
                        style={{
                            animationDelay: '0ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <ContentBox>
                            <div className='flex items-center gap-4'>
                                <Avatar className='h-16 w-16 overflow-hidden rounded-full'>
                                    <AvatarImage
                                        src={
                                            accountData.email
                                                ? getGravatarUrl(accountData.email, 128, accountData.gravatar_style as any)
                                                : undefined
                                        }
                                        alt={accountData.username}
                                    />
                                    <AvatarFallback className='rounded-full bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white'>
                                        {accountData.first_name || accountData.last_name
                                            ? getInitials(`${accountData.first_name || ''} ${accountData.last_name || ''}`.trim())
                                            : getInitials(accountData.username)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className='flex-1 min-w-0'>
                                    <h2 className='text-xl font-bold text-zinc-100 truncate'>
                                        {accountData.first_name || accountData.last_name
                                            ? `${accountData.first_name || ''} ${accountData.last_name || ''}`.trim()
                                            : accountData.username}
                                    </h2>
                                    <p className='text-sm text-zinc-400 truncate'>{accountData.email}</p>
                                </div>
                            </div>
                        </ContentBox>
                    </div>
                )}

                {/* Gravatar Style Selector */}
                {!accountDataLoading && accountData && (
                    <div
                        className='transform-gpu skeleton-anim-2 mb-4 sm:mb-6'
                        style={{
                            animationDelay: '10ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <ContentBox title='Gravatar Style'>
                            <p className='text-sm text-zinc-400 mb-4'>
                                Choose your preferred Gravatar style. This will be used for your profile picture when you don't have a custom Gravatar image.
                            </p>
                            <div className='grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4'>
                                {(
                                    [
                                        { value: 'identicon', label: 'Identicon', description: 'Geometric pattern' },
                                        { value: 'monsterid', label: 'Monster', description: 'Generated monster' },
                                        { value: 'wavatar', label: 'Wavatar', description: 'Generated faces' },
                                        { value: 'retro', label: 'Retro', description: '8-bit pixelated' },
                                        { value: 'robohash', label: 'Robohash', description: 'Generated robot' },
                                    ] as const
                                ).map((style) => {
                                    const isSelected = accountData.gravatar_style === style.value;
                                    return (
                                        <button
                                            key={style.value}
                                            onClick={async () => {
                                                if (!isSelected) {
                                                    try {
                                                        await updateGravatarStyle(style.value);
                                                        setAccountData({
                                                            ...accountData,
                                                            gravatar_style: style.value,
                                                        });
                                                    } catch (error) {
                                                        console.error('Failed to update gravatar style:', error);
                                                        addError({
                                                            key: 'account:gravatar-style',
                                                            message: httpErrorToHuman(error as any),
                                                        });
                                                    }
                                                }
                                            }}
                                            className={`relative flex flex-col items-center gap-2 p-3 rounded-lg border-2 transition-all ${
                                                isSelected
                                                    ? 'border-brand bg-brand/10'
                                                    : 'border-[#ffffff08] bg-[#ffffff05] hover:border-[#ffffff15] hover:bg-[#ffffff08]'
                                            }`}
                                        >
                                            <img
                                                src={getGravatarUrl(accountData.email || 'example@example.com', 64, style.value as GravatarStyle)}
                                                alt={style.label}
                                                className='w-12 h-12 rounded-full'
                                            />
                                            <div className='text-center'>
                                                <p
                                                    className={`text-sm font-medium ${
                                                        isSelected ? 'text-brand' : 'text-zinc-300'
                                                    }`}
                                                >
                                                    {style.label}
                                                </p>
                                                <p className='text-xs text-zinc-500'>{style.description}</p>
                                            </div>
                                            {isSelected && (
                                                <div className='absolute top-2 right-2 w-5 h-5 rounded-full bg-brand flex items-center justify-center'>
                                                    <span className='text-white text-xs'>✓</span>
                                                </div>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                            <FlashMessageRender byKey='account:gravatar-style' />
                        </ContentBox>
                    </div>
                )}

                {state?.twoFactorRedirect && (
                    <div
                        className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'
                        style={{
                            animationDelay: '25ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <MessageBox title={'2-Factor Required'} type={'error'}>
                            Your account must have two-factor authentication enabled in order to continue.
                        </MessageBox>
                    </div>
                )}

                <div className='flex flex-col w-full h-full gap-4'>
                    <div
                        className='transform-gpu skeleton-anim-2'
                        style={{
                            animationDelay: '50ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <ContentBox title={'Account Email'} showFlashes={'account:email'}>
                            <UpdateEmailAddressForm />
                        </ContentBox>
                    </div>

                    <div
                        className='transform-gpu skeleton-anim-2'
                        style={{
                            animationDelay: '75ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <div className='space-y-4'>
                            <ContentBox title={'Account Password'} showFlashes={'account:password'}>
                                <UpdatePasswordForm />
                            </ContentBox>
                            <ContentBox title={'Multi-Factor Authentication'}>
                                <ConfigureTwoFactorForm />
                            </ContentBox>
                        </div>
                    </div>

                    <div
                        className='transform-gpu skeleton-anim-2'
                        style={{
                            animationDelay: '100ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <ContentBox title={'API Keys'} showFlashes={'account:api-keys'}>
                            <div className='space-y-4'>
                                <div className='flex justify-end'>
                                    <ActionButton
                                        variant='primary'
                                        onClick={() => setShowCreateApiModal(true)}
                                        className='flex items-center gap-2'
                                    >
                                        <Plus width={18} height={18} fill='currentColor' />
                                        Create API Key
                                    </ActionButton>
                                </div>
                                <SpinnerOverlay visible={apiKeysLoading} />
                                {apiKeys.length === 0 ? (
                                    <div className='text-center py-8'>
                                        <div className='w-12 h-12 mx-auto mb-3 rounded-full bg-[#ffffff11] flex items-center justify-center'>
                                            <Key width={18} height={18} className='text-zinc-400' fill='currentColor' />
                                        </div>
                                        <h3 className='text-base font-medium text-zinc-200 mb-1'>No API Keys</h3>
                                        <p className='text-sm text-zinc-400 max-w-sm mx-auto'>
                                            {apiKeysLoading
                                                ? 'Loading your API keys...'
                                                : "You haven't created any API keys yet. Create one to get started with the API."}
                                        </p>
                                    </div>
                                ) : (
                                    <div className='space-y-2'>
                                        {apiKeys.map((key) => (
                                            <div
                                                key={key.identifier}
                                                className='bg-[#ffffff05] border-[1px] border-[#ffffff08] rounded-lg p-3 hover:border-[#ffffff15] transition-all duration-150'
                                            >
                                                <div className='flex items-center justify-between'>
                                                    <div className='flex-1 min-w-0'>
                                                        <div className='flex items-center gap-3 mb-2'>
                                                            <h4 className='text-sm font-medium text-zinc-100 truncate'>
                                                                {key.description}
                                                            </h4>
                                                        </div>
                                                        <div className='flex items-center gap-4 text-xs text-zinc-400'>
                                                            <span>
                                                                Last used:{' '}
                                                                {key.lastUsedAt
                                                                    ? format(key.lastUsedAt, 'MMM d, yyyy HH:mm')
                                                                    : 'Never'}
                                                            </span>
                                                            <div className='flex items-center gap-2'>
                                                                <span>Key:</span>
                                                                <code className='font-mono px-2 py-1 bg-[#ffffff08] border border-[#ffffff08] rounded text-zinc-300'>
                                                                    {showApiKeys[key.identifier]
                                                                        ? key.identifier
                                                                        : '••••••••••••••••'}
                                                                </code>
                                                                <ActionButton
                                                                    variant='secondary'
                                                                    size='sm'
                                                                    onClick={() =>
                                                                        toggleApiKeyVisibility(key.identifier)
                                                                    }
                                                                    className='p-1 text-zinc-400 hover:text-zinc-300'
                                                                >
                                                                    {showApiKeys[key.identifier] ? (
                                                                        <EyeSlash
                                                                            width={16}
                                                                            height={16}
                                                                            fill='currentColor'
                                                                        />
                                                                    ) : (
                                                                        <Eye
                                                                            width={16}
                                                                            height={16}
                                                                            fill='currentColor'
                                                                        />
                                                                    )}
                                                                </ActionButton>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <ActionButton
                                                        variant='danger'
                                                        size='sm'
                                                        className='ml-4'
                                                        onClick={() => setDeleteApiIdentifier(key.identifier)}
                                                    >
                                                        <TrashBin width={18} height={18} fill='currentColor' />
                                                    </ActionButton>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </ContentBox>
                    </div>

                    <div
                        className='transform-gpu skeleton-anim-2'
                        style={{
                            animationDelay: '125ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <ContentBox title={'SSH Keys'} showFlashes={'account:ssh-keys'}>
                            <div className='space-y-4'>
                                <div className='flex justify-end'>
                                    <ActionButton
                                        variant='primary'
                                        onClick={() => setShowCreateSSHModal(true)}
                                        className='flex items-center gap-2'
                                    >
                                        <Plus width={18} height={18} fill='currentColor' />
                                        Add SSH Key
                                    </ActionButton>
                                </div>
                                <SpinnerOverlay visible={!sshKeys && sshKeysValidating} />
                                {!sshKeys || sshKeys.length === 0 ? (
                                    <div className='text-center py-8'>
                                        <div className='w-12 h-12 mx-auto mb-3 rounded-full bg-[#ffffff11] flex items-center justify-center'>
                                            <Key width={18} height={18} className='text-zinc-400' fill='currentColor' />
                                        </div>
                                        <h3 className='text-base font-medium text-zinc-200 mb-1'>No SSH Keys</h3>
                                        <p className='text-sm text-zinc-400 max-w-sm mx-auto'>
                                            {!sshKeys
                                                ? 'Loading your SSH keys...'
                                                : "You haven't added any SSH keys yet. Add one to securely access your servers."}
                                        </p>
                                    </div>
                                ) : (
                                    <div className='space-y-2'>
                                        {sshKeys.map((key) => (
                                            <div
                                                key={key.fingerprint}
                                                className='bg-[#ffffff05] border-[1px] border-[#ffffff08] rounded-lg p-3 hover:border-[#ffffff15] transition-all duration-150'
                                            >
                                                <div className='flex items-center justify-between'>
                                                    <div className='flex-1 min-w-0'>
                                                        <div className='flex items-center gap-3 mb-2'>
                                                            <h4 className='text-sm font-medium text-zinc-100 truncate'>
                                                                {key.name}
                                                            </h4>
                                                        </div>
                                                        <div className='flex items-center gap-4 text-xs text-zinc-400'>
                                                            <span>
                                                                Added: {format(key.createdAt, 'MMM d, yyyy HH:mm')}
                                                            </span>
                                                            <div className='flex items-center gap-2'>
                                                                <span>Fingerprint:</span>
                                                                <code className='font-mono px-2 py-1 bg-[#ffffff08] border border-[#ffffff08] rounded text-zinc-300'>
                                                                    {showSSHKeys[key.fingerprint]
                                                                        ? `SHA256:${key.fingerprint}`
                                                                        : 'SHA256:••••••••••••••••'}
                                                                </code>
                                                                <ActionButton
                                                                    variant='secondary'
                                                                    size='sm'
                                                                    onClick={() =>
                                                                        toggleSSHKeyVisibility(key.fingerprint)
                                                                    }
                                                                    className='p-1 text-zinc-400 hover:text-zinc-300'
                                                                >
                                                                    {showSSHKeys[key.fingerprint] ? (
                                                                        <EyeSlash
                                                                            width={16}
                                                                            height={16}
                                                                            fill='currentColor'
                                                                        />
                                                                    ) : (
                                                                        <Eye
                                                                            width={16}
                                                                            height={16}
                                                                            fill='currentColor'
                                                                        />
                                                                    )}
                                                                </ActionButton>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <ActionButton
                                                        variant='danger'
                                                        size='sm'
                                                        className='ml-4'
                                                        onClick={() =>
                                                            setDeleteSSHKeyState({
                                                                name: key.name,
                                                                fingerprint: key.fingerprint,
                                                            })
                                                        }
                                                    >
                                                        <TrashBin width={18} height={18} fill='currentColor' />
                                                    </ActionButton>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </ContentBox>
                    </div>

                    <div
                        className='transform-gpu skeleton-anim-2'
                        style={{
                            animationDelay: '100ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <ContentBox title={'Panel Version'}>
                            <p className='text-sm mb-4 text-zinc-300'>
                                This is useful to provide Pyro staff if you run into an unexpected issue.
                            </p>
                            <div className='flex flex-col gap-4'>
                                <Code>
                                    Version: {import.meta.env.VITE_PYRODACTYL_VERSION} -{' '}
                                    {import.meta.env.VITE_BRANCH_NAME}
                                </Code>
                                <Code>Commit : {import.meta.env.VITE_COMMIT_HASH.slice(0, 7)}</Code>
                            </div>
                        </ContentBox>
                    </div>
                </div>
            </div>
        </PageContentBlock>
    );
};

export default AccountOverviewContainer;
