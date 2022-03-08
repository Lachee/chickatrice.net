#!/bin/bash
while [ true ]
do 
    ./cli cron/all;
    echo "waiting... press ctrl + c to abort";
    sleep 1
done