FROM aw-loyalty-prod-worker

USER root

RUN \
  DEBIAN_FRONTEND=noninteractive apt-get update && \
  DEBIAN_FRONTEND=noninteractive apt-get install -y \
    ffmpeg \
    pdftohtml \
    zbar-tools && \
	rm -rf /var/lib/apt/lists/*

RUN \
  ln -s /www/loyalty/current/docker/prod/supervisor.conf /etc/supervisor/conf.d/loyalty-worker.conf \
  && mkdir -p /www/loyalty/current/var/logs/check/tmp \
  && mkdir -p /www/loyalty/current/var/logs/check/checklogs \
  && chown -R www-data:www-data /www/loyalty/current/var/logs

ENTRYPOINT ["/www/loyalty/current/docker/prod/entrypoint.php"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
