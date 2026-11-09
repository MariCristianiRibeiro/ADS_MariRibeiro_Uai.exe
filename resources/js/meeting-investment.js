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

    get projectedCost() {
        return this.participants.reduce((total, participant) => total + participant.hourlyRate, 0) * (this.durationMinutes / 60);
    },

    get collaboratorHours() {
        return this.participants.length * (this.durationMinutes / 60);
    },

    selectParticipants(ids) {
        this.participants = this.availableParticipants.filter((participant) => ids.includes(participant.id));
    },

    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    },
});
