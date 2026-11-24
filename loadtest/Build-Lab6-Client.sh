#!/bin/sh
set -eu
mkdir -p /tmp/lab6-client
cp /workspace/loadtest/lab6-client.package.json /tmp/lab6-client/package.json
cp /workspace/resources/js/lab6-echo.js /tmp/lab6-client/main.js
cp /workspace/loadtest/lab6-reverb-smoke.mjs /tmp/lab6-client/smoke.mjs
cp /workspace/loadtest/lab6-raw-http.cjs /tmp/lab6-client/lab6-raw-http.cjs
cd /tmp/lab6-client
npm install --no-audit --no-fund
mkdir -p /workspace/work/lab6-client
cp package-lock.json /workspace/loadtest/lab6-client.package-lock.json
./node_modules/.bin/esbuild main.js --bundle --format=iife --global-name=Lab6Socket --outfile=/workspace/public/lab6/echo.js
./node_modules/.bin/esbuild smoke.mjs --bundle --platform=node --format=cjs --external:bufferutil --external:utf-8-validate --outfile=/workspace/work/lab6-client/smoke.cjs
