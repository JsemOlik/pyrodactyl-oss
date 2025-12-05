import type { FormikHelpers } from 'formik';
import { Formik } from 'formik';
import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { object, ref, string } from 'yup';

import LoginFormContainer from '@/components/auth/LoginFormContainer';
import Button from '@/components/elements/Button';
import Captcha, { getCaptchaResponse } from '@/components/elements/Captcha';
import Field from '@/components/elements/Field';
import Logo from '@/components/elements/PyroLogo';

import CaptchaManager from '@/lib/captcha';

import register from '@/api/auth/register';

import useFlash from '@/plugins/useFlash';

interface Values {
    email: string;
    username: string;
    name_first: string;
    name_last: string;
    password: string;
    password_confirmation: string;
}

function RegisterContainer() {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const navigate = useNavigate();

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // Get captcha response if enabled
        let registerData: any = values;
        if (CaptchaManager.isEnabled()) {
            const captchaResponse = getCaptchaResponse();
            const fieldName = CaptchaManager.getProviderInstance().getResponseFieldName();

            if (fieldName) {
                if (captchaResponse) {
                    registerData = { ...values, [fieldName]: captchaResponse };
                } else {
                    clearAndAddHttpError({ error: new Error('Please complete the captcha verification.') });
                    setSubmitting(false);
                    return;
                }
            }
        }

        register(registerData)
            .then(() => {
                clearFlashes();
                navigate('/auth/login', {
                    state: {
                        message: 'Registration successful! Please log in with your new account.',
                    },
                });
            })
            .catch((error: any) => {
                setSubmitting(false);

                if (error.code === 'DisplayException') {
                    clearAndAddHttpError({ error: new Error(error.detail || error.message) });
                } else if (error.response?.data?.errors) {
                    const firstError = error.response.data.errors[0];
                    clearAndAddHttpError({ error: new Error(firstError.detail || error.message) });
                } else {
                    clearAndAddHttpError({ error });
                }
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{
                email: '',
                username: '',
                name_first: '',
                name_last: '',
                password: '',
                password_confirmation: '',
            }}
            validationSchema={object().shape({
                email: string().required('An email address is required.').email('Please provide a valid email address.'),
                username: string()
                    .required('A username is required.')
                    .min(3, 'Username must be at least 3 characters.')
                    .max(191, 'Username cannot exceed 191 characters.'),
                name_first: string()
                    .required('First name is required.')
                    .min(1, 'First name cannot be empty.')
                    .max(191, 'First name cannot exceed 191 characters.'),
                name_last: string().max(191, 'Last name cannot exceed 191 characters.'),
                password: string()
                    .required('A password is required.')
                    .min(8, 'Password must be at least 8 characters.'),
                password_confirmation: string()
                    .required('Please confirm your password.')
                    .oneOf([ref('password')], 'Passwords do not match.'),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer className={`w-full flex`}>
                    <div className='flex h-12 mb-4 items-center w-full'>
                        <Logo />
                    </div>
                    <div aria-hidden className='my-8 bg-[#ffffff33] min-h-[1px]'></div>
                    <h2 className='text-xl font-extrabold mb-2'>Create Account</h2>

                    <Field id='email' type='email' label='Email' name='email' disabled={isSubmitting} />

                    <Field id='username' type='text' label='Username' name='username' disabled={isSubmitting} className='mt-6' />

                    <div className='mt-6 grid grid-cols-2 gap-4'>
                        <Field id='name_first' type='text' label='First Name' name='name_first' disabled={isSubmitting} />
                        <Field id='name_last' type='text' label='Last Name' name='name_last' disabled={isSubmitting} />
                    </div>

                    <Field
                        id='password'
                        type='password'
                        label='Password'
                        name='password'
                        disabled={isSubmitting}
                        className='mt-6'
                    />

                    <Field
                        id='password_confirmation'
                        type='password'
                        label='Confirm Password'
                        name='password_confirmation'
                        disabled={isSubmitting}
                        className='mt-6'
                    />

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
                            Create Account
                        </Button>
                    </div>

                    <div className='mt-6 text-center'>
                        <p className='text-sm text-zinc-500'>
                            Already have an account?{' '}
                            <Link to='/auth/login' className='text-brand hover:text-brand/80 font-medium'>
                                Log in
                            </Link>
                        </p>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
}

export default RegisterContainer;
