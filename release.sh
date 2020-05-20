#!/bin/bash
git pull origin master
#git fetch
#git reset --hard origin/master

cp .env_local .env

docker-compose stop
docker-compose rm -f
docker-compose pull
docker-compose up --build -d

