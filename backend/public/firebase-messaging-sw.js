
importScripts(
    'https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js'
);
importScripts(
    'https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js'
);

firebase.initializeApp({
    apiKey: "AIzaSyAbV1k43Nzcn9Kr3PSn4bnNdjuhFyEsxRc",
    authDomain: "syriateltms.firebaseapp.com",
    projectId: "syriateltms",
    storageBucket: "syriateltms.firebasestorage.app",
    messagingSenderId: "439230744675",
    appId: "1:439230744675:web:71e4ebb4b49235cda29056",
    measurementId: "G-3ZQBWDY729"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    console.log('Background message received:', payload);

    const title = payload.notification?.title || 'Syriatel TMS';
    const options = {
        body: payload.notification?.body || 'You have a new notification!',
        data: payload.data || {}
    };

    return self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil(
        clients.openWindow('http://127.0.0.1:8000')
    );
});
