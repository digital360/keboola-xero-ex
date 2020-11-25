FROM php:7-cli-alpine as base

# Create a group and user
RUN addgroup -g 1000 appgroup && adduser -u 1000 appuser -G appgroup -D appuser

# latest composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY --chown=appuser:appgroup ./app /usr/src/app

WORKDIR /usr/src/app

USER appuser

# Finish composer
RUN composer install --no-interaction

FROM base as prod
ENTRYPOINT php ./run.php --data=/data

FROM base as dev
CMD [ "php", "./wait.php" ]
