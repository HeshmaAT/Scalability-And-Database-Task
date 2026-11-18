FROM nginx:stable
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx-module-njs \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/cache/nginx/lab4 \
    && chown -R nginx:nginx /var/cache/nginx/lab4
