# Build stage
FROM debian:latest
MAINTAINER Xavier <xavier00.mtz@gmail.com>

RUN useradd --home /pmmp --create-home --shell /bin/bash pmmp

USER pmmp
WORKDIR /pmmp

COPY --chown=pmmp:pmmp PocketMine-MP.phar /pmmp/PocketMine-MP.phar
COPY --chown=pmmp:pmmp start.sh /pmmp/start.sh
COPY --chown=pmmp:pmmp bin /pmmp/bin
COPY --chown=pmmp:pmmp plugin_data /pmmp/plugin_data
COPY --chown=pmmp:pmmp plugins /pmmp/plugins
COPY --chown=pmmp:pmmp worlds /pmmp/worlds
COPY --chown=pmmp:pmmp server.properties /pmmp/server.properties
COPY --chown=pmmp:pmmp white-list.txt /pmmp/white-list.txt

EXPOSE 19132
EXPOSE 19132/udp

RUN chmod +x start.sh
RUN chmod +x bin/php7/bin/php
ENTRYPOINT ["./start.sh"]