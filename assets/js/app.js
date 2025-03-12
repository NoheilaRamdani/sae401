import './styles/app.css'; // Ton fichier CSS (on y ajoutera Bootstrap)
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        const calendar = new Calendar(calendarEl, {
            plugins: [dayGridPlugin, timeGridPlugin, listPlugin],
            initialView: 'dayGridMonth', // Vue par défaut : mois
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek' // Options de vue
            },
            events: '/api/assignments' // Endpoint pour charger les devoirs
        });
        calendar.render();
    }
});
console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
