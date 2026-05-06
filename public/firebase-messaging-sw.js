importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js');
 importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js');
 const firebaseConfig = {apiKey:'AIzaSyC3uInEAZeuteV07fqia_AVaAs5_yXqqiA',
authDomain:'dalat-bds.firebaseapp.com',
projectId:'dalat-bds',
storageBucket:'dalat-bds.appspot.com',
messagingSenderId:'16561777612',
appId:'1:16561777612:web:2c01b314391c3ea94f8b69',
measurementId:'G-JTJ45K3D8E',
 };
if (!firebase.apps.length) {
 firebase.initializeApp(firebaseConfig);
 }
const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
console.log(payload);
 var title = payload.data.title;
var options = {
body: payload.data.body,
icon: payload.data.icon,
data: {
 time: new Date(Date.now()).toString(),
 click_action: payload.data.click_action
 }
};
return self.registration.showNotification(title, options);
 });
self.addEventListener('notificationclick', function(event) {
 var action_click = event.notification.data.click_action;
event.notification.close();
event.waitUntil(
clients.openWindow(action_click)
 );
});