FROM php:7-cli-alpine

# Create a group and user
RUN addgroup -g 1000 appgroup && adduser -u 1000 appuser -G appgroup -D appuser

# latest composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY ./app /usr/src/app

WORKDIR /usr/src/app

# Finish composer
RUN composer install --no-interaction

#USER appuser
ENTRYPOINT php ./run.php --data=/data

#CMD [ "php", "./wait.php" ]
