

import Alpine from 'alpinejs';
import dashboardMeetingTimer from './dashboard-meeting-timer';
import meetingInvestment from './meeting-investment';

window.Alpine = Alpine;
Alpine.data('dashboardMeetingTimer', dashboardMeetingTimer);
Alpine.data('meetingInvestment', meetingInvestment);

Alpine.start();
