#!/bin/bash

export STATUSLIGHT_ENV="TEST"

git pull origin master

docker-compose stop
docker-compose rm -f
docker-compose pull
docker-compose up --build -d
