import axios from 'axios';
import { getActivePinia } from 'pinia';
import type { Router } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';

export function setupAxiosInterceptors(router: Router): void {
    axios.interceptors.request.use(
        (config) => {
            const pinia = getActivePinia();
            if (pinia) {
                const auth = useAuthStore(pinia);
                if (auth.token) {
                    config.headers.Authorization = `Bearer ${auth.token}`;
                }
            }
            return config;
        },
        (error) => Promise.reject(error)
    );

    axios.interceptors.response.use(
        (response) => response,
        (error) => {
            if (error.response?.status === 401) {
                const pinia = getActivePinia();
                if (pinia) {
                    const auth = useAuthStore(pinia);
                    auth.clearAuth();
                }
                if (router?.currentRoute?.value?.name !== 'login') {
                    router?.push({ name: 'login', query: { redirect: router.currentRoute.value?.fullPath } });
                }
            }
            return Promise.reject(error);
        }
    );
}
