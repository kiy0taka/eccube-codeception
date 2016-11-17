#!/bin/bash

cd /var
rm -r /var/www
git clone --depth=50 -b ${ECCUBE_BRANCH} ${ECCUBE_REPOS} ${ECCUBE_PATH}
cd ${ECCUBE_PATH}
git fetch origin pull/1922/head:pull_1922
git checkout pull_1922
