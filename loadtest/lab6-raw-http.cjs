const http = require('node:http');
const https = require('node:https');

// Internal test traffic: preserve the explicit virtual-host header, no redirects.
function rawFetch(url, options = {}) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const transport = parsed.protocol === 'https:' ? https : http;
    const headers = {...options.headers};
    if (options.body != null) headers['Content-Length'] = Buffer.byteLength(options.body);
    const request = transport.request(parsed, {
      method: options.method || 'GET', headers, signal: options.signal,
    }, response => {
      const chunks = [];
      response.on('data', chunk => chunks.push(chunk));
      response.on('error', reject);
      response.on('end', () => {
        const body = Buffer.concat(chunks).toString('utf8');
        const status = response.statusCode || 0;
        resolve({status, ok: status >= 200 && status < 300,
          headers: {get: name => response.headers[name.toLowerCase()] ?? null},
          json: async () => JSON.parse(body), text: async () => body,
        });
      });
    });
    request.on('error', reject);
    if (options.body != null) request.write(options.body);
    request.end();
  });
}
module.exports = {rawFetch};
