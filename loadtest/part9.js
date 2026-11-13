import http from 'k6/http';
import { check } from 'k6';
import exec from 'k6/execution';
import { SharedArray } from 'k6/data';
import { Counter, Rate, Trend } from 'k6/metrics';

const input = new SharedArray('part9-input', () => {
    const text = open('../work/part9-input.json').replace(/^\uFEFF/, '');
    return [JSON.parse(text)];
})[0];

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8080')
    .replace(/\/$/, '');

const redirectRequests = new Counter('redirect_requests');
const createRequests = new Counter('create_requests');
const requestErrors = new Rate('request_errors');

const redirectLatency = new Trend('redirect_latency', true);
const createLatency = new Trend('create_latency', true);

export const options = {
    scenarios: {
        baseline: {
            executor: 'constant-arrival-rate',
            rate: Number(__ENV.RATE || 50),
            timeUnit: '1s',
            duration: '2m',
            preAllocatedVUs: 20,
            maxVUs: 100,
            gracefulStop: '30s',
        },
    },

    maxRedirects: 0,
    discardResponseBodies: true,

    summaryTrendStats: [
        'avg',
        'min',
        'med',
        'p(95)',
        'p(99)',
        'max',
    ],

    thresholds: {
        checks: ['rate==1'],
        request_errors: ['rate==0'],
    },
};

export default function () {
    const iteration = exec.scenario.iterationInTest;

    // One create for every 100 redirects.
    if (iteration % 101 === 100) {
        createLink();
        return;
    }

    redirectLink();
}

function redirectLink() {
    // About 80% of redirects target only 10 hot links.
    const hot = Math.random() < 0.8;

    const index = hot
        ? Math.floor(Math.random() * 10)
        : 10 + Math.floor(Math.random() * (input.codes.length - 10));

    const response = http.get(
        `${baseUrl}/${input.codes[index]}`,
        {
            redirects: 0,
            timeout: '10s',
            tags: { name: 'GET /{shortCode}', operation: 'redirect' },
            responseCallback: http.expectedStatuses(302),
        },
    );

    redirectRequests.add(1);
    redirectLatency.add(response.timings.duration);

    const success = check(response, {
        'redirect returns 302': r => r.status === 302,
        'redirect has Location': r => Boolean(r.headers.Location),
    });

    requestErrors.add(!success);

    if (!success) {
        console.warn(`Redirect failed: status=${response.status}`);
    }
}

function createLink() {
    const response = http.post(
        `${baseUrl}/api/links`,
        JSON.stringify({
            destination_url: 'https://example.com/part9-created',
        }),
        {
            headers: {
                Authorization: `Bearer ${input.token}`,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            timeout: '10s',
            tags: { name: 'POST /api/links', operation: 'create' },
            responseCallback: http.expectedStatuses(201),
        },
    );

    createRequests.add(1);
    createLatency.add(response.timings.duration);

    const success = check(response, {
        'create returns 201': r => r.status === 201,
    });

    requestErrors.add(!success);

    if (!success) {
        console.warn(`Create failed: status=${response.status}`);
    }
}
