#!/bin/bash

npm run build

cat build/static/js/main.*.js build/static/js/*.chunk.js > ../js/main.js
cat build/static/css/*.css > ../style/main.css

#IMAGE_OUTPUT_DIR="../../../../../pub/media"
#mkdir -p $IMAGE_OUTPUT_DIR
#rm -Rf $IMAGE_OUTPUT_DIR/*
#cp -r public/media/* $IMAGE_OUTPUT_DIR
