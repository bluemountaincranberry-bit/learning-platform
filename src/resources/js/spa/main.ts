import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import router from './router';
import App from './App.vue';
import { setupAxiosInterceptors } from './infrastructure/http/axiosInterceptors';
import { useAuthStore } from './stores/authStore';
import { queryClient } from './infrastructure/query';

import '../bootstrap';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(VueQueryPlugin, { queryClient });
app.use(router);
setupAxiosInterceptors(router);

const authStore = useAuthStore();
authStore.checkAuth();

app.mount('#app');
