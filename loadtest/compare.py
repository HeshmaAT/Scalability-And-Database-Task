import argparse
import collections
import concurrent.futures
import json
import math
import time
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument("--name", required=True)
parser.add_argument("--requests", type=int, default=1000)
parser.add_argument("--concurrency", type=int, default=20)
args = parser.parse_args()


def request(_):
    started = time.perf_counter()

    try:
        with urllib.request.urlopen(
            "http://localhost:8080/instance", timeout=30
        ) as response:
            body = json.load(response)

        return {
            "ok": True,
            "hostname": body["hostname"],
            "ms": (time.perf_counter() - started) * 1000,
        }
    except Exception as error:
        return {
            "ok": False,
            "error": str(error),
            "ms": (time.perf_counter() - started) * 1000,
        }


# Warm-up خارج القياس
for i in range(20):
    request(i)

started = time.perf_counter()

with concurrent.futures.ThreadPoolExecutor(
    max_workers=args.concurrency
) as pool:
    results = list(pool.map(request, range(args.requests)))

duration = time.perf_counter() - started
successful = [r for r in results if r["ok"]]
latencies = sorted(r["ms"] for r in successful)


def percentile(p):
    if not latencies:
        return None
    return round(latencies[math.ceil(p * len(latencies)) - 1], 2)


report = {
    "scenario": args.name,
    "requests": args.requests,
    "concurrency": args.concurrency,
    "success": len(successful),
    "errors": len(results) - len(successful),
    "duration_seconds": round(duration, 2),
    "successful_rps": round(len(successful) / duration, 2),
    "p50_ms": percentile(0.50),
    "p95_ms": percentile(0.95),
    "p99_ms": percentile(0.99),
    "hosts": dict(collections.Counter(r["hostname"] for r in successful)),
    "error_examples": list({
        r["error"] for r in results if not r["ok"]
    })[:5],
}

print(json.dumps(report, indent=2))

with open(f"loadtest/{args.name}.json", "w", encoding="utf-8") as file:
    json.dump(report, file, indent=2)
