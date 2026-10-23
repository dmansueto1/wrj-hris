/**
 * clockPad: used by the Clock In/Out screen and the kiosk. clock(action, ...args) asks the browser
 * for the location (5 s limit, nulls when denied or unavailable), then calls the Livewire action
 * with (lat, lng, ...args). `busy` disables the buttons while saving.
 */
document.addEventListener('alpine:init', () => {
    /**
     * runningTime: worked time of an attendance that is still Clocked In, e.g. "2h 05m 09s",
     * counted from its clock-in (milliseconds) and refreshed every second.
     */
    window.Alpine.data('runningTime', (start) => ({
        elapsed: '',
        timer: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        tick() {
            const seconds = Math.max(0, Math.floor((Date.now() - start) / 1000));
            const pad = (value) => String(value).padStart(2, '0');
            this.elapsed = Math.floor(seconds / 3600) + 'h ' + pad(Math.floor((seconds % 3600) / 60)) + 'm ' + pad(seconds % 60) + 's';
        },
    }));

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
    /**
     * cameraCapture (T-10.2), used inside <x-clock-photo-modal>: the camera stays off until the user presses "Open camera". Then a live
     * front-camera preview, snapshot scaled to at most 720 px, JPEG at quality 0.8, uploaded into the
     * Livewire `photo` property. The stream stops when the photo is taken or the step closes.
     */
    window.Alpine.data('cameraCapture', () => ({
        state: 'idle', // idle | starting | live | uploading | taken | error
        preview: null,
        stream: null,

        destroy() {
            this.stop();
        },

        async start() {
            this.state = 'starting';
            this.preview = null;

            if (! navigator.mediaDevices?.getUserMedia) {
                this.state = 'error';
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.state = 'live';
            } catch (error) {
                this.state = 'error';
            }
        },

        stop() {
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
        },

        take() {
            const video = this.$refs.video;
            const scale = Math.min(1, 720 / Math.max(video.videoWidth, video.videoHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            this.preview = canvas.toDataURL('image/jpeg', 0.8);
            this.state = 'uploading';
            this.stop();

            canvas.toBlob((blob) => {
                const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                this.$wire.upload('photo', file, () => (this.state = 'taken'), () => (this.state = 'error'));
            }, 'image/jpeg', 0.8);
        },

        async retake() {
            this.$wire.set('photo', null);
            await this.start();
        },

        // The modal's Close button: camera off, photo discarded, modal closed.
        dismiss(modalName) {
            this.stop();
            this.state = 'idle';
            this.preview = null;

            if (this.$wire.photo) {
                this.$wire.set('photo', null);
            }

            this.$flux.modal(modalName).close();
        },
    }));
});
