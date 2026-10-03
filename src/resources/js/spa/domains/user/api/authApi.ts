import axios from 'axios';
import type { AuthLoginResponse, AuthMeResponse } from '../../../types';

export const authApi = {
    register(name: string, email: string, password: string, passwordConfirmation: string): Promise<AuthLoginResponse> {
        return axios
            .post('/api/auth/register', {
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
            })
            .then((r) => r.data);
    },

    login(email: string, password: string): Promise<AuthLoginResponse> {
        return axios.post('/api/auth/login', { email, password }).then((r) => r.data);
    },

    forgotPassword(email: string): Promise<{ message: string }> {
        return axios.post('/api/auth/forgot-password', { email }).then((r) => r.data);
    },

    resetPassword(token: string, email: string, password: string, passwordConfirmation: string): Promise<{ message: string }> {
        return axios
            .post('/api/auth/reset-password', {
                token,
                email,
                password,
                password_confirmation: passwordConfirmation,
            })
            .then((r) => r.data);
    },

    logout(): Promise<unknown> {
        return axios.post('/api/auth/logout');
    },

    me(): Promise<AuthMeResponse> {
        return axios.get('/api/auth/me').then((r) => r.data);
    },
};
