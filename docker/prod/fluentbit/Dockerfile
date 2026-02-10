FROM busybox as config
ARG APP_NAME
COPY fluent-bit.conf /tmp/
RUN sed -i "s/%APP_NAME%/$APP_NAME/" /tmp/fluent-bit.conf

FROM fluent/fluent-bit:1.9.1
COPY * /fluent-bit/etc/
COPY --from=config /tmp/fluent-bit.conf /fluent-bit/etc/
