#!/bin/bash

if ! command -v composer &> /dev/null
then
    cd ../../../../
    composer dump-autoload
fi