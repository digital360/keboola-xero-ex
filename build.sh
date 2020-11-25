#!/bin/bash

docker-compose -f docker-compose.yml stop
docker-compose -f docker-compose.yml up -d --force-recreate --remove-orphans --build
docker exec -it extractor composer install