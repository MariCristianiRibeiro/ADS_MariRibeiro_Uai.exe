export default (initialParticipants = [], initialDurationMinutes = 60) => ({
    availableParticipants: initialParticipants.map((participant) => ({
        ...participant,
        hourlyRate: Number(participant.hourlyRate || 0),
    })),
    participants: initialParticipants.filter((participant) => participant.isSelected).map((participant) => ({
        ...participant,
        hourlyRate: Number(participant.hourlyRate || 0),
    })),
    durationMinutes: Number(initialDurationMinutes || 60),
    durationDerivedFromSchedule: false,

    get projectedCost() {
        return this.participants.reduce((total, participant) => total + participant.hourlyRate, 0) * (this.durationMinutes / 60);
    },

    get collaboratorHours() {
        return this.participants.length * (this.durationMinutes / 60);
    },

    selectParticipants(ids) {
        this.participants = this.availableParticipants.filter((participant) => ids.includes(participant.id));
    },

    setDurationFromSchedule(startTime, endTime) {
        if (!startTime || !endTime) {
            this.durationDerivedFromSchedule = false;

            return;
        }

        const [startHour, startMinute] = startTime.split(':').map(Number);
        const [endHour, endMinute] = endTime.split(':').map(Number);
        const durationMinutes = endHour * 60 + endMinute - (startHour * 60 + startMinute);

        if (!Number.isInteger(durationMinutes) || durationMinutes < 1) {
            this.durationDerivedFromSchedule = false;

            return;
        }

        this.durationMinutes = durationMinutes;
        this.durationDerivedFromSchedule = true;
    },

    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    },
});
