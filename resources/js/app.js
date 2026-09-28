

import Alpine from 'alpinejs';
import meetingTimer from './meeting-timer';

window.Alpine = Alpine;
Alpine.data('meetingTimer', meetingTimer);

Alpine.start();
