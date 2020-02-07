#!/bin/bash

set -e

cmd="$@"

echo "Waiting for mysql"
until mysql -h db --password=password -uroot &> /dev/null
do
  printf "."
  sleep 1
done


>&2 echo "MySQL Ready"

bin/console doctrine:schema:create
bin/console eccube:fixtures:load
mysql -h db -u root --password=password eccube_db -e "update dtb_base_info set authentication_key='test';"

bin/console cache:warmup --env=prod

chown -R www-data:www-data ${ECCUBE_PATH}/app
apache2-foreground
