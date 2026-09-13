import type { QueuedEvent } from '@/types/analytics';

const FLUSH_INTERVAL_MS = 2_000;
const MAX_BATCH_SIZE = 10;

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

export class AnalyticsQueue {
    private events: QueuedEvent[] = [];
    private timer: ReturnType<typeof setTimeout> | null = null;

    enqueue(event: QueuedEvent): void {
        this.events.push(event);

        if (this.events.length >= MAX_BATCH_SIZE) {
            void this.flush();

            return;
        }

        this.timer ??= setTimeout(() => void this.flush(), FLUSH_INTERVAL_MS);
    }

    sendImmediately(event: QueuedEvent): void {
        this.sendBeacon([event]);
    }

    async deliver(event: QueuedEvent): Promise<boolean> {
        return (await this.post([event])) || this.post([event]);
    }

    flushBeacon(): void {
        if (this.events.length === 0) {
            return;
        }

        const pending = this.takeEvents();
        this.sendBeacon(pending);
    }

    async flush(): Promise<void> {
        if (this.events.length === 0) {
            return;
        }

        const pending = this.takeEvents();
        const delivered = await this.post(pending);

        if (!delivered) {
            await this.post(pending);
        }
    }

    private takeEvents(): QueuedEvent[] {
        if (this.timer) {
            clearTimeout(this.timer);
        }

        this.timer = null;

        return this.events.splice(0, MAX_BATCH_SIZE);
    }

    private sendBeacon(events: QueuedEvent[]): void {
        // A keepalive fetch outlives the page like navigator.sendBeacon, but
        // it can carry the CSRF header. Over plain HTTP browsers omit
        // Sec-Fetch-Site, so Laravel only accepts the header token there.
        void fetch('/analytics/track', {
            method: 'POST',
            body: JSON.stringify({ events }),
            keepalive: true,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
        }).catch(() => undefined);
    }

    private async post(events: QueuedEvent[]): Promise<boolean> {
        try {
            const response = await fetch('/analytics/track', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ events }),
            });

            return response.ok;
        } catch {
            return false;
        }
    }
}
