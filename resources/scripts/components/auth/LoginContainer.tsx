import type { FormikHelpers } from 'formik';
import { Formik } from 'formik';
import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { object, string } from 'yup';

import LoginFormContainer from '@/components/auth/LoginFormContainer';
import Button from '@/components/elements/Button';
import Captcha, { getCaptchaResponse } from '@/components/elements/Captcha';
import Field from '@/components/elements/Field';
import Logo from '@/components/elements/PyroLogo';

import CaptchaManager from '@/lib/captcha';

import getAccountData from '@/api/account/getAccountData';
import login from '@/api/auth/login';

import useFlash from '@/plugins/useFlash';

interface Values {
    user: string;
    password: string;
}

function LoginContainer() {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const navigate = useNavigate();
    const location = useLocation();
    const intendedUrl = (location.state as { from?: string })?.from || '/dashboard';

    const [showSuccessAnimation, setShowSuccessAnimation] = useState(false);
    const [userFirstName, setUserFirstName] = useState<string | null>(null);
    const redirectTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    useEffect(() => {
        clearFlashes();

        return () => {
            if (redirectTimeoutRef.current) {
                clearTimeout(redirectTimeoutRef.current);
            }
        };
    }, [clearFlashes]);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // Get captcha response if enabled
        let loginData: any = values;
        if (CaptchaManager.isEnabled()) {
            const captchaResponse = getCaptchaResponse();
            const fieldName = CaptchaManager.getProviderInstance().getResponseFieldName();

            console.log('Captcha enabled, response:', captchaResponse, 'fieldName:', fieldName);

            if (fieldName) {
                if (captchaResponse) {
                    loginData = { ...values, [fieldName]: captchaResponse };
                    console.log('Adding captcha to login data:', loginData);
                } else {
                    // Captcha is enabled but no response - show error
                    console.error('Captcha enabled but no response available');
                    clearAndAddHttpError({ error: new Error('Please complete the captcha verification.') });
                    setSubmitting(false);
                    return;
                }
            }
        } else {
            console.log('Captcha not enabled');
        }

        login(loginData)
            .then(async (response) => {
                if (response.complete) {
                    // Fetch user's first name for welcome message
                    try {
                        const accountData = await getAccountData();
                        setUserFirstName(accountData.first_name || null);
                    } catch (error) {
                        console.error('Failed to fetch account data:', error);
                        // Continue with animation even if fetch fails
                    }

                    // Start success animation
                    setShowSuccessAnimation(true);

                    // Redirect after 2 seconds
                    redirectTimeoutRef.current = setTimeout(() => {
                        window.location.href = intendedUrl;
                    }, 2000);
                    return;
                }
                navigate('/auth/login/checkpoint', {
                    state: { token: response.confirmationToken, from: intendedUrl },
                });
            })
            .catch((error: any) => {
                setSubmitting(false);

                if (error.code === 'InvalidCredentials') {
                    clearAndAddHttpError({ error: new Error('Invalid username or password. Please try again.') });
                } else if (error.code === 'DisplayException') {
                    clearAndAddHttpError({ error: new Error(error.detail || error.message) });
                } else {
                    clearAndAddHttpError({ error });
                }
            });
    };

    return (
        <>
            {/* CSS Animations */}
            <style>{`
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                    }
                    to {
                        opacity: 1;
                    }
                }
                
                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                @keyframes logoMoveDown {
                    from {
                        transform: translateY(0);
                    }
                    to {
                        transform: translateY(120px);
                    }
                }
            `}</style>

            <div className='relative w-full max-w-[38rem]' style={{ minHeight: '500px' }}>
                {/* Logo - stays visible during animation */}
                <div
                    className={`absolute top-0 left-0 right-0 flex justify-center z-40 transition-transform duration-700 ${
                        showSuccessAnimation ? 'translate-y-[190px]' : ''
                    }`}
                    style={{
                        paddingLeft: '2rem',
                        paddingRight: '2rem',
                        transitionTimingFunction: 'cubic-bezier(0.42, 0, 0.58, 1)',
                    }}
                >
                    <div className='flex h-12 mb-4 items-center w-full max-w-[38rem]'>
                        <Logo />
                    </div>
                </div>

                {/* Success Animation - Welcome Text */}
                {showSuccessAnimation && (
                    <div
                        className='absolute inset-0 flex flex-col items-center justify-center z-50'
                        style={{
                            animation: 'fadeIn 0.5s ease-out 0.5s both',
                        }}
                    >
                        <div
                            className='flex flex-col items-center justify-center'
                            style={{
                                paddingTop: '72px',
                                animation: 'slideUp 0.6s ease-out 0.7s both',
                            }}
                        >
                            <h2 className='text-2xl font-bold text-white text-center'>
                                Welcome back, {userFirstName || 'there'}!
                            </h2>
                        </div>
                    </div>
                )}

                {/* Login Form */}
                <div className='relative'>
                    <Formik
                        onSubmit={onSubmit}
                        initialValues={{ user: '', password: '' }}
                        validationSchema={object().shape({
                            user: string().required('A username or email must be provided.'),
                            password: string().required('Please enter your account password.'),
                        })}
                    >
                        {({ isSubmitting }) => (
                            <LoginFormContainer className={`w-full flex`}>
                                {/* Logo placeholder - invisible but maintains spacing */}
                                <div className='flex h-12 mb-4 items-center w-full opacity-0 pointer-events-none'>
                                    <Logo />
                                </div>
                                <div
                                    className={`transition-all duration-500 ease-in-out ${
                                        showSuccessAnimation ? 'opacity-0 pointer-events-none' : 'opacity-100'
                                    }`}
                                >
                                    <div aria-hidden className='my-8 bg-[#ffffff33] min-h-[1px]'></div>
                                    <h2 className='text-xl font-extrabold mb-2'>Login</h2>

                                    <Field
                                        id='user'
                                        type={'text'}
                                        label={'Username or Email'}
                                        name={'user'}
                                        disabled={isSubmitting}
                                    />

                                    <div className={`relative mt-6`}>
                                        <Field
                                            id='password'
                                            type={'password'}
                                            label={'Password'}
                                            name={'password'}
                                            disabled={isSubmitting}
                                        />
                                        <Link
                                            to={'/auth/password'}
                                            className={`text-xs text-zinc-500 tracking-wide no-underline hover:text-zinc-600 absolute top-1 right-0`}
                                        >
                                            Forgot Password?
                                        </Link>
                                    </div>

                                    <Captcha
                                        className='mt-6'
                                        onError={(error) => {
                                            console.error('Captcha error:', error);
                                            clearAndAddHttpError({
                                                error: new Error('Captcha verification failed. Please try again.'),
                                            });
                                        }}
                                    />

                                    <div className={`mt-6`}>
                                        <Button
                                            className={`relative mt-4 w-full rounded-full bg-brand border-0 ring-0 outline-hidden capitalize font-bold text-sm py-2 hover:cursor-pointer`}
                                            type={'submit'}
                                            size={'xlarge'}
                                            isLoading={isSubmitting}
                                            disabled={isSubmitting}
                                        >
                                            Login
                                        </Button>
                                    </div>
                                    <div className='mt-6 text-center'>
                                        <p className='text-sm text-zinc-500'>
                                            Don&apos;t have an account?{' '}
                                            <Link
                                                to='/auth/register'
                                                className='text-brand hover:text-brand/80 font-medium'
                                            >
                                                Register
                                            </Link>
                                        </p>
                                    </div>
                                </div>
                            </LoginFormContainer>
                        )}
                    </Formik>
                </div>
            </div>
        </>
    );
}

export default LoginContainer;
