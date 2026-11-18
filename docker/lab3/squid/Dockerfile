FROM alpine:3.21
RUN apk add --no-cache squid ca-certificates \
    && mkdir -p /var/log/squid \
    && chown -R squid:squid /var/log/squid
CMD ["squid", "-N", "-f", "/etc/squid/squid.conf"]
