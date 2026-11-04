FROM golang:1.26-bookworm AS builder

RUN CGO_ENABLED=0 go install github.com/minio/minio@latest

FROM debian:bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /data

COPY --from=builder /go/bin/minio /usr/local/bin/minio

EXPOSE 9000 9001

ENTRYPOINT ["minio"]
