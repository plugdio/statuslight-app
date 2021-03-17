#!/bin/bash
git pull origin master
#git fetch
#git reset --hard origin/master

chmod 777 src/logs/
chmod 777 src/tmp/

cp .env_local .env

docker-compose stop
docker-compose rm -f
docker-compose pull
docker-compose up --build -d

