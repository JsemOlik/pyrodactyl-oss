import http from '@/api/http';

interface RegisterData {
    email: string;
    username: string;
    name_first: string;
    name_last: string;
    password: string;
    password_confirmation: string;
    [key: string]: any; // Allow additional fields like captcha responses
}

interface RegisterResponse {
    success: boolean;
    message?: string;
    error?: string;
}

export default async (data: RegisterData): Promise<RegisterResponse> => {
    try {
        await http.get('/sanctum/csrf-cookie');

        // Pass through all data including captcha responses
        const payload: Record<string, any> = {
            ...data,
        };

        const response = await http.post('/auth/register', payload);

        if (!response.data || typeof response.data !== 'object') {
            throw new Error('Invalid server response format');
        }

        return {
            success: true,
            message: response.data.message ?? 'Registration successful',
        };
    } catch (error: any) {
        const registerError = new Error(
            error.response?.data?.error ??
                error.response?.data?.message ??
                error.message ??
                'Registration failed. Please try again.',
        ) as any;

        registerError.response = error.response;
        registerError.detail = error.response?.data?.errors?.[0]?.detail;
        registerError.code = error.response?.data?.errors?.[0]?.code;

        console.error('Register API Error:', {
            status: error.response?.status,
            data: error.response?.data,
            detail: registerError.detail,
            message: registerError.message,
        });

        throw registerError;
    }
};
