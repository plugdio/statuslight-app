#!/bin/sh

#check environmental variables
#if [ "${DOMAIN}x" == "x" ];
#then
#    echo "DOMAIN not set, can't continue";
#    kill -SIGTERM 1
#    exit
#fi

#if [ "${EMAIL}x" == "x" ];
#then
#    echo "EMAIL not set, can't continue";
#    kill -SIGTERM 1
#    exit
#fi

#if [ "${BACKEND}x" == "x" ];
#then
#    echo "BACKEND not set, can't continue";
#    kill -SIGTERM 1
#    exit
#fi

#if [ "${STAGING}x" == "x" ];
#then
#    #Production server
#    SERVER=https://acme-v02.api.letsencrypt.org/directory
#else
#    #Staging server
#    SERVER=https://acme-staging-v02.api.letsencrypt.org/directory
#fi

#Run various file-related checks

supervisorctl start nginx 2>&1 >/dev/null
echo " done"