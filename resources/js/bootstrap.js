/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const realtimeEnabled = Boolean(import.meta.env.VITE_PUSHER_APP_KEY);

window.Echo = realtimeEnabled
    ? new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        wsHost: import.meta.env.VITE_PUSHER_HOST || `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1'}.pusher.com`,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        withCredentials: true,
    })
    : null;

window.createRealtimeChannel = (channelName, callbackMap) => {
    if (! window.Echo || ! callbackMap || Object.keys(callbackMap).length === 0) {
        return () => {};
    }

    const channel = window.Echo.private(channelName);

    Object.entries(callbackMap).forEach(([eventName, callback]) => {
        if (typeof callback === 'function') {
            channel.listen(`.${eventName}`, callback);
        }
    });

    return () => {
        Object.keys(callbackMap).forEach((eventName) => channel.stopListening(`.${eventName}`));
        window.Echo.leave(`private-${channelName}`);
    };
};
