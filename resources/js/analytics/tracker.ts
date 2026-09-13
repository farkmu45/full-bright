import { EVENT_TYPES, isCriticalEvent } from '@/analytics/event-types';
import type { EventType } from '@/analytics/event-types';
import { AnalyticsQueue } from '@/analytics/queue';
import type { EventData, QueuedEvent } from '@/types/analytics';

const queue = new AnalyticsQueue();
let maximumScrollDepth = 0;

function eventId(): string {
    return (
        window.crypto?.randomUUID?.() ??
        `${Date.now()}-${Math.random().toString(36).slice(2)}`
    );
}

export function landingSource(): string {
    if (typeof window === 'undefined') {
        return '/';
    }

    const key = 'pbm_landing_source';
    const existing = window.sessionStorage.getItem(key);

    if (existing) {
        return existing;
    }

    window.sessionStorage.setItem(key, window.location.pathname);

    return window.location.pathname;
}

function makePayload(eventType: EventType, data: EventData): QueuedEvent {
    const pageViewId =
        eventType === EVENT_TYPES.visit
            ? window.__META_PAGE_VIEW_EVENT_ID
            : undefined;

    return {
        event_type: eventType,
        event_data: {
            ...data,
            event_id: data.event_id ?? pageViewId ?? eventId(),
            landing_source: data.landing_source ?? landingSource(),
        },
    };
}

function pushDataLayer(
    eventType: EventType,
    data: EventData,
    payload: QueuedEvent,
): void {
    window.dataLayer ??= [];
    window.dataLayer.push({
        event: eventType,
        zone: data.zone,
        action: data.action,
        cta_label: data.cta_label,
        landing_source: payload.event_data.landing_source,
        value: data.value,
        currency: data.currency,
    });

    const metaEvent = window.__PBM_META_EVENTS?.[eventType];

    if (metaEvent && window.fbq) {
        const standardEvents = new Set([
            'PageView',
            'ViewContent',
            'InitiateCheckout',
            'Lead',
            'Purchase',
        ]);
        window.fbq(
            standardEvents.has(metaEvent) ? 'track' : 'trackCustom',
            metaEvent,
            {},
            { eventID: payload.event_data.event_id },
        );
    }
}

export function track(
    eventType: EventType,
    data: EventData = {},
): Promise<void> {
    if (typeof window === 'undefined') {
        return Promise.resolve();
    }

    const payload = makePayload(eventType, data);
    pushDataLayer(eventType, data, payload);

    if (isCriticalEvent(eventType)) {
        queue.sendImmediately(payload);
    } else {
        queue.enqueue(payload);
    }

    return Promise.resolve();
}

export function trackServerConfirmed(
    eventType: EventType,
    confirmedEventId: string,
    data: EventData = {},
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const payload = makePayload(eventType, {
        ...data,
        event_id: confirmedEventId,
    });
    pushDataLayer(eventType, data, payload);
}

export async function trackVisit(data: EventData = {}): Promise<boolean> {
    if (typeof window === 'undefined') {
        return false;
    }

    const payload = makePayload(EVENT_TYPES.visit, data);
    pushDataLayer(EVENT_TYPES.visit, data, payload);

    return queue.deliver(payload);
}

export function flushTracking(): void {
    queue.flushBeacon();
}

export function sendHeartbeat(
    durationSeconds: number,
    maxScrollDepth: number,
): void {
    const csrf =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';
    // Keepalive fetch instead of sendBeacon so the CSRF header is sent; see
    // AnalyticsQueue::sendBeacon.
    void fetch('/analytics/heartbeat', {
        method: 'POST',
        body: JSON.stringify({
            duration_seconds: durationSeconds,
            max_scroll_depth: maxScrollDepth,
            landing_source: landingSource(),
        }),
        keepalive: true,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            Accept: 'application/json',
        },
    }).catch(() => undefined);
}

export function rememberScrollDepth(depth: number): void {
    maximumScrollDepth = Math.max(maximumScrollDepth, depth);
}

export function getMaximumScrollDepth(): number {
    return maximumScrollDepth;
}
