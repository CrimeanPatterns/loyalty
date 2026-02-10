FROM aw-loyalty-prod-web

ARG trusted_proxies
ENV TRUSTED_PROXIES=$trusted_proxies

EXPOSE 9000
USER root

RUN \
  ln -s /www/loyalty/current/docker/prod/fpm-supervisor.conf /etc/supervisor/conf.d/loyalty-web.conf

ENTRYPOINT ["/www/loyalty/current/docker/prod/entrypoint.php"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
