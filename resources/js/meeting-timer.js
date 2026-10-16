export default (initialParticipants = [], initialElapsedSeconds = null, initialDurationMinutes = 60, includePendingAttendance = false) => ({
    availableParticipants: initialParticipants.map((participant) => ({
        ...participant,
        hourlyRate: Number(participant.hourlyRate || 0),
    })),
    participants: initialParticipants.filter((participant) => participant.isSelected).map((participant) => ({
        ...participant,
        hourlyRate: Number(participant.hourlyRate || 0),
    })),
    durationMinutes: Number(initialDurationMinutes || 60),
    elapsedSeconds: Number(initialElapsedSeconds || 0),
    hasStarted: initialElapsedSeconds !== null,
    includePendingAttendance,
    isRunning: false,
    interval: null,
    startedAt: null,

    get projectedCost() {
        return this.participants.reduce((total, participant) => total + this.hourlyRate(participant), 0) * (this.durationMinutes / 60);
    },

    get liveCost() {
        const hourlyRate = this.participants.reduce(
            (total, participant) => total + (participant.attendanceStatus === 'confirmed' || this.includePendingAttendance ? participant.hourlyRate : 0),
            0,
        );
        return hourlyRate * (this.elapsedSeconds / 3600);
    },

    hourlyRate(participant) {
        return participant.hourlyRate;
    },

    selectParticipants(ids) {
        const attendanceById = new Map(this.participants.map((participant) => [participant.id, participant.attendanceStatus]));
        this.participants = this.availableParticipants
            .filter((participant) => ids.includes(participant.id))
            .map((participant) => ({
                ...participant,
                attendanceStatus: attendanceById.get(participant.id) || participant.attendanceStatus,
            }));
    },

    setAttendanceStatus(id, status) {
        const participant = this.participants.find((candidate) => candidate.id === id);
        if (participant) participant.attendanceStatus = status;
    },

    start() {
        if (this.isRunning) return;
        this.hasStarted = true;
        this.startedAt = Date.now() - this.elapsedSeconds * 1000;
        this.isRunning = true;
        this.tick();
        this.interval = window.setInterval(() => this.tick(), 250);
    },

    tick() {
        this.elapsedSeconds = Math.floor((Date.now() - this.startedAt) / 1000);
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
