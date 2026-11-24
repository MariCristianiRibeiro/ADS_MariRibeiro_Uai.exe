const ATTENDED_STATUSES = new Set(['confirmed', 'present']);

export default (meetings = [], initialMeetingId = null) => ({
    meetings,
    selectedMeetingId: Number(initialMeetingId || meetings[0]?.id || 0),
    tickCount: 0,
    interval: null,
    processing: false,
    error: '',

    init() {
        if (this.isRunning) this.startTicking();
    },

    destroy() {
        this.stopTicking();
    },

    get selectedMeeting() {
        return this.meetings.find((meeting) => meeting.id === Number(this.selectedMeetingId)) ?? null;
    },

    get isRunning() {
        return Boolean(this.selectedMeeting?.timerStartedAt);
    },

    get isPast() {
        const scheduledDate = this.selectedMeeting?.scheduledDate;

        if (!scheduledDate) return false;

        const meetingDay = new Date(`${scheduledDate}T00:00:00`);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        return meetingDay < today;
    },

    get canStart() {
        return this.selectedMeeting
            && !this.isPast
            && !this.isRunning
            && !['completed', 'canceled'].includes(this.selectedMeeting.status);
    },

    get canClosePast() {
        return this.isPast && !this.isRunning && ['draft', 'scheduled'].includes(this.selectedMeeting?.status);
    },

    get canFinish() {
        return this.selectedMeeting?.status === 'ongoing' || this.isRunning;
    },

    get elapsedSeconds() {
        this.tickCount;

        const meeting = this.selectedMeeting;

        if (!meeting) return 0;

        const runningSeconds = meeting.timerStartedAt
            ? Math.max(0, Math.floor((Date.now() - Date.parse(meeting.timerStartedAt)) / 1000))
            : 0;

        return Number(meeting.actualDurationSeconds || 0) + runningSeconds;
    },

    get attendedCount() {
        return this.selectedMeeting?.attendees.filter((attendee) => ATTENDED_STATUSES.has(attendee.attendanceStatus)).length ?? 0;
    },

    get investedValue() {
        const confirmedHourlyRate = this.selectedMeeting?.attendees.reduce(
            (total, attendee) => total + (ATTENDED_STATUSES.has(attendee.attendanceStatus) ? Number(attendee.hourlyRate) : 0),
            0,
        ) ?? 0;

        return confirmedHourlyRate * (this.elapsedSeconds / 3600);
    },

    selectMeeting() {
        this.error = '';
    },

    async start() {
        if (!this.selectedMeeting || this.isRunning || this.processing) return;

        this.processing = true;
        this.error = '';

        try {
            let response;

            try {
                response = await this.sendTimerRequest(this.selectedMeeting.startUrl);
            } catch (error) {
                const confirmation = {
                    advance_confirmation_required: {
                        message: error.message,
                        payload: { confirm_advance: true },
                    },
                    late_start_confirmation_required: {
                        message: error.message,
                        payload: { confirm_late_start: true },
                    },
                }[error.code];

                if (!confirmation) throw error;
                if (!window.confirm(confirmation.message)) return;

                response = await this.sendTimerRequest(this.selectedMeeting.startUrl, confirmation.payload);
            }

            this.selectedMeeting.actualDurationSeconds = response.actualDurationSeconds;
            this.selectedMeeting.timerStartedAt = response.timerStartedAt;
            this.selectedMeeting.scheduledDate = response.scheduledDate;
            this.selectedMeeting.date = new Date(`${response.scheduledDate}T00:00:00`).toLocaleDateString('pt-BR');
            this.startTicking();
        } catch (error) {
            this.error = error instanceof Error ? error.message : 'Não foi possível iniciar o cronômetro.';
        } finally {
            this.processing = false;
        }
    },

    async stop() {
        if (!this.selectedMeeting || !this.isRunning || this.processing) return;

        this.processing = true;
        this.error = '';

        try {
            const response = await this.sendTimerRequest(this.selectedMeeting.stopUrl);

            this.selectedMeeting.actualDurationSeconds = response.actualDurationSeconds;
            this.selectedMeeting.timerStartedAt = null;
            this.stopTicking();
        } catch (error) {
            this.error = error instanceof Error ? error.message : 'Não foi possível salvar o tempo da reunião.';
        } finally {
            this.processing = false;
        }
    },

    async finish() {
        if (!this.selectedMeeting || !this.canFinish || this.processing) return;

        this.processing = true;
        this.error = '';

        try {
            const response = await this.sendTimerRequest(this.selectedMeeting.finishUrl);

            this.selectedMeeting.actualDurationSeconds = response.actualDurationSeconds;
            this.selectedMeeting.timerStartedAt = null;
            this.selectedMeeting.status = response.status;
            this.stopTicking();
            window.location.assign(response.redirectUrl);
        } catch (error) {
            this.error = error instanceof Error ? error.message : 'Não foi possível finalizar a reunião.';
        } finally {
            this.processing = false;
        }
    },

    async sendTimerRequest(url, payload = null) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');

        if (!csrfToken) {
            throw new Error('Não foi possível validar sua sessão. Atualize a página e tente novamente.');
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                ...(payload ? { 'Content-Type': 'application/json' } : {}),
            },
            body: payload ? JSON.stringify(payload) : undefined,
        });
        const data = await response.json();

        if (!response.ok) {
            throw Object.assign(new Error(data.message || 'Não foi possível atualizar o cronômetro.'), { code: data.code });
        }

        return data;
    },

    startTicking() {
        this.stopTicking();
        this.interval = window.setInterval(() => {
            this.tickCount += 1;
        }, 1000);
    },

    stopTicking() {
        if (this.interval !== null) {
            window.clearInterval(this.interval);
            this.interval = null;
        }
    },

    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    },

    formatTime() {
        const hours = Math.floor(this.elapsedSeconds / 3600).toString().padStart(2, '0');
        const minutes = Math.floor((this.elapsedSeconds % 3600) / 60).toString().padStart(2, '0');
        const seconds = (this.elapsedSeconds % 60).toString().padStart(2, '0');

        return `${hours}:${minutes}:${seconds}`;
    },
});
