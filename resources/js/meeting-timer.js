export default (initialParticipants = []) => ({
    participants: initialParticipants.map((participant) => ({
        ...participant,
        accumulatedCost: Number(participant.accumulatedCost || 0),
        isActive: participant.isActive !== false,
    })),
    durationMinutes: 60,
    elapsedSeconds: 0,
    isRunning: false,
    interval: null,

    get projectedCost() {
        return this.participants.reduce((total, participant) => total + this.hourlyRate(participant), 0) * (this.durationMinutes / 60);
    },

    get liveCost() {
        return this.participants.reduce((total, participant) => total + participant.accumulatedCost, 0);
    },

    hourlyRate(participant) {
        if (participant.type === 'internal') return Number(participant.salary || 0) / 220;
        if (participant.type === 'external') return Number(participant.hourlyRate || 0);
        return 0;
    },

    start() {
        if (this.isRunning) return;
        this.isRunning = true;
        this.interval = window.setInterval(() => this.tick(), 1000);
    },

    pause() {
        this.isRunning = false;
        window.clearInterval(this.interval);
        this.interval = null;
    },

    tick() {
        this.elapsedSeconds += 1;
        this.participants.forEach((participant) => {
            if (participant.isActive) participant.accumulatedCost += this.hourlyRate(participant) / 3600;
        });
    },

    remove(participant) {
        participant.isActive = false;
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
