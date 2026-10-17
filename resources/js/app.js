

import Alpine from 'alpinejs';
import dashboardMeetingTimer from './dashboard-meeting-timer';
import meetingTimer from './meeting-timer';

window.Alpine = Alpine;
Alpine.data('dashboardMeetingTimer', dashboardMeetingTimer);
Alpine.data('meetingTimer', meetingTimer);

Alpine.start();
