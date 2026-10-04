import { createApp, watch } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import router from './router';
import App from './App.vue';
import { setupAxiosInterceptors } from './infrastructure/http/axiosInterceptors';
import { useAuthStore } from './stores/authStore';
import { queryClient } from './infrastructure/query';

import { registerPwa } from './infrastructure/pwa/registerPwa';

import '../bootstrap';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(VueQueryPlugin, { queryClient });
async function boot() {
    const authStore = useAuthStore();
    const synchronizeOfflineSession = await registerPwa();
    await synchronizeOfflineSession(authStore.token).catch(() => {});
    watch(() => authStore.token, (token) => { void synchronizeOfflineSession(token).catch(() => {}); }, { flush: 'sync' });
    authStore.$onAction(({ name }) => {
        if (name === 'logout' || name === 'clearAuth') void synchronizeOfflineSession(null).catch(() => {});
    });
    // Other tabs must not retain the previous learner's session after an account switch.
    window.addEventListener('storage', (event) => {
        if ((event.key === 'auth_token' || event.key === null) && localStorage.getItem('auth_token') !== authStore.token) window.location.reload();
    });
    setupAxiosInterceptors(router);
    app.use(router);
    void authStore.checkAuth();
    app.mount('#app');
}
void boot();
