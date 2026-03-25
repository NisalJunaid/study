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

const normalizeEnvValue = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    return value.trim();
};

const isPlaceholderValue = (value) => {
    const normalized = normalizeEnvValue(value).toLowerCase();

    if (! normalized) {
        return true;
    }

    return [
        'null',
        'undefined',
        'false',
        'study_app_key_change_me',
        'change_me',
        'your_app_key',
    ].includes(normalized);
};

const pusherKey = normalizeEnvValue(import.meta.env.VITE_PUSHER_APP_KEY);
const pusherCluster = normalizeEnvValue(import.meta.env.VITE_PUSHER_APP_CLUSTER) || 'mt1';
const pusherHost = normalizeEnvValue(import.meta.env.VITE_PUSHER_HOST);
const pusherPort = normalizeEnvValue(import.meta.env.VITE_PUSHER_PORT);
const pusherScheme = normalizeEnvValue(import.meta.env.VITE_PUSHER_SCHEME) || 'https';

const realtimeEnabled = ! isPlaceholderValue(pusherKey);

window.Echo = realtimeEnabled
    ? new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        wsHost: pusherHost && ! isPlaceholderValue(pusherHost) ? pusherHost : `ws-${pusherCluster}.pusher.com`,
        wsPort: Number(pusherPort || 80),
        wssPort: Number(pusherPort || 443),
        forceTLS: pusherScheme === 'https',
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
