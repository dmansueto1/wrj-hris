/**
 * clockPad: used by the Clock In/Out screen and the kiosk. clock(action, ...args) asks the browser
 * for the location (5 s limit, nulls when denied or unavailable), then calls the Livewire action
 * with (lat, lng, ...args). `busy` disables the buttons while saving.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('clockPad', () => ({
        busy: false,

        locate() {
            return new Promise((resolve) => {
                if (! navigator.geolocation) {
                    return resolve([null, null]);
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => resolve([position.coords.latitude, position.coords.longitude]),
                    () => resolve([null, null]),
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 },
                );
            });
        },

        async clock(action) {
            if (this.busy) {
                return;
            }

            this.busy = true;

            try {
                const [lat, lng] = await this.locate();
                await this.$wire.call(action, lat, lng);
            } finally {
                this.busy = false;
            }
        },
    }));
});
