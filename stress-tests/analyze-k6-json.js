const fs = require('fs');

const inputPath = process.argv[2];

if (!inputPath) {
  console.error('Usage: node stress-tests/analyze-k6-json.js <k6-json-output.ndjson>');
  process.exit(1);
}

const raw = fs.readFileSync(inputPath, 'utf8');
const endpointDurations = new Map();
const allDurations = [];
let httpRequests = 0;
let failedRequestSamples = 0;
let failedRequests = 0;
let checkSamples = 0;
let failedChecks = 0;
let firstTime = null;
let lastTime = null;

function percentile(values, p) {
  if (!values.length) {
    return 0;
  }

  const sorted = [...values].sort((a, b) => a - b);
  const index = Math.ceil((p / 100) * sorted.length) - 1;
  return sorted[Math.max(0, Math.min(index, sorted.length - 1))];
}

function average(values) {
  if (!values.length) {
    return 0;
  }

  return values.reduce((sum, value) => sum + value, 0) / values.length;
}

function formatMs(value) {
  return `${value.toFixed(2)} ms`;
}

function pointTime(data) {
  const time = data && data.time ? Date.parse(data.time) : NaN;
  return Number.isNaN(time) ? null : time;
}

for (const line of raw.split(/\r?\n/)) {
  if (!line.trim()) {
    continue;
  }

  let entry;
  try {
    entry = JSON.parse(line);
  } catch {
    continue;
  }

  if (entry.type !== 'Point') {
    continue;
  }

  const metric = entry.metric || entry.data?.metric || entry.data?.name;
  const data = entry.data || {};
  const value = data.value;
  const tags = data.tags || {};
  const timestamp = pointTime(data);

  if (timestamp !== null) {
    firstTime = firstTime === null ? timestamp : Math.min(firstTime, timestamp);
    lastTime = lastTime === null ? timestamp : Math.max(lastTime, timestamp);
  }

  if (metric === 'http_reqs') {
    httpRequests += Number(value) || 0;
  }

  if (metric === 'http_req_failed') {
    failedRequestSamples += 1;
    if (value === true || value === 'true' || Number(value) === 1) {
      failedRequests += 1;
    }
  }

  if (metric === 'checks') {
    checkSamples += 1;
    if (Number(value) !== 1) {
      failedChecks += 1;
    }
  }

  if (metric === 'http_req_duration') {
    const duration = Number(value);
    if (!Number.isFinite(duration)) {
      continue;
    }

    const endpoint = tags.endpoint || tags.name || tags.url || 'unknown';
    if (!endpointDurations.has(endpoint)) {
      endpointDurations.set(endpoint, []);
    }

    endpointDurations.get(endpoint).push(duration);
    allDurations.push(duration);
  }
}

const elapsedSeconds = firstTime !== null && lastTime !== null
  ? Math.max((lastTime - firstTime) / 1000, 1)
  : 0;
const failedRequestRate = failedRequestSamples ? failedRequests / failedRequestSamples : 0;

const endpoints = [...endpointDurations.entries()]
  .map(([endpoint, values]) => ({
    endpoint,
    count: values.length,
    avg: average(values),
    p90: percentile(values, 90),
    p95: percentile(values, 95),
    max: Math.max(...values),
  }))
  .sort((a, b) => b.p95 - a.p95);

const slowest = endpoints[0];

console.log(`Total requests: ${httpRequests}`);
console.log(`Requests/sec: ${elapsedSeconds ? (httpRequests / elapsedSeconds).toFixed(2) : '0.00'}`);
console.log(`Average response time: ${formatMs(average(allDurations))}`);
console.log(`p90 response time: ${formatMs(percentile(allDurations, 90))}`);
console.log(`p95 response time: ${formatMs(percentile(allDurations, 95))}`);
console.log(`Max response time: ${formatMs(allDurations.length ? Math.max(...allDurations) : 0)}`);
console.log(`Failed request rate: ${(failedRequestRate * 100).toFixed(2)}% (${failedRequests}/${failedRequestSamples})`);
console.log(`Failed checks: ${failedChecks}/${checkSamples}`);

if (slowest) {
  console.log(`Slowest endpoint by p95: ${slowest.endpoint} (${formatMs(slowest.p95)} p95, ${formatMs(slowest.avg)} avg, ${formatMs(slowest.max)} max, ${slowest.count} samples)`);
}

console.log('Top endpoints by p95:');
for (const item of endpoints.slice(0, 10)) {
  console.log(`- ${item.endpoint}: p95 ${formatMs(item.p95)}, avg ${formatMs(item.avg)}, max ${formatMs(item.max)}, samples ${item.count}`);
}
