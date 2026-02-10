FROM nginx:1.19

COPY docker/prod/nginx-prod.conf /etc/nginx/conf.d/default.conf
COPY web /www/loyalty/current/web

EXPOSE 80
