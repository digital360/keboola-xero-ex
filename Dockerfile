FROM keboola/base-php56

MAINTAINER Vojtech Kurka <vokurka@keboola.com>

ENV APP_VERSION 0.1.4

WORKDIR /home

RUN git clone https://github.com/vokurka/keboola-xero-ex ./
RUN composer install --no-interaction

WORKDIR /home/src/Keboola/XeroEx

RUN git clone https://github.com/XeroAPI/XeroOAuth-PHP.git

WORKDIR /home

ENTRYPOINT php ./src/run.php --data=/data